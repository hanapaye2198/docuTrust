<?php

namespace App\Services;

use App\Models\Document;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentCertificateService
{
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

        $path = 'certificates/'.Str::uuid()->toString().'.pdf';
        Storage::disk('local')->put($path, $pdf->output());

        $document->update(['certificate_path' => $path]);

        return $path;
    }
}
