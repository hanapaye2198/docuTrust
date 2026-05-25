<?php

namespace App\Services\Ekyc\Sumsub;

use App\Enums\EkycStatus;
use App\Enums\OnboardingStep;
use App\Models\EkycRecord;
use App\Models\User;
use App\Services\OnboardingAuditLogger;
use Illuminate\Support\Facades\Log;

class SumsubWebhookHandler
{
    public function __construct(
        private readonly SumsubApiClient $client,
        private readonly OnboardingAuditLogger $auditLogger,
    ) {}

    /**
     * Handle the applicantReviewed webhook event.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleReviewed(string $applicantId, string $externalUserId, array $payload): void
    {
        $reviewResult = $payload['reviewResult'] ?? [];
        $reviewAnswer = $reviewResult['reviewAnswer'] ?? null;

        $record = $this->findEkycRecord($applicantId, $externalUserId);

        if ($record === null) {
            Log::warning('Sumsub webhook: no matching EkycRecord found.', [
                'applicant_id' => $applicantId,
                'external_user_id' => $externalUserId,
            ]);

            return;
        }

        if ($reviewAnswer === 'GREEN') {
            $this->markVerified($record, $payload);
        } else {
            $rejectLabels = $reviewResult['rejectLabels'] ?? [];
            $rejectType = $reviewResult['reviewRejectType'] ?? 'RETRY';
            $this->markRejected($record, $rejectLabels, $rejectType, $payload);
        }
    }

    /**
     * Handle the applicantPending webhook event.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handlePending(string $applicantId, string $externalUserId, array $payload): void
    {
        $record = $this->findEkycRecord($applicantId, $externalUserId);

        if ($record === null) {
            return;
        }

        // Ensure the record stays in pending state while Sumsub processes
        if ($record->status !== EkycStatus::Pending->value) {
            $record->update(['status' => EkycStatus::Pending->value]);
        }

        Log::info('Sumsub webhook: applicant pending review.', [
            'applicant_id' => $applicantId,
            'ekyc_record_id' => $record->id,
        ]);
    }

    private function markVerified(EkycRecord $record, array $payload): void
    {
        // Fetch full applicant data for extracted identity info
        $extractedData = $this->fetchExtractedData($record->provider_reference);

        $record->update([
            'status' => EkycStatus::Verified->value,
            'verified_at' => now(),
            'ocr_text' => $extractedData !== null ? json_encode($extractedData) : null,
            'provider_payload' => $payload,
            'rejection_reason' => null,
        ]);

        $user = $record->user;

        if ($user === null) {
            return;
        }

        $user->forceFill([
            'ekyc_status' => EkycStatus::Verified,
            'kyc_verified_at' => now(),
        ])->save();

        // Advance onboarding if user is on the KYC step
        if ($user->onboarding_step === OnboardingStep::Kyc) {
            $user->forceFill(['onboarding_step' => OnboardingStep::Mfa])->save();
        }

        $this->auditLogger->log($user, 'ekyc_sumsub_verified');

        Log::info('Sumsub eKYC verified.', [
            'user_id' => $user->id,
            'ekyc_record_id' => $record->id,
        ]);
    }

    /**
     * @param  list<string>  $rejectLabels
     */
    private function markRejected(EkycRecord $record, array $rejectLabels, string $rejectType, array $payload): void
    {
        $reason = $rejectLabels !== []
            ? implode(', ', $rejectLabels)
            : __('Identity verification was not approved.');

        $record->update([
            'status' => EkycStatus::Rejected->value,
            'rejection_reason' => $reason,
            'provider_payload' => $payload,
        ]);

        $user = $record->user;

        if ($user === null) {
            return;
        }

        $user->forceFill([
            'ekyc_status' => EkycStatus::Rejected,
        ])->save();

        $this->auditLogger->log($user, 'ekyc_sumsub_rejected');

        Log::info('Sumsub eKYC rejected.', [
            'user_id' => $user->id,
            'ekyc_record_id' => $record->id,
            'reject_type' => $rejectType,
            'reject_labels' => $rejectLabels,
        ]);
    }

    private function findEkycRecord(string $applicantId, string $externalUserId): ?EkycRecord
    {
        // First try by provider reference (applicant ID)
        $record = EkycRecord::query()
            ->where('provider_reference', $applicantId)
            ->where('provider', 'sumsub')
            ->latest()
            ->first();

        if ($record !== null) {
            return $record;
        }

        // Fallback: find by user ID (external user ID) with sumsub provider
        return EkycRecord::query()
            ->where('user_id', (int) $externalUserId)
            ->where('provider', 'sumsub')
            ->where('status', EkycStatus::Pending->value)
            ->latest()
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchExtractedData(?string $applicantId): ?array
    {
        if ($applicantId === null || $applicantId === '') {
            return null;
        }

        try {
            $info = $this->client->getApplicantInfo($applicantId);

            return $info['info'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('Sumsub: failed to fetch applicant info after verification.', [
                'applicant_id' => $applicantId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
