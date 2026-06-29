<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Models\Document;

class CompletedDocumentArtifactService
{
    public function __construct(
        private readonly CompletedDocumentSealingService $completedDocumentSealingService,
        private readonly DocumentCertificateService $documentCertificateService,
        private readonly DocumentArchiveService $documentArchiveService,
    ) {}

    public function ensureReady(Document $document): Document
    {
        $document = $document->fresh() ?? $document;

        if ($document->status !== DocumentStatus::Completed) {
            return $document;
        }

        if ($this->hasCompletedArtifacts($document) && ! $this->needsVerificationProofRefresh($document)) {
            return $document;
        }

        $this->completedDocumentSealingService->seal($document);
        $document = $document->fresh() ?? $document;

        if ($this->hasCompletedArtifacts($document)) {
            return $document;
        }

        $this->documentCertificateService->createForCompletedDocument($document);
        $document = $document->fresh() ?? $document;

        $this->documentArchiveService->archiveCompletedDocument($document);

        return $document->fresh() ?? $document;
    }

    private function hasCompletedArtifacts(Document $document): bool
    {
        return is_string($document->final_pdf_path)
            && $document->final_pdf_path !== ''
            && is_string($document->certificate_path)
            && $document->certificate_path !== '';
    }

    private function needsVerificationProofRefresh(Document $document): bool
    {
        return $document->documentHash === null
            || ! is_string($document->documentHash->hash)
            || $document->documentHash->hash === ''
            || ! is_string($document->documentHash->transaction_id)
            || $document->documentHash->transaction_id === '';
    }
}
