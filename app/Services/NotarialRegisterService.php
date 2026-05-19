<?php

namespace App\Services;

use App\Enums\NotaryRequestStatus;
use App\Models\Document;
use App\Models\NotarialRegisterEntry;
use App\Models\NotaryCredential;
use App\Models\NotaryJournal;
use App\Models\NotaryRequest;
use Illuminate\Support\Str;
use RuntimeException;

class NotarialRegisterService
{
    public function __construct(
        private readonly NotaryRequestWorkflowService $notaryRequestWorkflowService,
    ) {}

    /**
     * Create a new notarial register entry for a notary request.
     *
     * @param  array{
     *   document_title: string,
     *   document_description?: string|null,
     *   parties: array<int, array{name: string, address: string}>,
     *   witnesses?: array<int, array{name: string, address: string}>|null,
     *   competent_evidence: array<int, array{person_name: string, id_type: string, id_number: string}>,
     *   notarial_act_type: string,
     *   fees?: float|string|null,
     *   official_receipt_number?: string|null,
     *   page_number?: int|null,
     *   book_number?: string|null,
     * }  $data
     */
    public function createEntry(
        NotaryRequest $request,
        NotaryCredential $credential,
        array $data,
        ?Document $document = null,
    ): NotarialRegisterEntry {
        if (! $credential->isActive()) {
            throw new RuntimeException(__('Notary commission is expired or inactive.'));
        }

        if (! $this->notaryRequestWorkflowService->canCreateRegisterEntry($request)) {
            throw new RuntimeException(__('Register entries can only be created after the attorney has signed the document.'));
        }

        $documentTitle = trim((string) ($data['document_title'] ?? ''));
        if ($documentTitle === '') {
            throw new RuntimeException(__('Document title is required for the register entry.'));
        }

        $parties = $data['parties'] ?? [];
        if ($parties === []) {
            throw new RuntimeException(__('At least one party is required for the register entry.'));
        }

        $competentEvidence = $data['competent_evidence'] ?? [];
        if ($competentEvidence === []) {
            throw new RuntimeException(__('Competent evidence of identity is required for each party.'));
        }

        $notarialActType = trim((string) ($data['notarial_act_type'] ?? ''));
        if (! in_array($notarialActType, ['acknowledgment', 'jurat', 'affidavit', 'oath', 'other'], true)) {
            throw new RuntimeException(__('Invalid notarial act type.'));
        }

        $entryYear = (int) now()->format('Y');
        $entryNumber = $credential->nextEntryNumber();
        $verificationToken = Str::uuid()->toString();

        $entry = NotarialRegisterEntry::query()->create([
            'notary_request_id' => $request->id,
            'notary_credential_id' => $credential->id,
            'document_id' => $document?->id,
            'entry_number' => $entryNumber,
            'entry_year' => $entryYear,
            'page_number' => isset($data['page_number']) && is_numeric($data['page_number']) ? (int) $data['page_number'] : null,
            'book_number' => isset($data['book_number']) && trim((string) $data['book_number']) !== '' ? trim((string) $data['book_number']) : null,
            'document_title' => $documentTitle,
            'document_description' => $data['document_description'] ?? null,
            'parties' => $parties,
            'witnesses' => $data['witnesses'] ?? [],
            'competent_evidence' => $competentEvidence,
            'notarized_at' => now()->timezone('Asia/Manila'),
            'notarial_act_type' => $notarialActType,
            'fees' => (float) ($data['fees'] ?? 0),
            'official_receipt_number' => $data['official_receipt_number'] ?? null,
            'notary_signature_path' => $credential->signature_image_path,
            'qr_verification_token' => $verificationToken,
        ]);

        NotaryJournal::query()->create([
            'notary_request_id' => $request->id,
            'notary_user_id' => $credential->user_id,
            'entry_type' => 'register_entry_created',
            'summary' => __('Notarial register entry :number created for ":title" (:type).', [
                'number' => str_pad((string) $entryNumber, 3, '0', STR_PAD_LEFT),
                'title' => $documentTitle,
                'type' => $notarialActType,
            ]),
            'legal_assertions' => [
                'entry_number' => $entryNumber,
                'entry_year' => $entryYear,
                'notarial_act_type' => $notarialActType,
                'parties_count' => count($parties),
                'witnesses_count' => count($data['witnesses'] ?? []),
                'fees' => (float) ($data['fees'] ?? 0),
                'official_receipt_number' => $data['official_receipt_number'] ?? null,
                'verification_token' => $verificationToken,
            ],
            'recorded_at' => now(),
        ]);

        return $entry;
    }

    /**
     * Find a register entry by its public verification token.
     */
    public function findByVerificationToken(string $token): ?NotarialRegisterEntry
    {
        if ($token === '') {
            return null;
        }

        return NotarialRegisterEntry::query()
            ->where('qr_verification_token', $token)
            ->with(['notaryCredential.user', 'notaryRequest', 'document'])
            ->first();
    }
}
