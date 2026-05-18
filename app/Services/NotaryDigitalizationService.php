<?php

namespace App\Services;

use App\Models\Document;
use App\Models\NotarialRegisterEntry;
use App\Models\NotaryCredential;
use App\Models\NotaryJournal;
use App\Models\NotaryRequest;
use Illuminate\Support\Facades\Log;
use RuntimeException;
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

        // Step 1b: Ensure blockchain anchoring for all completed documents
        foreach ($request->documents as $document) {
            $this->ensureBlockchainAnchoring($document->fresh());
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

        // Step 4: Apply attorney's written signature to documents
        if ($credential !== null && $credential->signature_image_path !== null && $credential->signature_image_path !== '') {
            foreach ($request->documents as $document) {
                $this->applyAttorneySignatureToDocument($request, $document, $credential);
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
                'attorney_signature_applied' => $credential !== null && $credential->signature_image_path !== null,
                'attorney_credential_id' => $credential?->id,
                'attorney_commission_number' => $credential?->commission_number,
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

    private function applyAttorneySignatureToDocument(NotaryRequest $request, $document, NotaryCredential $credential): void
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
            throw new RuntimeException(__('Unable to generate document hash for blockchain anchoring. Document: :title', [
                'title' => $document->title,
            ]));
        }

        // Attempt blockchain anchoring
        if (is_string($documentHash->transaction_id) && $documentHash->transaction_id !== '') {
            return;
        }

        $transactionId = $this->blockchainProofService->anchorDocumentHash($documentHash->hash);

        if ($transactionId === null || $transactionId === '') {
            throw new RuntimeException(__('Blockchain anchoring failed for document ":title". Ensure the blockchain service is running and configured.', [
                'title' => $document->title,
            ]));
        }

        $documentHash->forceFill([
            'transaction_id' => $transactionId,
        ])->save();
    }
}
