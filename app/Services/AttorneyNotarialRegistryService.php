<?php

namespace App\Services;

use App\Enums\DocumentSignerStatus;
use App\Enums\NotaryIdentityVerificationStatus;
use App\Enums\PaymentStatus;
use App\Models\AttorneyNotarialRegistry;
use App\Models\DocumentSigner;
use App\Models\NotaryCredential;
use App\Models\NotaryIdentityVerification;
use App\Models\NotaryRequest;
use App\Models\NotarySigner;
use App\Models\Signature;
use App\Models\User;
use Illuminate\Support\Collection;

class AttorneyNotarialRegistryService
{
    /**
     * @return list<string>
     */
    public function notarialActTypes(): array
    {
        return ['acknowledgment', 'jurat', 'affidavit', 'oath', 'other'];
    }

    /**
     * @return array{
     *   title: string,
     *   notarial_act_type: string,
     *   fees: string,
     *   official_receipt_no: string,
     *   parties: list<array{name: string, address: string}>,
     *   witnesses: list<array{name: string, address: string}>,
     *   competent_evidence: list<array{
     *     person_name: string,
     *     id_type: string,
     *     id_number: string,
     *     verification_id: int|null,
     *     id_image_path: string|null
     *   }>,
     *   preview_entry_number: int|null,
     *   signature_image_path: string|null,
     *   prefilled_signer_count: int,
     *   verified_identity_count: int,
     *   signer_signatures: list<array{
     *     document_signer_id: int,
     *     document_id: int,
     *     signature_id: int,
     *     name: string,
     *     signature_path: string|null
     *   }>,
     *   payment_settled: bool,
     *   or_editable: bool,
     *   fees_editable: bool
     * }
     */
    public function draftStateForRequest(NotaryRequest $request, User $attorney): array
    {
        $request->loadMissing([
            'attorneyNotarialRegistry',
            'signers',
            'identityVerifications.signer',
            'payments',
            'eInvoices',
        ]);

        $existing = $request->attorneyNotarialRegistry;
        $credential = $this->activeCredential($attorney);
        $paymentSettled = $this->hasSettledPayment($request);
        $orEditable = $paymentSettled || ! $this->paymentRequired($request);
        $feesEditable = ! $paymentSettled;

        $base = $existing instanceof AttorneyNotarialRegistry
            ? [
                'title' => (string) $existing->title,
                'notarial_act_type' => (string) $existing->notarial_act_type,
                'fees' => number_format((float) $existing->fees, 2, '.', ''),
                'official_receipt_no' => $this->resolveOfficialReceiptNo($request, $existing),
                'parties' => $this->normalizeParties(is_array($existing->parties) ? $existing->parties : []),
                'witnesses' => $this->normalizeWitnesses(is_array($existing->witnesses) ? $existing->witnesses : []),
                'competent_evidence' => $this->normalizeEvidence(is_array($existing->competent_evidence) ? $existing->competent_evidence : []),
                'signature_image_path' => $existing->notary_signature_path ?? $credential?->signature_image_path,
            ]
            : [
                'title' => (string) $request->title,
                'notarial_act_type' => (string) ($request->request_type ?? 'acknowledgment'),
                'fees' => '',
                'official_receipt_no' => $this->resolveOfficialReceiptNo($request, null),
                'parties' => $this->partiesFromSigners($request->signers),
                'witnesses' => [],
                'competent_evidence' => $this->evidenceFromRequest($request),
                'signature_image_path' => $credential?->signature_image_path,
            ];

        return array_merge($base, [
            'preview_entry_number' => $credential?->nextEntryNumber(),
            'prefilled_signer_count' => $request->signers->count(),
            'verified_identity_count' => $request->identityVerifications
                ->where('verification_status', NotaryIdentityVerificationStatus::Verified)
                ->count(),
            'signer_signatures' => $this->signerSignaturesForRequest($request),
            'payment_settled' => $paymentSettled,
            'or_editable' => $orEditable,
            'fees_editable' => $feesEditable,
        ]);
    }

    public function saveSettlementFee(NotaryRequest $request, User $attorney, float $fees): AttorneyNotarialRegistry
    {
        $state = $this->draftStateForRequest($request, $attorney);

        return $this->persistDraft($request, $attorney, [
            'title' => $state['title'],
            'notarial_act_type' => $state['notarial_act_type'],
            'fees' => $fees,
            'official_receipt_no' => null,
            'parties' => $state['parties'],
            'witnesses' => $state['witnesses'],
            'competent_evidence' => $state['competent_evidence'],
        ], markRegistryFieldsComplete: false);
    }

