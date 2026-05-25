<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\DocumentStorageService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentCertificateController extends Controller
{
    public function __construct(
        private readonly DocumentStorageService $documentStorageService,
    ) {}

    public function show(Document $document): StreamedResponse
    {
        $this->authorize('view', $document);

        abort_if($document->certificate_path === null, 404);

        return $this->documentStorageService->certificateResponse(
            $document->certificate_path,
            $document->title.'-certificate.pdf'
        );
    }

    public function download(Document $document): StreamedResponse
    {
        $this->authorize('view', $document);

        abort_if($document->certificate_path === null, 404);

        return $this->documentStorageService->certificateDownload(
            $document->certificate_path,
            $document->title.'-certificate.pdf'
        );
    }
}
