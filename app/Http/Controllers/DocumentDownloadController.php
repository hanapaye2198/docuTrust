<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\CompletedDocumentArtifactService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentDownloadController extends Controller
{
    public function __construct(
        private readonly CompletedDocumentArtifactService $completedDocumentArtifactService,
    ) {}

    public function __invoke(Document $document): StreamedResponse
    {
        $this->authorize('view', $document);

        $document = $this->completedDocumentArtifactService->ensureReady($document);

        $diskName = $document->hasArchivedFinalDocument()
            ? $document->archiveDisk()
            : (string) config('filesystems.docutrust_disk', 'local');

        $path = $document->finalDownloadPath();

        abort_if(! is_string($path) || $path === '', 404);

        return Storage::disk($diskName)->download(
            $path,
            $document->title.'-signed.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }
}
