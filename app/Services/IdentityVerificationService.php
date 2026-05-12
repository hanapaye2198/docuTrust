<?php

namespace App\Services;

use App\Enums\NotaryRequestStatus;
use App\Models\NotaryJournal;
use App\Models\NotaryRequest;
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

        return $request->fresh();
    }
}
