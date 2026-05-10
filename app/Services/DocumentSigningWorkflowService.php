<?php

namespace App\Services;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Events\DocumentCompleted;
use App\Events\DocumentSignerCompleted;
use App\Jobs\GenerateCertificateJob;
use App\Jobs\GenerateDocumentPdfJob;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Trust\Authorization\TrustAuthorizationRequiredException;
use App\Trust\Authorization\TrustAuthorizationSessionService;
use Illuminate\Support\Carbon;

class DocumentSigningWorkflowService
{
    public function __construct(
        private readonly TrustAuthorizationSessionService $trustAuthorizationSessionService,
        private readonly SigningMethodService $signingMethodService,
    ) {}

    public function canSignerModifyFields(Document $document, DocumentSigner $signer): ?string
    {
        if ($signer->status->isCompleted()) {
            return $signer->isApprover()
                ? __('You have already approved this document.')
                : ($signer->isRecipient()
                    ? __('This participant does not take action on the document.')
                    : __('You have already signed this document.'));
        }

        if (in_array($document->status, [DocumentStatus::Declined, DocumentStatus::Cancelled], true)) {
            return __('This document can no longer be processed.');
        }

        if ($document->status !== DocumentStatus::Pending) {
            return __('This document is not available right now.');
        }

        if ($signer->isRecipient()) {
            return __('Recipients receive the completed document and do not take action during signing.');
        }

        return $this->canParticipantAct($document, $signer);
    }

    public function completeLegacySigning(DocumentSigner $signer, string $ipAddress): void
    {
        $document = $signer->document;
        if ($signer->isSigner()) {
            $this->ensureActiveTrustAuthorizationIfRequired($signer);
        }

        $signer->update($this->completedStatusPayload($signer));
        event(new DocumentSignerCompleted($document->fresh(), $signer->fresh()));

        $this->finalizeDocumentIfWorkflowComplete($document->fresh()->load('documentSigners'), $ipAddress);
    }

    public function completeSignerIfAllFieldsSigned(DocumentSigner $signer, Document $document, string $ipAddress): void
    {
        $signerHasFields = $document->signatureFields()
            ->where('signer_id', $signer->id)
            ->exists();

        if (! $signerHasFields) {
            return;
        }

        $hasUnsignedFields = $document->signatureFields()
            ->where('signer_id', $signer->id)
            ->whereDoesntHave('signature', function ($query) use ($signer): void {
                $query->where('signer_id', $signer->id);
            })
            ->exists();

        if ($hasUnsignedFields || $signer->status === DocumentSignerStatus::Signed) {
            return;
        }

        $signer->update($this->completedStatusPayload($signer));
        event(new DocumentSignerCompleted($document->fresh(), $signer->fresh()));

        $this->finalizeDocumentIfWorkflowComplete($document->fresh()->load('documentSigners'), $ipAddress);
    }

    private function finalizeDocumentIfWorkflowComplete(Document $document, string $ipAddress): void
    {
        if (! $document->allApproversHaveApproved() || ! $document->allSignersHaveSigned()) {
            return;
        }

        $document->update(['status' => DocumentStatus::Completed]);
        GenerateDocumentPdfJob::dispatch($document->id, 'final');
        $completedDocument = $document->fresh();
        SignatureAuditLogger::documentCompleted($completedDocument, $ipAddress);
        GenerateCertificateJob::dispatch($completedDocument->id);
        event(new DocumentCompleted($completedDocument));
    }

    private function canParticipantAct(Document $document, DocumentSigner $signer): ?string
    {
        if (! $this->usesSequentialSigning($document) || $signer->signing_order === null) {
            return $this->parallelWorkflowBlockingMessage($document, $signer);
        }

        $blockingSigner = $this->pendingPrerequisiteParticipant($document, $signer);

        if ($blockingSigner === null) {
            return null;
        }

        $blockingName = trim((string) $blockingSigner->name);
        $blockingLabel = $blockingName !== ''
            ? $blockingName
            : __('Participant #:order', ['order' => $blockingSigner->signing_order]);

        $blockingRoleLabel = $blockingSigner->isApprover()
            ? __('approver')
            : __('signer');

        return __($signer->isApprover()
            ? 'You cannot approve yet. Waiting for :role :order (:name) to finish first.'
            : 'You cannot sign yet. Waiting for :role :order (:name) to finish first.', [
                'role' => $blockingRoleLabel,
                'order' => $blockingSigner->signing_order,
                'name' => $blockingLabel,
            ]);
    }

    private function usesSequentialSigning(Document $document): bool
    {
        return $document->usesSequentialSigningWorkflow();
    }

    private function pendingPrerequisiteParticipant(Document $document, DocumentSigner $signer): ?DocumentSigner
    {
        /** @var ?DocumentSigner $blockingSigner */
        $blockingSigner = $document->documentSigners
            ->filter(function (DocumentSigner $otherSigner) use ($signer): bool {
                if ($otherSigner->id === $signer->id || $otherSigner->signing_order === null || ! $otherSigner->requiresAction()) {
                    return false;
                }

                if ($otherSigner->signing_order >= $signer->signing_order) {
                    return false;
                }

                return ! $otherSigner->status->isCompleted();
            })
            ->sortBy('signing_order')
            ->first();

        return $blockingSigner;
    }

    private function parallelWorkflowBlockingMessage(Document $document, DocumentSigner $signer): ?string
    {
        if (! $signer->isSigner()) {
            return null;
        }

        $pendingApprover = $document->documentSigners
            ->first(fn (DocumentSigner $participant): bool => $participant->isApprover() && ! $participant->status->isCompleted());

        if ($pendingApprover === null) {
            return null;
        }

        $blockingName = trim((string) $pendingApprover->name);

        return __('You cannot sign yet. Waiting for approver :name to approve first.', [
            'name' => $blockingName !== '' ? $blockingName : __('Approval participant'),
        ]);
    }

    /**
     * @return array{status: DocumentSignerStatus, signed_at: Carbon}
     */
    private function completedStatusPayload(DocumentSigner $signer): array
    {
        return [
            'status' => $signer->isApprover()
                ? DocumentSignerStatus::Approved
                : DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ];
    }

    private function ensureActiveTrustAuthorizationIfRequired(DocumentSigner $signer): void
    {
        if (! $this->signingMethodService->requiresTrustAuthorization($signer)) {
            return;
        }

        $providerName = trim((string) config('services.remote_signing.provider_name', 'remote_managed'));
        $authorization = $this->trustAuthorizationSessionService->activeForSigner($signer, $providerName);

        if ($authorization !== null) {
            return;
        }

        throw new TrustAuthorizationRequiredException(
            __('Start trust authorization before completing your assigned fields.')
        );
    }
}
