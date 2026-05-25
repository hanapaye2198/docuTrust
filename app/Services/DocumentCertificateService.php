<?php

namespace App\Services;

use App\Models\Document;
use Barryvdh\DomPDF\Facade\Pdf;

class DocumentCertificateService
{
    public function __construct(
        private readonly DocumentStorageService $documentStorageService,
    ) {}

    public function createForCompletedDocument(Document $document): ?string
    {
        if ($document->certificate_path !== null && $document->certificate_path !== '') {
            return $document->certificate_path;
        }

        $document->loadMissing(['documentSigners', 'documentHash']);

        if ($document->documentHash === null) {
            return null;
        }

        $pdf = Pdf::loadView('certificates.completion', [
            'document' => $document,
            'completedAt' => $document->documentHash->created_at,
            'hash' => $document->documentHash->hash,
            'signers' => $document->documentSigners,
        ])->setPaper('a4');

        $path = $this->documentStorageService->storeCertificatePdf($pdf->output());

        $document->update(['certificate_path' => $path]);

        return $path;
    }
}
