<?php

namespace App\Services;

use App\Enums\NotaryIdentityVerificationStatus;
use App\Enums\NotaryRequestStatus;
use App\Models\AppNotification;
use App\Models\NotaryIdentityVerification;
use App\Models\NotaryJournal;
use App\Models\NotaryRequest;
use App\Models\NotarySigner;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class IdentityVerificationService
{
    /**
     * Verify the identity of the requester for a notary request.
     *
     * @param  array{
     *   id_document_type: string,
     *   id_document_number: string,
     *   id_document_path: string,
     *   selfie_path?: string|null,
     *   otp_verified?: bool,
     * }  $identityData
     */
    public function verify(NotaryRequest $request, array $identityData): NotaryRequest
    {
        if ($request->status !== NotaryRequestStatus::Submitted) {
            throw new RuntimeException(__('Identity verification can only be performed on submitted requests.'));
        }

        $idDocumentType = trim((string) ($identityData['id_document_type'] ?? ''));
        $idDocumentNumber = trim((string) ($identityData['id_document_number'] ?? ''));
        $idDocumentPath = trim((string) ($identityData['id_document_path'] ?? ''));

        if ($idDocumentType === '' || $idDocumentNumber === '' || $idDocumentPath === '') {
            throw new RuntimeException(__('Valid ID document type, number, and uploaded file are required.'));
        }

        $request->forceFill([
            'id_document_type' => $idDocumentType,
            'id_document_number' => $idDocumentNumber,
            'id_document_path' => $idDocumentPath,
            'selfie_path' => $identityData['selfie_path'] ?? null,
        ])->save();

        $request->markIdentityVerified();

        NotaryJournal::query()->create([
            'notary_request_id' => $request->id,
            'notary_user_id' => $request->notary_user_id,
            'entry_type' => 'identity_verification',
            'summary' => __('Identity verification completed. Document type: :type, Number: :number', [
                'type' => $idDocumentType,
                'number' => $idDocumentNumber,
            ]),
            'legal_assertions' => [
                'identity_verified' => true,
                'id_document_type' => $idDocumentType,
                'id_document_number' => $idDocumentNumber,
                'selfie_captured' => isset($identityData['selfie_path']) && $identityData['selfie_path'] !== null,
                'otp_verified' => $identityData['otp_verified'] ?? false,
            ],
            'recorded_at' => now(),
        ]);

        Log::channel('audit')->info('Notary identity verified (direct)', [
            'notary_request_id' => $request->id,
        ]);

        return $request->fresh();
    }

    /**
     * @param  array{
     *   id_type: string,
     *   id_number: string,
     *   id_image_path: string,
     *   selfie_image_path?: string|null,
     * }  $payload
     */
    public function submitPendingForSigner(NotarySigner $signer, array $payload): NotaryIdentityVerification
    {
        $request = $signer->notaryRequest()->firstOrFail();

        if ($request->status !== NotaryRequestStatus::Submitted) {
            throw new RuntimeException(__('Identity documents can only be submitted while the request is pending verification.'));
        }

        $idType = trim((string) ($payload['id_type'] ?? ''));
        $idNumber = trim((string) ($payload['id_number'] ?? ''));
        $idImagePath = trim((string) ($payload['id_image_path'] ?? ''));

        if ($idType === '' || $idNumber === '' || $idImagePath === '') {
            throw new RuntimeException(__('Government ID type, number, and scan are required.'));
        }

        $verification = NotaryIdentityVerification::query()->create([
            'notary_request_id' => $request->id,
            'notary_signer_id' => $signer->id,
            'id_type' => $idType,
            'id_number' => $idNumber,
            'id_image_path' => $idImagePath,
            'selfie_image_path' => isset($payload['selfie_image_path']) ? trim((string) $payload['selfie_image_path']) : null,
            'verification_status' => NotaryIdentityVerificationStatus::Pending,
        ]);

        if ($request->notary_user_id !== null) {
            AppNotification::query()->create([
                'user_id' => $request->notary_user_id,
                'type' => 'notary.identity.pending',
                'message' => __('Signer :name uploaded identity documents for ":title".', [
                    'name' => $signer->full_name,
                    'title' => $request->title,
                ]),
                'read_at' => null,
                'created_at' => now(),
            ]);
        }

        NotaryJournal::query()->create([
            'notary_request_id' => $request->id,
            'notary_user_id' => $request->notary_user_id,
            'entry_type' => 'identity_documents_submitted',
            'summary' => __('Signer :name submitted identity documents for review.', ['name' => $signer->full_name]),
            'legal_assertions' => [
                'notary_identity_verification_id' => $verification->id,
                'notary_signer_id' => $signer->id,
            ],
            'recorded_at' => now(),
        ]);

        Log::channel('audit')->info('Notary identity documents submitted', [
            'notary_request_id' => $request->id,
            'notary_signer_id' => $signer->id,
            'notary_identity_verification_id' => $verification->id,
        ]);

        return $verification;
    }

    public function approvePendingRecord(User $admin, NotaryIdentityVerification $record): NotaryRequest
    {
        if ($record->verification_status !== NotaryIdentityVerificationStatus::Pending) {
            throw new RuntimeException(__('Only pending identity reviews can be approved.'));
        }

        $request = $record->notaryRequest()->firstOrFail();

        if ($request->status !== NotaryRequestStatus::Submitted) {
            throw new RuntimeException(__('This request is not awaiting identity verification.'));
        }

        $record->forceFill([
            'verification_status' => NotaryIdentityVerificationStatus::Verified,
            'verified_by' => $admin->id,
            'verified_at' => now(),
            'rejection_reason' => null,
        ])->save();

        if ($request->id_document_path === null || $request->id_document_path === '') {
            $request->forceFill([
                'id_document_type' => $record->id_type,
                'id_document_number' => $record->id_number,
                'id_document_path' => $record->id_image_path,
                'selfie_path' => $record->selfie_image_path,
            ])->save();
        }

        if ($this->allSignersHaveApprovedIdentity($request)) {
            $request->markIdentityVerified();

            NotaryJournal::query()->create([
                'notary_request_id' => $request->id,
                'notary_user_id' => $request->notary_user_id,
                'entry_type' => 'identity_verification',
                'summary' => __('All signer identities reviewed and verified.'),
                'legal_assertions' => [
                    'identity_verified' => true,
                ],
                'recorded_at' => now(),
            ]);
        }

        Log::channel('audit')->info('Notary identity verification approved', [
            'notary_request_id' => $request->id,
            'notary_identity_verification_id' => $record->id,
            'admin_user_id' => $admin->id,
        ]);

        return $request->fresh();
    }

    public function rejectPendingRecord(User $admin, NotaryIdentityVerification $record, string $reason): NotaryRequest
    {
        if ($record->verification_status !== NotaryIdentityVerificationStatus::Pending) {
            throw new RuntimeException(__('Only pending identity reviews can be rejected.'));
        }

        if (trim($reason) === '') {
            throw new RuntimeException(__('A rejection reason is required.'));
        }

        $request = $record->notaryRequest()->firstOrFail();

        $record->forceFill([
            'verification_status' => NotaryIdentityVerificationStatus::Rejected,
            'verified_by' => $admin->id,
            'verified_at' => now(),
            'rejection_reason' => trim($reason),
        ])->save();

        NotaryJournal::query()->create([
            'notary_request_id' => $request->id,
            'notary_user_id' => $request->notary_user_id,
            'entry_type' => 'identity_verification_rejected',
            'summary' => trim($reason),
            'legal_assertions' => [
                'notary_identity_verification_id' => $record->id,
                'reviewer_id' => $admin->id,
            ],
            'recorded_at' => now(),
        ]);

        Log::channel('audit')->warning('Notary identity verification rejected', [
            'notary_request_id' => $request->id,
            'notary_identity_verification_id' => $record->id,
            'admin_user_id' => $admin->id,
        ]);

        return $request->fresh();
    }

    private function allSignersHaveApprovedIdentity(NotaryRequest $request): bool
    {
        $signers = $request->signers()->get();
        if ($signers->isEmpty()) {
            return true;
        }

        foreach ($signers as $signer) {
            $hasVerified = $signer->identityVerifications()
                ->where('verification_status', NotaryIdentityVerificationStatus::Verified)
                ->exists();

            if (! $hasVerified) {
                return false;
            }
        }

        return true;
    }
}
