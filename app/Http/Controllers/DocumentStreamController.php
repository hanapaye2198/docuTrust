<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Support\PublicPdfStream;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentStreamController extends Controller
{
    public function __invoke(Document $document): StreamedResponse
    {
        $this->authorize('view', $document);

        $path = $document->previewPdfPath();

        return PublicPdfStream::inlineResponse($path);
    }
}
