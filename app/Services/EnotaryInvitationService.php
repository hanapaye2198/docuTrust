<?php

namespace App\Services;

use App\Enums\EkycStatus;
use App\Enums\OnboardingStep;
use App\Enums\OrganizationRole;
use App\Enums\UserRole;
use App\Enums\UserWorkspace;
use App\Mail\EnotarySignerInvitationMail;
use App\Models\EnotaryInvitation;
use App\Models\NotaryRequest;
use App\Models\NotarySigner;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class EnotaryInvitationService
{
    public const EXPIRY_DAYS = 7;

    public function inviteSignerFromAttorney(
        User $attorney,
        NotaryRequest $request,
        NotarySigner $signer,
    ): ?EnotaryInvitation {
        if ($attorney->role !== UserRole::Notary) {
            throw new RuntimeException(__('Only the assigned attorney can send e-Notary invitations.'));
        }

        if ($request->notary_user_id !== $attorney->id) {
            throw new RuntimeException(__('You are not the assigned attorney for this case.'));
        }

        $email = strtolower(trim($signer->email));

        $existingUser = User::query()->where('email', $email)->first();

        if ($existingUser !== null) {
            if ($existingUser->workspace === UserWorkspace::Signing) {
                throw new RuntimeException(__('This email belongs to a document signing account. Use a different email for e-Notary access.'));
            }

            if ($existingUser->canAccessEnotaryWorkspace()) {
                $this->alignEnotaryUserToRequest($existingUser, $request);

                return null;
            }
        }

        $invitation = EnotaryInvitation::query()
            ->where('notary_signer_id', $signer->id)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if ($invitation === null) {
            $invitation = EnotaryInvitation::query()->create([
                'token' => (string) Str::uuid(),
                'email' => $email,
                'full_name' => $signer->full_name,
                'notary_request_id' => $request->id,
                'notary_signer_id' => $signer->id,
                'organization_id' => $request->organization_id,
                'invited_by_user_id' => $attorney->id,
                'expires_at' => now()->addDays(self::EXPIRY_DAYS),
            ]);
        }

        $this->sendInvitationMail($invitation);

        return $invitation;
    }

    public function resendInvitation(User $attorney, NotarySigner $signer): EnotaryInvitation
    {
        $request = $signer->notaryRequest;
        if ($request === null) {
            throw new RuntimeException(__('Notary request not found for this signer.'));
        }

        EnotaryInvitation::query()
            ->where('notary_signer_id', $signer->id)
            ->whereNull('accepted_at')
            ->update(['expires_at' => now()]);

        return $this->inviteSignerFromAttorney($attorney, $request, $signer)
            ?? throw new RuntimeException(__('This signer already has e-Notary portal access.'));
    }

    public function findPendingByToken(string $token): ?EnotaryInvitation
    {
        $invitation = EnotaryInvitation::query()
            ->with(['notaryRequest', 'invitedBy', 'notarySigner'])
            ->where('token', $token)
            ->first();

        if ($invitation === null || $invitation->isAccepted() || $invitation->isExpired()) {
            return null;
        }

        return $invitation;
    }

    /**
     * @param  array{
     *     first_name: string,
     *     middle_name?: string|null,
     *     last_name: string,
     *     suffix?: string|null,
     *     password: string,
     * }  $profile
     */
    public function acceptAsNewUser(EnotaryInvitation $invitation, array $profile): User
    {
        if (! $invitation->isPending()) {
            throw new RuntimeException(__('This invitation is no longer valid.'));
        }

        if (User::query()->where('email', $invitation->email)->exists()) {
            throw ValidationException::withMessages([
                'email' => __('An account with this email already exists. Sign in to accept the invitation.'),
            ]);
        }

        return DB::transaction(function () use ($invitation, $profile): User {
            $fullName = collect([
                $profile['first_name'],
                $profile['middle_name'] ?? null,
                $profile['last_name'],
                $profile['suffix'] ?? null,
            ])->filter(fn (?string $value): bool => filled($value))
                ->map(fn (string $value): string => trim($value))
                ->implode(' ');

            $user = User::query()->create([
                'organization_id' => $invitation->organization_id,
                'name' => $fullName,
                'first_name' => $profile['first_name'],
                'middle_name' => $profile['middle_name'] ?? null,
                'last_name' => $profile['last_name'],
                'suffix' => $profile['suffix'] ?? null,
                'email' => $invitation->email,
                'password' => $profile['password'],
                'role' => UserRole::Client,
                'workspace' => UserWorkspace::Enotary,
                'organization_role' => OrganizationRole::Member,
                'onboarding_step' => OnboardingStep::EmailVerification,
                'ekyc_status' => EkycStatus::NotSubmitted,
                'email_verified_at' => null,
                'mfa_enabled' => false,
                'two_factor_enabled' => false,
                'two_factor_onboarding_completed_at' => null,
            ]);

            $this->markAccepted($invitation, $user);

            return $user;
        });
    }

    public function acceptForAuthenticatedUser(EnotaryInvitation $invitation, User $user): User
    {
        if (! $invitation->isPending()) {
            throw new RuntimeException(__('This invitation is no longer valid.'));
        }

        if (strtolower($user->email) !== strtolower($invitation->email)) {
            throw ValidationException::withMessages([
                'email' => __('Sign in with :email to accept this invitation.', ['email' => $invitation->email]),
            ]);
        }

        if ($user->workspace === UserWorkspace::Signing) {
            throw ValidationException::withMessages([
                'email' => __('This account is for document signing only. Contact your attorney for assistance.'),
            ]);
        }

        if (! $user->canAccessEnotaryWorkspace()) {
            throw new RuntimeException(__('This account cannot access the e-Notary workspace.'));
        }

        return DB::transaction(function () use ($invitation, $user): User {
            $user->forceFill([
                'workspace' => UserWorkspace::Enotary,
                'organization_id' => $invitation->organization_id,
            ])->save();

            $this->markAccepted($invitation, $user);

            return $user->fresh();
        });
    }

    /**
     * @return array<string, EnotaryInvitation|null>
     */
    public function latestInvitationsForRequest(NotaryRequest $request): array
    {
        return EnotaryInvitation::query()
            ->where('notary_request_id', $request->id)
            ->latest('id')
            ->get()
            ->unique('notary_signer_id')
            ->keyBy(fn (EnotaryInvitation $invitation): int => (int) $invitation->notary_signer_id)
            ->all();
    }

    private function markAccepted(EnotaryInvitation $invitation, User $user): void
    {
        $invitation->update([
            'accepted_at' => now(),
            'accepted_by_user_id' => $user->id,
        ]);

        $this->alignEnotaryUserToRequest($user, $invitation->notaryRequest);
    }

    private function alignEnotaryUserToRequest(User $user, ?NotaryRequest $request): void
    {
        if ($request === null) {
            return;
        }

        if ($request->user_id === null) {
            $request->update(['user_id' => $user->id]);
        }
    }

    private function sendInvitationMail(EnotaryInvitation $invitation): void
    {
        $invitation->loadMissing(['notaryRequest', 'invitedBy']);

        $acceptUrl = route('enotary.invite.accept', ['token' => $invitation->token], absolute: true);

        Mail::to($invitation->email)->queue(new EnotarySignerInvitationMail(
            signerName: $invitation->full_name,
            attorneyName: $invitation->invitedBy?->name ?? __('Your attorney'),
            caseTitle: $invitation->notaryRequest?->title ?? __('Notarization case'),
            acceptUrl: $acceptUrl,
            expiresAt: $invitation->expires_at->timezone(config('app.timezone'))->format('M j, Y g:i A'),
        ));
    }
}