    /**
     * @param  array{
     *   title: string,
     *   notarial_act_type: string,
     *   fees?: string|float|null,
     *   official_receipt_no?: string|null,
     *   parties: list<array{name: string, address: string}>,
     *   witnesses?: list<array{name: string, address: string}>,
     *   competent_evidence: list<array{person_name: string, id_type: string, id_number: string}>
     * }  $payload
     */
    public function saveDraft(NotaryRequest $request, User $attorney, array $payload): AttorneyNotarialRegistry
    {
        return $this->persistDraft($request, $attorney, $payload, markRegistryFieldsComplete: true);
    }

    /**
     * @param  array{
     *   title: string,
     *   notarial_act_type: string,
     *   fees?: string|float|null,
     *   official_receipt_no?: string|null,
     *   parties: list<array{name: string, address: string}>,
     *   witnesses?: list<array{name: string, address: string}>,
     *   competent_evidence: list<array<string, mixed>>
     * }  $payload
     */
    private function persistDraft(
        NotaryRequest $request,
        User $attorney,
        array $payload,
        bool $markRegistryFieldsComplete,
    ): AttorneyNotarialRegistry {
        $credential = $this->activeCredential($attorney);
        $existing = $request->attorneyNotarialRegistry;

        $attributes = [
            'entry_no' => null,
            'title' => trim($payload['title']),
            'description' => null,
            'parties' => $this->normalizeParties($payload['parties']),
            'witnesses' => $this->normalizeWitnesses($payload['witnesses'] ?? []),
            'competent_evidence' => $this->normalizeEvidence($payload['competent_evidence']),
            'notarial_act_type' => trim($payload['notarial_act_type']),
            'fees' => (float) ($payload['fees'] ?? 0),
            'official_receipt_no' => trim((string) ($payload['official_receipt_no'] ?? '')) !== ''
                ? trim((string) $payload['official_receipt_no'])
                : null,
            'notary_signature_path' => $credential?->signature_image_path,
            'registry_fields_completed_at' => $markRegistryFieldsComplete
                ? now()
                : $existing?->registry_fields_completed_at,
        ];

        if (! $markRegistryFieldsComplete) {
            $attributes['registry_fields_completed_at'] = null;
        }

        return AttorneyNotarialRegistry::query()->updateOrCreate(
            ['notary_request_id' => $request->id],
            $attributes,
        );
    }

    /**
     * @return list<array{
     *   document_signer_id: int,
     *   document_id: int,
     *   signature_id: int,
     *   name: string,
     *   signature_path: string|null
     * }>
     */
    public function signerSignaturesForRequest(NotaryRequest $request): array
    {
        $request->loadMissing('documents.documentSigners.signatures');

        $notaryUserId = (int) $request->notary_user_id;

        return $request->documents
            ->flatMap(fn ($document) => $document->documentSigners)
            ->filter(function (DocumentSigner $signer) use ($notaryUserId): bool {
                if ((int) $signer->user_id === $notaryUserId) {
                    return false;
                }

                return $signer->status === DocumentSignerStatus::Signed
                    || $signer->status->isCompleted();
            })
            ->map(function (DocumentSigner $signer): ?array {
                $signature = $signer->signatures
                    ->filter(fn (Signature $record): bool => is_string($record->signature_path) && $record->signature_path !== '')
                    ->sortByDesc('id')
                    ->first();

                if (! $signature instanceof Signature) {
                    return null;
                }

                return [
                    'document_signer_id' => (int) $signer->id,
                    'document_id' => (int) $signer->document_id,
                    'signature_id' => (int) $signature->id,
                    'name' => (string) $signer->name,
                    'signature_path' => $signature->signature_path,
                ];
            })
            ->filter()
            ->unique('document_signer_id')
            ->values()
            ->all();
    }

    private function resolveOfficialReceiptNo(NotaryRequest $request, ?AttorneyNotarialRegistry $existing): string
    {
        if ($existing?->official_receipt_no !== null && $existing->official_receipt_no !== '') {
            return (string) $existing->official_receipt_no;
        }

        $request->loadMissing('eInvoices');

        $invoice = $request->eInvoices->sortByDesc('created_at')->first();
        if ($invoice !== null && is_string($invoice->official_receipt_number) && $invoice->official_receipt_number !== '') {
            return $invoice->official_receipt_number;
        }

        return '';
    }

    public function paymentRequired(NotaryRequest $request): bool
    {
        $request->loadMissing(['registerEntries', 'attorneyNotarialRegistry']);

        if ($request->registerEntries->contains(
            fn ($entry): bool => (float) $entry->fees > 0
        )) {
            return true;
        }

        return (float) ($request->attorneyNotarialRegistry?->fees ?? 0) > 0;
    }

