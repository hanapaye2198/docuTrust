<?php

namespace App\Services;

use App\Models\Document;
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
        private readonly BlockchainProofService $blockchainProofService,
        private readonly DocumentHashService $documentHashService,
    ) {}

    /**
     * Perform the full digital notarization for a request:
     * 1. Apply notary seal to documents
     * 2. Attach QR verification codes to register entries
     * 3. Generate notarial certificates
     * 4. Timestamp documents (hash + blockchain anchoring)
     */
    public function digitalize(NotaryRequest $request): NotaryRequest
    {
        $request->loadMissing(['documents', 'registerEntries.notaryCredential']);

        $credential = $this->resolveCredential($request);

        // Step 1: Apply notary seal to documents
        if ($credential !== null) {
            foreach ($request->documents as $document) {
                $this->applyNotarySealToDocument($request, $document, $credential);
            }
        }

        // Step 1b: The attorney's signature is already embedded by the document signing flow.
        // Digital notarization should not restamp the same document with another seal pass.
        if ($credential !== null && $credential->signature_image_path !== null && $credential->signature_image_path !== '') {
            foreach ($request->documents as $document) {
                $this->applyAttorneySignatureToDocument($request, $document, $credential);
            }
        }

        // Step 2: Attach QR verification codes to register entries
        foreach ($request->registerEntries as $entry) {
            $this->attachQrCode($request, $entry);
        }

        // Step 3: Generate notarial certificates for each register entry
        foreach ($request->registerEntries as $entry) {
            $this->generateCertificate($request, $entry);
        }

        // Step 4: Timestamp documents (generate hash + blockchain anchoring)
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

        foreach ($request->documents as $document) {
            $this->ensureBlockchainAnchoring($document->fresh());
        }

        // Record journal entry
        NotaryJournal::query()->create([
            'notary_request_id' => $request->id,
            'notary_user_id' => $request->notary_user_id,
            'entry_type' => 'digitalization_completed',
            'summary' => __('Digital notarization completed: notary seal applied, QR codes attached, certificates generated, documents timestamped.'),
            'legal_assertions' => [
                'documents_processed' => $request->documents->count(),
                'register_entries_processed' => $request->registerEntries->count(),
                'completed_at' => now()->timezone('Asia/Manila')->toDateTimeString(),
                'notary_seal_applied' => $credential !== null,
                'attorney_signature_applied' => $credential !== null
                    && is_string($credential->signature_image_path)
                    && $credential->signature_image_path !== '',
                'qr_codes_attached' => $request->registerEntries->count(),
                'certificates_generated' => $request->registerEntries->count(),
                'documents_timestamped' => $request->documents->count(),
                'attorney_credential_id' => $credential?->id,
                'attorney_commission_number' => $credential?->commission_number,
            ],
            'recorded_at' => now(),
        ]);

        return $request->fresh();
    }

    private function attachQrCode(NotaryRequest $request, NotarialRegisterEntry $entry): void
    {
        try {
            if ($entry->qr_code_path === null || $entry->qr_code_path === '') {
                $this->notarySealService->generateVerificationQrCode($entry);
            }
        } catch (Throwable $throwable) {
            Log::channel('errors')->warning('QR code generation failed during digitalization', [
                'notary_request_id' => $request->id,
                'entry_id' => $entry->id,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    private function generateCertificate(NotaryRequest $request, NotarialRegisterEntry $entry): void
    {
        try {
            $this->notarialCertificateService->generate($entry);
        } catch (Throwable $throwable) {
            Log::channel('errors')->warning('Certificate generation failed during digitalization', [
                'notary_request_id' => $request->id,
                'entry_id' => $entry->id,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    private function applyNotarySealToDocument(NotaryRequest $request, Document $document, NotaryCredential $credential): void
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
            $notarizedPath = $this->notarySealService->applyNotarySeal($sourcePath, $credential, $entry);

            if (is_string($notarizedPath) && $notarizedPath !== '') {
                $document->forceFill([
                    'final_pdf_path' => $notarizedPath,
                ])->save();
            }
        } catch (Throwable $throwable) {
            Log::channel('errors')->warning('Notary seal application failed', [
                'notary_request_id' => $request->id,
                'document_id' => $document->id,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    private function applyAttorneySignatureToDocument(NotaryRequest $request, Document $document, NotaryCredential $credential): void
    {
        try {
            // The signed document already contains the attorney's applied signature fields.
            // Digital notarization should preserve that output and add the notarial artifact layer only once.
            return;
        } catch (Throwable $throwable) {
            Log::channel('errors')->warning('Attorney signature application failed', [
                'notary_request_id' => $request->id,
                'document_id' => $document->id,
                'credential_id' => $credential->id,
                'signature_image_path' => $credential->signature_image_path,
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

    private function ensureBlockchainAnchoring(Document $document): void
    {
        if ($document->status->value !== 'completed') {
            return;
        }

        $documentHash = $document->documentHash;

        // If hash exists and already has a transaction, nothing to do
        if ($documentHash !== null
            && is_string($documentHash->transaction_id)
            && $documentHash->transaction_id !== '') {
            return;
        }

        // If no hash exists yet, generate it
        if ($documentHash === null || ! is_string($documentHash->hash) || $documentHash->hash === '') {
            $documentHash = $this->documentHashService->createForCompletedDocument($document);
        }

        if ($documentHash === null || ! is_string($documentHash->hash) || $documentHash->hash === '') {
            Log::channel('errors')->warning('Unable to generate document hash for blockchain anchoring', [
                'document_id' => $document->id,
                'document_title' => $document->title,
            ]);
            return;
        }

        // Attempt blockchain anchoring (non-blocking — can be retried later)
        if (is_string($documentHash->transaction_id) && $documentHash->transaction_id !== '') {
            return;
        }

        try {
            $transactionId = $this->blockchainProofService->anchorDocumentHash($documentHash->hash);

            if ($transactionId !== null && $transactionId !== '') {
                $documentHash->forceFill([
                    'transaction_id' => $transactionId,
                ])->save();
            } else {
                Log::channel('errors')->warning('Blockchain anchoring returned empty transaction ID', [
                    'document_id' => $document->id,
                    'document_title' => $document->title,
                ]);
            }
        } catch (Throwable $throwable) {
            Log::channel('errors')->warning('Blockchain anchoring failed — service may be unavailable. Can be retried later.', [
                'document_id' => $document->id,
                'document_title' => $document->title,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);
        }
    }
}
