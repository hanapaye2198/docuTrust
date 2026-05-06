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

        if ($document->final_pdf_path !== null && $document->final_pdf_path !== '') {
            if ($document->certificate_path !== null && $document->certificate_path !== '') {
                return $document;
            }
        }

        $this->completedDocumentSealingService->seal($document);
        $document = $document->fresh() ?? $document;

        $this->documentCertificateService->createForCompletedDocument($document);
        $document = $document->fresh() ?? $document;

        $this->documentArchiveService->archiveCompletedDocument($document);

        return $document->fresh() ?? $document;
    }
}
