<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Support\PublicPdfStream;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentStreamController extends Controller
{
    public function __invoke(Request $request, Document $document): StreamedResponse
    {
        $this->authorize('view', $document);

        $path = $request->boolean('source')
            ? $document->sourcePdfPath()
            : $document->previewPdfPath();

        return PublicPdfStream::inlineResponse($path);
    }
}
