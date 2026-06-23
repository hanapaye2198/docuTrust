<?php

namespace App\Services;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Enums\SignatureFieldType;
use App\Models\DocumentSigner;

class SignerSessionPayloadService
{
    public function __construct(
        private readonly DocumentSigningWorkflowService $documentSigningWorkflowService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(DocumentSigner $signer): array
    {
        $signer->loadMissing([
            'document.signatureFields',
            'document.documentSigners',
            'signatures' => fn ($query) => $query->whereNotNull('signature_field_id'),
        ]);

        $document = $signer->document;
        $assignedCount = $document->signatureFields
            ->where('signer_id', $signer->id)
            ->reject(fn ($field): bool => $field->type === SignatureFieldType::Seal)
            ->count();
        $signedCount = $signer->signatures->count();
        $signingAvailabilityMessage = $this->documentSigningWorkflowService->canSignerModifyFields($document, $signer);
        $documentHasSignatureFields = $document->signatureFields->isNotEmpty();
        $canTakeAction = $signer->status === DocumentSignerStatus::Pending
            && $document->status === DocumentStatus::Pending
            && $signingAvailabilityMessage === null
            && (
                $assignedCount > 0
                || (! $documentHasSignatureFields && ! $signer->isRecipient())
            );

        return [
            'signer_status' => $signer->status->value,
            'document_status' => $document->status->value,
            'can_take_action' => $canTakeAction,
            'redirect_url' => $signer->status->isCompleted()
                ? route('sign.show', $signer->access_token ?? $signer->id)
                : null,
            'summary' => [
                'assigned' => $assignedCount,
                'completed' => $signedCount,
                'remaining' => max(0, $assignedCount - $signedCount),
                'progress_percent' => $assignedCount > 0
                    ? (int) round(($signedCount / $assignedCount) * 100)
                    : 0,
                'can_edit_fields' => $canTakeAction && $assignedCount > 0,
                'signer_status' => $signer->status->value,
                'document_status' => $document->status->value,
            ],
        ];
    }
}
