<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\CompletedDocumentArtifactService;
use App\Services\DocumentPdfStampingService;
use App\Support\PublicPdfStream;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentStreamController extends Controller
{
    public function __construct(
        private readonly CompletedDocumentArtifactService $completedDocumentArtifactService,
        private readonly DocumentPdfStampingService $documentPdfStampingService,
    ) {}

    public function __invoke(Request $request, Document $document): StreamedResponse
    {
        $this->authorize('view', $document);

        if ($request->boolean('signed_preview')) {
            $signedPreviewPath = $this->documentPdfStampingService->generateSignedPreviewPdf($document);

            if (is_string($signedPreviewPath) && $signedPreviewPath !== '') {
                return PublicPdfStream::inlineResponse($signedPreviewPath);
            }
        }

        if (! $request->boolean('source')) {
            $document = $this->completedDocumentArtifactService->ensureReady($document);
        }

        $streamSource = $request->boolean('source');
        $path = $streamSource
            ? $document->sourcePdfPath()
            : $document->previewPdfPath();
        $disk = $streamSource
            ? (string) config('filesystems.docutrust_disk', 'local')
            : $document->previewPdfDisk();

        $response = PublicPdfStream::inlineResponse($path, $disk);

        if (! $streamSource && $document->status === DocumentStatus::Completed) {
            $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }
}
