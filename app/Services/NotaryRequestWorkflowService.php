<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\NotaryRequestStatus;
use App\Enums\TemplateRoleType;
use App\Events\NotaryRequestApproved;
use App\Events\NotaryRequestNotarized;
use App\Events\NotaryRequestSubmitted;
use App\Models\DocumentSigner;
use App\Models\Document;
use App\Models\NotaryJournal;
use App\Models\NotaryRequest;
use RuntimeException;

class NotaryRequestWorkflowService
{
    public function canScheduleSession(NotaryRequest $request): bool
    {
        return $request->status === NotaryRequestStatus::LocationVerified
            && $this->documentsReadyForSession($request);
    }

    public function hasCompletedSession(NotaryRequest $request): bool
    {
        $request->loadMissing('sessions');

        return $request->sessions->contains(fn ($session): bool => $session->status === 'completed');
    }

    public function canBeginAttorneySigning(NotaryRequest $request): bool
    {
        if (! $this->hasCompletedSession($request)) {
            return false;
        }

        return $this->documentsReadyForSession($request);
    }

    public function hasAttorneySignedAllDocuments(NotaryRequest $request): bool
    {
        $request->loadMissing('documents.documentSigners');

        if ($request->documents->isEmpty() || $request->notary_user_id === null) {
            return false;
        }

        return $request->documents->every(function (Document $document) use ($request): bool {
            return $document->documentSigners->contains(
                fn (DocumentSigner $signer): bool =>
                    (int) $signer->user_id === (int) $request->notary_user_id
                    && $signer->role_type === TemplateRoleType::Signer
                    && $signer->status->isCompleted()
            );
        });
    }

    public function canCreateRegisterEntry(NotaryRequest $request): bool
    {
        if ($this->hasAttorneySignedAllDocuments($request)) {
            return true;
        }

        return in_array($request->status, [
            NotaryRequestStatus::AttorneyApproved,
            NotaryRequestStatus::Digitalized,
            NotaryRequestStatus::Notarized,
        ], true);
    }

    public function canApprove(NotaryRequest $request): bool
    {
        if (! $this->hasCompletedSession($request)) {
            return false;
        }

        if (! $this->hasAttorneySignedAllDocuments($request)) {
            return false;
        }

        $request->loadMissing('registerEntries');

        return $request->registerEntries->isNotEmpty();
    }

    public function canDigitalize(NotaryRequest $request): bool
    {
        if (in_array($request->status, [NotaryRequestStatus::Digitalized, NotaryRequestStatus::Notarized], true)) {
            return false;
        }

        if (! $this->hasAttorneySignedAllDocuments($request)) {
            return false;
        }

        if (! $this->canCreateRegisterEntry($request)) {
            return false;
        }

        $request->loadMissing('documents');

        return $request->documents->isNotEmpty()
            && $request->documents->every(fn (Document $document): bool => $document->status === DocumentStatus::Completed);
    }

    public function beginAttorneySigning(NotaryRequest $request): NotaryRequest
    {
        if (! $this->canBeginAttorneySigning($request)) {
            throw new RuntimeException(__('Attorney signing can begin only after signer completion and the completed verification session.'));
        }

        if ($request->status !== NotaryRequestStatus::AttorneySigning) {
            $request->markAttorneySigning();
        }

        return $request->fresh();
    }

    private function documentsReadyForSession(NotaryRequest $request): bool
    {
        $request->loadMissing(['documents.documentSigners']);

        if ($request->documents->isEmpty()) {
            return false;
        }

        return $request->documents->every(function (Document $document) use ($request): bool {
            if (! in_array($document->status, [DocumentStatus::Pending, DocumentStatus::Completed], true)) {
                return false;
            }

            return $document->documentSigners
                ->filter(function (DocumentSigner $signer) use ($request): bool {
                    if (! $signer->requiresAction()) {
                        return false;
                    }

                    return (int) $signer->user_id !== (int) $request->notary_user_id;
                })
                ->every(fn (DocumentSigner $signer): bool => $signer->status->isCompleted());
        });
    }

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

            // Blockchain anchoring is optional — service may be unavailable
            // NotaryAdmin can retry blockchain anchoring later
            if (! $hasBlockchainTransaction) {
                // Not a blocking issue — just a warning
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
        if (! $this->canApprove($request)) {
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
        if ($request->status !== NotaryRequestStatus::Digitalized) {
            throw new RuntimeException(__('Digital notarization is required before notarization can be finalized.'));
        }

        $readiness = $this->finalizationReadiness($request);
        if (! $readiness['ready']) {
            throw new RuntimeException($readiness['issues'][0] ?? __('This notary request is not ready for finalization.'));
        }

        $request->markNotarized();

        event(new NotaryRequestNotarized($request));

        return $request->fresh();
    }

    public function digitalize(NotaryRequest $request): NotaryRequest
    {
        if (! $this->canDigitalize($request)) {
            throw new RuntimeException(__('This notary request is not ready for digital notarization.'));
        }

        if ($request->status !== NotaryRequestStatus::AttorneyApproved) {
            $request = $this->approve($request->fresh(), [
                'identity_matched' => true,
                'voluntary_consent' => true,
                'jurisdiction_valid' => true,
                'digital_notarization_ready' => true,
            ], __('Attorney completed signing and approved this request for digital notarization.'));
        }

        app(NotaryDigitalizationService::class)->digitalize($request->fresh());

        $request->fresh()->markDigitalized();

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

        app(NotaryParticipantSyncService::class)->syncRequestSignersToDocument($document);

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

    public function cancel(NotaryRequest $request, string $reason = ''): NotaryRequest
    {
        if (in_array($request->status, [
            NotaryRequestStatus::Notarized,
            NotaryRequestStatus::Digitalized,
            NotaryRequestStatus::Rejected,
            NotaryRequestStatus::Failed,
            NotaryRequestStatus::Cancelled,
        ], true)) {
            throw new RuntimeException(__('This notary request cannot be cancelled in its current state.'));
        }

        $request->markCancelled();

        NotaryJournal::query()->create([
            'notary_request_id' => $request->id,
            'notary_user_id' => $request->notary_user_id,
            'entry_type' => 'request_cancelled',
            'summary' => $reason !== '' ? $reason : __('Notary request was cancelled.'),
            'legal_assertions' => [],
            'recorded_at' => now(),
        ]);

        return $request->fresh();
    }
}
