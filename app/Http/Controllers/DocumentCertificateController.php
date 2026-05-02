<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentCertificateController extends Controller
{
    public function show(Document $document): StreamedResponse
    {
        $this->authorize('view', $document);

        abort_if($document->certificate_path === null, 404);

        return Storage::disk('local')->response(
            $document->certificate_path,
            $document->title.'-certificate.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    public function download(Document $document): StreamedResponse
    {
        $this->authorize('view', $document);

        abort_if($document->certificate_path === null, 404);

        return Storage::disk('local')->download(
            $document->certificate_path,
            $document->title.'-certificate.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }
}
