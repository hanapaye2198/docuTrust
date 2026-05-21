<?php

namespace App\Services;

use App\Enums\NotaryCredentialStatus;
use App\Enums\UserRole;
use App\Mail\AttorneyApplicationApprovedMail;
use App\Mail\AttorneyApplicationRejectedMail;
use App\Mail\AttorneyApplicationSubmittedMail;
use App\Models\AppNotification;
use App\Models\NotaryCredential;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class AttorneyApplicationService
{
    public const RENEWAL_WINDOW_DAYS = 90;

    public function __construct(
        private readonly OnboardingAuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{
     *     commission_number: string,
     *     commission_jurisdiction: string,
     *     commission_issued_at: string,
     *     commission_expires_at: string,
     *     roll_number?: string|null,
     *     ibp_number?: string|null,
     *     ptr_number?: string|null,
     *     mcle_compliance_number?: string|null,
     *     seal_image_path?: string|null,
     *     signature_image_path?: string|null,
     *     commission_document_path?: string|null,
     *     ibp_document_path?: string|null,
     *     ptr_document_path?: string|null,
     *     mcle_document_path?: string|null,
     * }  $data
     */
    public function submit(User $user, array $data, bool $isRenewal = false): NotaryCredential
    {
        if (! $this->canSubmitApplication($user)) {
            throw new RuntimeException(__('You cannot submit an attorney application at this time.'));
        }

        if (! $user->hasCompletedOnboarding()) {
            throw new RuntimeException(__('Complete email, mobile, eKYC, and MFA onboarding before applying as an attorney.'));
        }

        if ($user->role === UserRole::Notary && ! $isRenewal) {
            $existing = $this->latestCredential($user);
            if ($existing !== null && $existing->isActive()) {
                throw new RuntimeException(__('You already have an active attorney credential.'));
            }
        }

        return DB::transaction(function () use ($user, $data, $isRenewal): NotaryCredential {
            $credential = NotaryCredential::query()
                ->where('user_id', $user->id)
                ->latest('id')
                ->first();

            $payload = array_merge($data, [
                'status' => NotaryCredentialStatus::Pending->value,
                'rejection_reason' => null,
                'reviewed_by_user_id' => null,
                'reviewed_at' => null,
                'submitted_at' => now(),
                'is_renewal' => $isRenewal,
            ]);

            if ($credential !== null) {
                $credential->update($payload);
            } else {
                $credential = NotaryCredential::query()->create(array_merge(
                    ['user_id' => $user->id],
                    $payload,
                ));
            }

            $this->auditLogger->log(
                $user,
                $isRenewal ? 'attorney_application.renewal_submitted' : 'attorney_application.submitted',
            );

            $this->notifyAdminsOfSubmission($user, $credential);

            return $credential->fresh();
        });
    }

    public function approve(NotaryCredential $credential, User $reviewer): NotaryCredential
    {
        if ($credential->status !== NotaryCredentialStatus::Pending->value) {
            throw new RuntimeException(__('Only pending applications can be approved.'));
        }

        if ($credential->commission_expires_at !== null && $credential->commission_expires_at->copy()->endOfDay()->isPast()) {
            throw new RuntimeException(__('Commission expiry date is already in the past. Ask the applicant to update dates.'));
        }

        return DB::transaction(function () use ($credential, $reviewer): NotaryCredential {
            $applicant = $credential->user;
            abort_if($applicant === null, 404);

            $credential->update([
                'status' => NotaryCredentialStatus::Active->value,
                'rejection_reason' => null,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            if ($applicant->role !== UserRole::Notary) {
                $applicant->update(['role' => UserRole::Notary]);
            }

            $this->auditLogger->log($applicant, 'attorney_application.approved');

            if ($applicant->email !== '') {
                Mail::to($applicant->email)->queue(new AttorneyApplicationApprovedMail($credential));
            }

            $this->createInAppNotification(
                $applicant->id,
                'attorney.application.approved',
                __('Your attorney application was approved. You can now access the e-Notary workspace.'),
            );

            return $credential->fresh();
        });
    }

    public function reject(NotaryCredential $credential, User $reviewer, string $reason): NotaryCredential
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException(__('A rejection reason is required.'));
        }

        if ($credential->status !== NotaryCredentialStatus::Pending->value) {
            throw new RuntimeException(__('Only pending applications can be rejected.'));
        }

        return DB::transaction(function () use ($credential, $reviewer, $reason): NotaryCredential {
            $applicant = $credential->user;
            abort_if($applicant === null, 404);

            $credential->update([
                'status' => NotaryCredentialStatus::Rejected->value,
                'rejection_reason' => $reason,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            if ($applicant->role === UserRole::Notary && ! $credential->is_renewal) {
                $applicant->update(['role' => UserRole::Client]);
            }

            $this->auditLogger->log($applicant, 'attorney_application.rejected');

            if ($applicant->email !== '') {
                Mail::to($applicant->email)->queue(new AttorneyApplicationRejectedMail($credential, $reason));
            }

            $this->createInAppNotification(
                $applicant->id,
                'attorney.application.rejected',
                __('Your attorney application was not approved: :reason', ['reason' => $reason]),
            );

            return $credential->fresh();
        });
    }

    public function latestCredential(User $user): ?NotaryCredential
    {
        return $user->notaryCredential;
    }

    public function canSubmitApplication(User $user): bool
    {
        if (! in_array($user->role, [UserRole::Client, UserRole::Notary], true)) {
            return false;
        }

        $credential = $this->latestCredential($user);

        if ($credential === null) {
            return true;
        }

        if ($credential->status === NotaryCredentialStatus::Pending->value) {
            return false;
        }

        if ($credential->status === NotaryCredentialStatus::Rejected->value) {
            return true;
        }

        if ($user->role === UserRole::Notary && $this->requiresRenewal($user)) {
            return true;
        }

        if ($user->role === UserRole::Client) {
            return $credential->status !== NotaryCredentialStatus::Active->value;
        }

        return false;
    }

    public function requiresRenewal(User $user): bool
    {
        $credential = $this->latestCredential($user);
        if ($credential === null) {
            return false;
        }

        if (! in_array($credential->status, [
            NotaryCredentialStatus::Active->value,
            NotaryCredentialStatus::Expired->value,
        ], true)) {
            return false;
        }

        if ($credential->isExpired()) {
            return true;
        }

        $expiresAt = $credential->commission_expires_at;
        if ($expiresAt === null) {
            return false;
        }

        return $expiresAt->copy()->subDays(self::RENEWAL_WINDOW_DAYS)->isPast();
    }

    /**
     * @return array{allowed: bool, message: string|null}
     */
    public function practiceEligibility(User $user): array
    {
        if ($user->role !== UserRole::Notary) {
            return [
                'allowed' => false,
                'message' => __('An approved attorney account is required.'),
            ];
        }

        if (! $user->hasCompletedOnboarding()) {
            return [
                'allowed' => false,
                'message' => __('Complete onboarding (email, mobile, eKYC, and MFA) before practicing as an attorney.'),
            ];
        }

        if (! $user->mfa_enabled) {
            return [
                'allowed' => false,
                'message' => __('Enable multi-factor authentication in Security settings before e-Notary work.'),
            ];
        }

        $credential = $this->latestCredential($user);

        if ($credential === null) {
            return [
                'allowed' => false,
                'message' => __('Submit and receive approval for your attorney credentials first.'),
            ];
        }

        if ($credential->status === NotaryCredentialStatus::Pending->value) {
            return [
                'allowed' => false,
                'message' => __('Your attorney application is pending Notary Admin review.'),
            ];
        }

        if ($credential->status === NotaryCredentialStatus::Rejected->value) {
            return [
                'allowed' => false,
                'message' => __('Your attorney application was rejected. Update your documents and reapply.'),
            ];
        }

        if ($credential->isExpired()) {
            if ($credential->status === NotaryCredentialStatus::Active->value) {
                $credential->update(['status' => NotaryCredentialStatus::Expired->value]);
            }

            return [
                'allowed' => false,
                'message' => __('Your notary commission has expired. Submit a renewal application for approval.'),
            ];
        }

        if ($credential->status === NotaryCredentialStatus::Expired->value) {
            return [
                'allowed' => false,
                'message' => __('Your notary commission has expired. Submit a renewal application for approval.'),
            ];
        }

        if ($credential->status !== NotaryCredentialStatus::Active->value || ! $credential->isActive()) {
            return [
                'allowed' => false,
                'message' => __('Active, approved attorney credentials are required.'),
            ];
        }

        return ['allowed' => true, 'message' => null];
    }

    public function storeUploadedFile(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, (string) config('filesystems.docutrust_disk', 'local'));
    }

    private function notifyAdminsOfSubmission(User $applicant, NotaryCredential $credential): void
    {
        $admins = User::query()
            ->whereIn('role', [UserRole::NotaryAdmin, UserRole::SuperAdmin])
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get();

        foreach ($admins as $admin) {
            if ($admin->email !== '') {
                Mail::to($admin->email)->queue(new AttorneyApplicationSubmittedMail($credential));
            }

            $this->createInAppNotification(
                $admin->id,
                'attorney.application.submitted',
                __(':name submitted an attorney application for review.', ['name' => $applicant->name]),
            );
        }
    }

    private function createInAppNotification(int $userId, string $type, string $message): void
    {
        AppNotification::query()->create([
            'user_id' => $userId,
            'type' => $type,
            'message' => $message,
            'read_at' => null,
            'created_at' => now(),
        ]);
    }
}
