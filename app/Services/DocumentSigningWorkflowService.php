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

class DocumentSigningWorkflowService
{
    public function __construct(
        private readonly TrustAuthorizationSessionService $trustAuthorizationSessionService,
        private readonly SigningMethodService $signingMethodService,
    ) {}

    public function canSignerModifyFields(Document $document, DocumentSigner $signer): ?string
    {
        if ($signer->status === DocumentSignerStatus::Signed) {
            return __('You have already signed this document.');
        }

        if (in_array($document->status, [DocumentStatus::Declined, DocumentStatus::Cancelled], true)) {
            return __('This document can no longer be signed.');
        }

        if ($document->status !== DocumentStatus::Pending) {
            return __('This document is not available for signing.');
        }

        return $this->canSignerSign($document, $signer);
    }

    public function completeLegacySigning(DocumentSigner $signer, string $ipAddress): void
    {
        $document = $signer->document;
        $this->ensureActiveTrustAuthorizationIfRequired($signer);

        $signer->update([
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);
        event(new DocumentSignerCompleted($document->fresh(), $signer->fresh()));

        $this->finalizeDocumentIfFullySigned($document->fresh()->load('documentSigners'), $ipAddress);
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

        $signer->update([
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);
        event(new DocumentSignerCompleted($document->fresh(), $signer->fresh()));

        $this->finalizeDocumentIfFullySigned($document->fresh()->load('documentSigners'), $ipAddress);
    }

    private function finalizeDocumentIfFullySigned(Document $document, string $ipAddress): void
    {
        if (! $document->allSignersHaveSigned()) {
            return;
        }

        $document->update(['status' => DocumentStatus::Completed]);
        GenerateDocumentPdfJob::dispatch($document->id, 'final');
        $completedDocument = $document->fresh();
        SignatureAuditLogger::documentCompleted($completedDocument, $ipAddress);
        GenerateCertificateJob::dispatch($completedDocument->id);
        event(new DocumentCompleted($completedDocument));
    }

    private function canSignerSign(Document $document, DocumentSigner $signer): ?string
    {
        if (! $this->usesSequentialSigning($document) || $signer->signing_order === null) {
            return null;
        }

        $blockingSigner = $this->pendingPrerequisiteSigner($document, $signer);

        if ($blockingSigner === null) {
            return null;
        }

        $blockingName = trim((string) $blockingSigner->name);
        $blockingLabel = $blockingName !== ''
            ? $blockingName
            : __('Signer #:order', ['order' => $blockingSigner->signing_order]);

        return __('You cannot sign yet. Waiting for signer :order (:name) to finish first.', [
            'order' => $blockingSigner->signing_order,
            'name' => $blockingLabel,
        ]);
    }

    private function usesSequentialSigning(Document $document): bool
    {
        return $document->usesSequentialSigningWorkflow();
    }

    private function pendingPrerequisiteSigner(Document $document, DocumentSigner $signer): ?DocumentSigner
    {
        /** @var ?DocumentSigner $blockingSigner */
        $blockingSigner = $document->documentSigners
            ->filter(function (DocumentSigner $otherSigner) use ($signer): bool {
                if ($otherSigner->id === $signer->id || $otherSigner->signing_order === null) {
                    return false;
                }

                if ($otherSigner->signing_order >= $signer->signing_order) {
                    return false;
                }

                return $otherSigner->status !== DocumentSignerStatus::Signed;
            })
            ->sortBy('signing_order')
            ->first();

        return $blockingSigner;
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
