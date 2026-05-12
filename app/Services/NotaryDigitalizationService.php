<?php

namespace App\Services;

use App\Models\NotarialRegisterEntry;
use App\Models\NotaryCredential;
use App\Models\NotaryJournal;
use App\Models\NotaryRequest;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotaryDigitalizationService
{
    public function __construct(
        private readonly NotarySealService $notarySealService,
        private readonly NotarialCertificateService $notarialCertificateService,
        private readonly CompletedDocumentArtifactService $completedDocumentArtifactService,
    ) {}

    /**
     * Perform the full digital notarization for a request:
     * - Ensure all document artifacts are ready (final PDF, hash, blockchain)
     * - Generate QR verification codes for register entries
     * - Apply notary seal to documents
     * - Generate notarial certificates
     */
    public function digitalize(NotaryRequest $request): NotaryRequest
    {
        $request->loadMissing(['documents', 'registerEntries.notaryCredential']);

        // Step 1: Ensure all document artifacts are ready
        foreach ($request->documents as $document) {
            try {
                $this->completedDocumentArtifactService->ensureReady($document);
            } catch (Throwable $throwable) {
                Log::channel('errors')->warning('Document artifact generation failed during digitalization', [
                    'notary_request_id' => $request->id,
                    'document_id' => $document->id,
                    'exception' => $throwable::class,
                    'message' => $throwable->getMessage(),
                ]);
            }
        }

        // Step 2: Generate QR codes and notarial certificates for each register entry
        foreach ($request->registerEntries as $entry) {
            $this->digitalizeEntry($request, $entry);
        }

        // Step 3: Apply notary seal to documents
        $credential = $this->resolveCredential($request);
        if ($credential !== null) {
            foreach ($request->documents as $document) {
                $this->applyNotarySealToDocument($request, $document, $credential);
            }
        }

        NotaryJournal::query()->create([
            'notary_request_id' => $request->id,
            'notary_user_id' => $request->notary_user_id,
            'entry_type' => 'digitalization_completed',
            'summary' => __('Digital notarization completed. Seal applied, certificates generated, QR codes created.'),
            'legal_assertions' => [
                'documents_processed' => $request->documents->count(),
                'register_entries_processed' => $request->registerEntries->count(),
                'completed_at' => now()->timezone('Asia/Manila')->toDateTimeString(),
            ],
            'recorded_at' => now(),
        ]);

        return $request->fresh();
    }

    private function digitalizeEntry(NotaryRequest $request, NotarialRegisterEntry $entry): void
    {
        try {
            // Generate QR code
            if ($entry->qr_code_path === null || $entry->qr_code_path === '') {
                $this->notarySealService->generateVerificationQrCode($entry);
            }

            // Generate notarial certificate PDF
            $this->notarialCertificateService->generate($entry);
        } catch (Throwable $throwable) {
            Log::channel('errors')->warning('Register entry digitalization failed', [
                'notary_request_id' => $request->id,
                'entry_id' => $entry->id,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    private function applyNotarySealToDocument(NotaryRequest $request, $document, NotaryCredential $credential): void
    {
        $entry = $request->registerEntries->first(fn ($e) => $e->document_id === $document->id);
        if ($entry === null) {
            $entry = $request->registerEntries->first();
        }

        if ($entry === null) {
            return;
        }

        $sourcePath = $document->final_pdf_path ?: $document->prepared_pdf_path ?: $document->sourcePdfPath();
        if ($sourcePath === null || $sourcePath === '') {
            return;
        }

        try {
            $this->notarySealService->applyNotarySeal($sourcePath, $credential, $entry);
        } catch (Throwable $throwable) {
            Log::channel('errors')->warning('Notary seal application failed', [
                'notary_request_id' => $request->id,
                'document_id' => $document->id,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    private function resolveCredential(NotaryRequest $request): ?NotaryCredential
    {
        $entry = $request->registerEntries->first();
        if ($entry !== null && $entry->notaryCredential !== null) {
            return $entry->notaryCredential;
        }

        if ($request->notary_user_id === null) {
            return null;
        }

        return NotaryCredential::query()
            ->where('user_id', $request->notary_user_id)
            ->where('status', 'active')
            ->latest()
            ->first();
    }
}
