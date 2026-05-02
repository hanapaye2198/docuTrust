<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\DocumentCertificateService;
use App\Services\DocumentHashService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateCertificateJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $documentId) {}

    /**
     * Execute the job.
     */
    public function handle(
        DocumentHashService $documentHashService,
        DocumentCertificateService $documentCertificateService,
    ): void
    {
        try {
            $document = Document::query()->find($this->documentId);
            if ($document === null) {
                return;
            }

            $completedDocument = $document->fresh();
            if ($completedDocument === null) {
                return;
            }

            $documentHashService->createForCompletedDocument($completedDocument);
            $documentCertificateService->createForCompletedDocument($completedDocument->fresh());
        } catch (Throwable $throwable) {
            Log::channel('errors')->error('Queued certificate generation failed', [
                'document_id' => $this->documentId,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);
        }
    }
}
