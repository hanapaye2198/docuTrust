<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\DocumentPdfStampingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateDocumentPdfJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $documentId,
        public string $mode,
    ) {}

    public function handle(DocumentPdfStampingService $documentPdfStampingService): void
    {
        try {
            $document = Document::query()->find($this->documentId);
            if ($document === null) {
                return;
            }

            if ($this->mode === 'final') {
                $documentPdfStampingService->generateFinalPdf($document);

                return;
            }

            $documentPdfStampingService->generatePreparedPdf($document);
        } catch (Throwable $throwable) {
            Log::channel('errors')->error('Queued PDF generation failed', [
                'document_id' => $this->documentId,
                'mode' => $this->mode,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);
        }
    }
}
