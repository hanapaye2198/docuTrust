<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\NotaryRequestStatus;
use App\Events\NotaryRequestApproved;
use App\Events\NotaryRequestNotarized;
use App\Events\NotaryRequestSubmitted;
use App\Models\Document;
use App\Models\NotaryJournal;
use App\Models\NotaryRequest;
use RuntimeException;

class NotaryRequestWorkflowService
{
    /**
     * @return array{
     *   ready: bool,
     *   issues: list<string>,
     *   documents: array<int, array{
     *     document_id: int,
     *     title: string,
     *     completed: bool,
     *     has_final_pdf: bool,
     *     has_certificate: bool,
     *     has_document_hash: bool,
     *     has_blockchain_transaction: bool,
     *     issues: list<string>
     *   }>
     * }
     */
    public function finalizationReadiness(NotaryRequest $request): array
    {
        $request->loadMissing(['documents.documentHash', 'registerEntries']);

        $issues = [];
        $documents = [];

        if ($request->documents->isEmpty()) {
            $issues[] = __('Attach at least one document before finalizing notarization.');
        }

        if ($request->registerEntries->isEmpty()) {
            $issues[] = __('Create at least one notarial register entry before finalizing.');
        }

        foreach ($request->documents as $document) {
            $documentIssues = [];
            $completed = $document->status === DocumentStatus::Completed;
            $hasFinalPdf = is_string($document->final_pdf_path) && $document->final_pdf_path !== '';
            $hasCertificate = is_string($document->certificate_path) && $document->certificate_path !== '';
            $hasDocumentHash = $document->documentHash !== null && is_string($document->documentHash->hash) && $document->documentHash->hash !== '';
            $hasBlockchainTransaction = $document->documentHash !== null
                && is_string($document->documentHash->transaction_id)
                && $document->documentHash->transaction_id !== '';

            if (! $completed) {
                $documentIssues[] = __('Document is not completed.');
            }

            if (! $hasFinalPdf) {
                $documentIssues[] = __('Final signed PDF has not been generated.');
            }

            if (! $hasCertificate) {
                $documentIssues[] = __('Completion certificate has not been generated.');
            }

            if (! $hasDocumentHash) {
                $documentIssues[] = __('Document hash has not been recorded.');
            }

            if (! $hasBlockchainTransaction) {
                $documentIssues[] = __('Blockchain transaction is missing.');
            }

            if ($documentIssues !== []) {
                $issues[] = __('Document ":title" is not ready for notarization finalization.', [
                    'title' => $document->title,
                ]);
            }

            $documents[] = [
                'document_id' => (int) $document->id,
                'title' => (string) $document->title,
                'completed' => $completed,
                'has_final_pdf' => $hasFinalPdf,
                'has_certificate' => $hasCertificate,
                'has_document_hash' => $hasDocumentHash,
                'has_blockchain_transaction' => $hasBlockchainTransaction,
                'issues' => $documentIssues,
            ];
        }

        return [
            'ready' => $issues === [],
            'issues' => $issues,
            'documents' => $documents,
        ];
    }

    public function submit(NotaryRequest $request): NotaryRequest
    {
        if ($request->status !== NotaryRequestStatus::Draft) {
            throw new RuntimeException(__('Only draft notary requests can be submitted.'));
        }

        $request->markSubmitted();

        event(new NotaryRequestSubmitted($request));

        return $request->fresh();
    }

    public function approve(NotaryRequest $request, array $legalAssertions = [], ?string $summary = null): NotaryRequest
    {
        if (! in_array($request->status, [
            NotaryRequestStatus::SessionScheduled,
            NotaryRequestStatus::SessionInProgress,
            NotaryRequestStatus::LocationVerified,
            NotaryRequestStatus::IdentityVerified,
        ], true)) {
            throw new RuntimeException(__('This notary request is not ready for attorney approval.'));
        }

        $request->markApproved();

        NotaryJournal::query()->create([
            'notary_request_id' => $request->id,
            'notary_user_id' => $request->notary_user_id,
            'entry_type' => 'approval',
            'summary' => $summary ?: __('Attorney approved the notary request.'),
            'legal_assertions' => $legalAssertions,
            'recorded_at' => now(),
        ]);

        event(new NotaryRequestApproved($request));

        return $request->fresh();
    }

    public function reject(NotaryRequest $request, string $reason, array $legalAssertions = []): NotaryRequest
    {
        if ($reason === '') {
            throw new RuntimeException(__('A rejection reason is required.'));
        }

        if (in_array($request->status, [
            NotaryRequestStatus::Notarized,
            NotaryRequestStatus::Rejected,
            NotaryRequestStatus::Failed,
        ], true)) {
            throw new RuntimeException(__('This notary request cannot be rejected in its current state.'));
        }

        $request->markRejected($reason);

        NotaryJournal::query()->create([
            'notary_request_id' => $request->id,
            'notary_user_id' => $request->notary_user_id,
            'entry_type' => 'rejection',
            'summary' => $reason,
            'legal_assertions' => $legalAssertions,
            'recorded_at' => now(),
        ]);

        return $request->fresh();
    }

    public function finalize(NotaryRequest $request): NotaryRequest
    {
        if ($request->status !== NotaryRequestStatus::AttorneyApproved) {
            throw new RuntimeException(__('Attorney approval is required before notarization can be finalized.'));
        }

        $readiness = $this->finalizationReadiness($request);
        if (! $readiness['ready']) {
            throw new RuntimeException($readiness['issues'][0] ?? __('This notary request is not ready for finalization.'));
        }

        $request->markNotarized();

        event(new NotaryRequestNotarized($request));

        return $request->fresh();
    }

    public function attachDocument(NotaryRequest $request, Document $document): NotaryRequest
    {
        if ($request->organization_id === null || $document->organization_id === null || $request->organization_id !== $document->organization_id) {
            throw new RuntimeException(__('The selected document does not belong to this organization.'));
        }

        if ($document->notary_request_id !== null && $document->notary_request_id !== $request->id) {
            throw new RuntimeException(__('The selected document is already linked to another notary request.'));
        }

        if ($request->status === NotaryRequestStatus::Notarized) {
            throw new RuntimeException(__('You cannot attach documents to a finalized notary request.'));
        }

        $document->update([
            'notary_request_id' => $request->id,
        ]);

        NotaryJournal::query()->create([
            'notary_request_id' => $request->id,
            'notary_user_id' => $request->notary_user_id,
            'entry_type' => 'document_attached',
            'summary' => __('Linked document ":title" to this notary request.', ['title' => $document->title]),
            'legal_assertions' => [
                'document_id' => $document->id,
            ],
            'recorded_at' => now(),
        ]);

        return $request->fresh(['documents', 'journals']);
    }

    public function detachDocument(NotaryRequest $request, Document $document): NotaryRequest
    {
        if ($document->notary_request_id !== $request->id) {
            throw new RuntimeException(__('The selected document is not linked to this notary request.'));
        }

        if ($request->status === NotaryRequestStatus::Notarized) {
            throw new RuntimeException(__('You cannot detach documents from a finalized notary request.'));
        }

        $document->update([
            'notary_request_id' => null,
        ]);

        NotaryJournal::query()->create([
            'notary_request_id' => $request->id,
            'notary_user_id' => $request->notary_user_id,
            'entry_type' => 'document_detached',
            'summary' => __('Removed document ":title" from this notary request.', ['title' => $document->title]),
            'legal_assertions' => [
                'document_id' => $document->id,
            ],
            'recorded_at' => now(),
        ]);

        return $request->fresh(['documents', 'journals']);
    }

}