    public function hasSettledPayment(NotaryRequest $request): bool
    {
        if (! $this->paymentRequired($request)) {
            return true;
        }

        $request->loadMissing('payments');

        return $request->payments->contains(
            fn ($payment): bool => $payment->status === PaymentStatus::Paid
        );
    }

    public function isImagePath(?string $path): bool
    {
        if ($path === null || $path === '') {
            return false;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }

    /**
     * @param  Collection<int, NotarySigner>  $signers
     * @return list<array{name: string, address: string}>
     */
    private function partiesFromSigners(Collection $signers): array
    {
        if ($signers->isEmpty()) {
            return [['name' => '', 'address' => '']];
        }

        return $signers
            ->map(fn (NotarySigner $signer): array => [
                'name' => (string) $signer->full_name,
                'address' => (string) ($signer->address ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{
     *   person_name: string,
     *   id_type: string,
     *   id_number: string,
     *   verification_id: int|null,
     *   id_image_path: string|null
     * }>
     */
    private function evidenceFromRequest(NotaryRequest $request): array
    {
        $verified = $request->identityVerifications
            ->where('verification_status', NotaryIdentityVerificationStatus::Verified);

        if ($verified->isNotEmpty()) {
            return $verified
                ->map(function (NotaryIdentityVerification $verification): array {
                    $signer = $verification->signer;

                    return [
                        'person_name' => $signer?->full_name ?? (string) ($verification->id_type ?? __('Signer')),
                        'id_type' => (string) ($verification->id_type ?? ''),
                        'id_number' => (string) ($verification->id_number ?? ''),
                        'verification_id' => (int) $verification->id,
                        'id_image_path' => $verification->id_image_path,
                    ];
                })
                ->filter(fn (array $row): bool => trim($row['person_name']) !== '')
                ->values()
                ->all();
        }

        if ($request->signers->isEmpty()) {
            return [['person_name' => '', 'id_type' => '', 'id_number' => '', 'verification_id' => null, 'id_image_path' => null]];
        }

        return $request->signers
            ->map(fn (NotarySigner $signer): array => [
                'person_name' => (string) $signer->full_name,
                'id_type' => '',
                'id_number' => '',
                'verification_id' => null,
                'id_image_path' => null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{name?: string, address?: string}>  $parties
     * @return list<array{name: string, address: string}>
     */
    private function normalizeParties(array $parties): array
    {
        $normalized = collect($parties)
            ->map(fn (array $party): array => [
                'name' => trim((string) ($party['name'] ?? '')),
                'address' => trim((string) ($party['address'] ?? '')),
            ])
            ->filter(fn (array $party): bool => $party['name'] !== '')
            ->values()
            ->all();

        return $normalized !== [] ? $normalized : [['name' => '', 'address' => '']];
    }

    /**
     * @param  list<array{name?: string, address?: string}>  $witnesses
     * @return list<array{name: string, address: string}>
     */
    private function normalizeWitnesses(array $witnesses): array
    {
        return collect($witnesses)
            ->map(fn (array $witness): array => [
                'name' => trim((string) ($witness['name'] ?? '')),
                'address' => trim((string) ($witness['address'] ?? '')),
            ])
            ->filter(fn (array $witness): bool => $witness['name'] !== '' || $witness['address'] !== '')
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $evidence
     * @return list<array{
     *   person_name: string,
     *   id_type: string,
     *   id_number: string,
     *   verification_id: int|null,
     *   id_image_path: string|null
     * }>
     */
    private function normalizeEvidence(array $evidence): array
    {
        $normalized = collect($evidence)
            ->map(fn (array $row): array => [
                'person_name' => trim((string) ($row['person_name'] ?? '')),
                'id_type' => trim((string) ($row['id_type'] ?? '')),
                'id_number' => trim((string) ($row['id_number'] ?? '')),
                'verification_id' => isset($row['verification_id']) ? (int) $row['verification_id'] : null,
                'id_image_path' => isset($row['id_image_path']) ? (string) $row['id_image_path'] : null,
            ])
            ->filter(fn (array $row): bool => $row['person_name'] !== '')
            ->values()
            ->all();

        return $normalized !== []
            ? $normalized
            : [['person_name' => '', 'id_type' => '', 'id_number' => '', 'verification_id' => null, 'id_image_path' => null]];
    }

    private function activeCredential(User $attorney): ?NotaryCredential
    {
        return NotaryCredential::query()
            ->where('user_id', $attorney->id)
            ->where('status', 'active')
            ->latest()
            ->first();
    }
}
