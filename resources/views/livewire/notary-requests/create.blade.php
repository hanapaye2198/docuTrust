<?php

use App\Enums\UserRole;
use App\Models\NotaryRequest;
use App\Models\NotarySigner;
use App\Models\User;
use App\Services\AttorneyApplicationService;
use App\Services\NotaryParticipantSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.app')] class extends Component {
    use WithFileUploads;

    public string $title = '';

    public string $requestType = 'acknowledgment';

    public string $notaryUserId = '';

    public string $remarks = '';

    public $caseDocument = null;

    public int $wizardStep = 1;

    public int $maxWizardStepReached = 1;

    /**
     * @var list<array{full_name: string, email: string, phone: string, address: string, role: string, row_index?: int}>
     */
    public array $signers = [];

    /**
     * @var array<int, bool>
     */
    public array $openSignerPanels = [];

    /**
     * @var array<int, mixed>
     */
    public array $signerIdDocuments = [];

    /**
     * @var list<array{full_name: string, email: string, phone: string, address: string, role: string, witnessed_signer_row_index?: int|null, row_index?: int}>
     */
    public array $witnesses = [];

    /**
     * @var array<int, bool>
     */
    public array $openWitnessPanels = [];

    public function mount(): void
    {
        $this->signers = [];
        $this->signerIdDocuments = [];
        $this->witnesses = [];
    }

    public function totalWizardSteps(): int
    {
        return Auth::user()?->role === UserRole::Notary ? 4 : 1;
    }

    /**
     * @return array<string, string>
     */
    public function notarialActHints(): array
    {
        return [
            'acknowledgment' => __('Signer appeared before you and acknowledged signing the document.'),
            'jurat' => __('Signer swore to or affirmed the truth of the contents (often used for affidavits).'),
            'affidavit' => __('Written statement confirmed by oath or affirmation.'),
            'oath' => __('Oral or written oath administered for a specific purpose.'),
            'other' => __('Another notarial act — describe context in your internal notes.'),
        ];
    }

    public function addSignerRow(): void
    {
        $index = count($this->signers);
        $this->signers[] = [
            'full_name' => '',
            'email' => '',
            'phone' => '',
            'address' => '',
            'role' => 'signer',
        ];
        $this->openSignerPanels[$index] = true;
    }

    public function removeSignerRow(int $index): void
    {
        $remainingOpenStates = [];
        foreach ($this->signers as $rowIndex => $signer) {
            if ($rowIndex === $index) {
                continue;
            }

            $remainingOpenStates[] = (bool) ($this->openSignerPanels[$rowIndex] ?? false);
        }

        unset($this->signers[$index]);
        $this->signers = array_values($this->signers);
        unset($this->signerIdDocuments[$index]);
        $this->signerIdDocuments = array_values($this->signerIdDocuments);
        $this->openSignerPanels = array_combine(
            range(0, max(count($this->signers) - 1, 0)),
            $remainingOpenStates,
        ) ?: [];
    }

    public function updatedSigners(mixed $value, ?string $key = null): void
    {
        if ($key === null) {
            return;
        }

        if (preg_match('/^(\d+)\./', $key, $matches) !== 1) {
            return;
        }

        $this->openSignerPanels[(int) $matches[1]] = true;
    }

    public function removeSignerIdDocument(int $index): void
    {
        unset($this->signerIdDocuments[$index]);
        $this->signerIdDocuments = array_replace(array_fill(0, count($this->signers), null), $this->signerIdDocuments);
        $this->resetValidation("signerIdDocuments.{$index}");
    }

    public function addWitnessRow(): void
    {
        $index = count($this->witnesses);
        $this->witnesses[] = [
            'full_name' => '',
            'email' => '',
            'phone' => '',
            'address' => '',
            'role' => 'witness',
            'witnessed_signer_row_index' => null,
        ];
        $this->openWitnessPanels[$index] = true;
    }

    public function removeWitnessRow(int $index): void
    {
        $remainingOpenStates = [];
        foreach ($this->witnesses as $rowIndex => $witness) {
            if ($rowIndex === $index) {
                continue;
            }

            $remainingOpenStates[] = (bool) ($this->openWitnessPanels[$rowIndex] ?? false);
        }

        unset($this->witnesses[$index]);
        $this->witnesses = array_values($this->witnesses);
        $this->openWitnessPanels = array_combine(
            range(0, max(count($this->witnesses) - 1, 0)),
            $remainingOpenStates,
        ) ?: [];
    }

    public function updatedWitnesses(mixed $value, ?string $key = null): void
    {
        if ($key === null) {
            return;
        }

        if (preg_match('/^(\d+)\./', $key, $matches) !== 1) {
            return;
        }

        $this->openWitnessPanels[(int) $matches[1]] = true;
    }

    public function removeCaseDocument(): void
    {
        $this->caseDocument = null;
    }

    public function nextStep(): void
    {
        $this->validateWizardStep($this->wizardStep);

        if ($this->wizardStep < $this->totalWizardSteps()) {
            $this->wizardStep++;
            $this->maxWizardStepReached = max($this->maxWizardStepReached, $this->wizardStep);
        }
    }

    public function previousStep(): void
    {
        if ($this->wizardStep > 1) {
            $this->wizardStep--;
        }
    }

    public function goToWizardStep(int $step): void
    {
        if ($step < 1 || $step > $this->totalWizardSteps()) {
            return;
        }

        if ($step <= $this->maxWizardStepReached) {
            $this->wizardStep = $step;
        }
    }

    public function skipDocumentStep(): void
    {
        $this->validateWizardStep(1);
        $this->wizardStep = 3;
        $this->maxWizardStepReached = max($this->maxWizardStepReached, 3);
    }

    public function skipPartiesStep(): void
    {
        $this->signers = [];
        $this->signerIdDocuments = [];

        if ($this->wizardStep >= 3) {
            $this->witnesses = [];
            $this->wizardStep = 4;
            $this->maxWizardStepReached = max($this->maxWizardStepReached, 4);
            $this->save();

            return;
        }

        $this->wizardStep = 3;
        $this->maxWizardStepReached = max($this->maxWizardStepReached, 3);
    }

    public function skipWitnessStep(): void
    {
        $this->witnesses = [];
        $this->wizardStep = 4;
        $this->maxWizardStepReached = max($this->maxWizardStepReached, 4);
    }

    protected function validateWizardStep(int $step): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        $isNotary = $user->role === UserRole::Notary;

        if ($step === 1) {
            $rules = [
                'title' => ['required', 'string', 'max:255'],
                'requestType' => ['required', 'string', 'max:64'],
                'remarks' => ['nullable', 'string', 'max:2000'],
            ];

            if (! $isNotary && trim((string) $this->notaryUserId) !== '') {
                $rules['notaryUserId'] = ['required', 'exists:users,id'];
            }

            $this->validate($rules);

            if ($isNotary && $this->caseDocument !== null) {
                $this->validate([
                    'caseDocument' => ['file', 'mimes:pdf', 'max:10240'],
                ]);
            }

            return;
        }

        if (! $isNotary) {
            return;
        }

        if ($step === 2) {
            $this->validateSignerRows();

            if ($this->signerIdDocuments !== []) {
                $this->validate([
                    'signerIdDocuments.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
                ]);
            }
        }

        if ($step === 3) {
            $this->validateWitnessRows();
        }
    }

    protected function validateSignerRows(): void
    {
        $this->signers = $this->normalizedSigners();

        if ($this->signers === []) {
            return;
        }

        $this->validate([
            'signers' => ['array'],
            'signers.*.full_name' => ['required', 'string', 'max:255'],
            'signers.*.email' => ['required', 'email', 'max:255'],
            'signers.*.phone' => ['nullable', 'string', 'max:64'],
            'signers.*.address' => ['nullable', 'string', 'max:500'],
            'signers.*.role' => ['nullable', 'string', 'max:64'],
        ]);

        $this->assertUniqueSignerEmails($this->signers);
    }

    protected function validateWitnessRows(): void
    {
        $this->witnesses = $this->normalizedWitnesses();

        if ($this->witnesses === []) {
            return;
        }

        $this->validate([
            'witnesses' => ['array'],
            'witnesses.*.full_name' => ['required', 'string', 'max:255'],
            'witnesses.*.email' => ['required', 'email', 'max:255'],
            'witnesses.*.phone' => ['nullable', 'string', 'max:64'],
            'witnesses.*.address' => ['nullable', 'string', 'max:500'],
            'witnesses.*.role' => ['nullable', 'string', 'max:64'],
            'witnesses.*.witnessed_signer_row_index' => ['nullable', 'integer', 'min:0'],
        ]);

        $validSignerRowIndexes = collect($this->normalizedSigners())
            ->pluck('row_index')
            ->all();

        foreach ($this->witnesses as $index => $witness) {
            $witnessedSignerRowIndex = $witness['witnessed_signer_row_index'] ?? null;
            if ($witnessedSignerRowIndex !== null && ! in_array($witnessedSignerRowIndex, $validSignerRowIndexes, true)) {
                throw ValidationException::withMessages([
                    "witnesses.{$index}.witnessed_signer_row_index" => __('Select a valid client or signer for this witness.'),
                ]);
            }
        }

        $this->assertUniqueSignerEmails($this->witnesses, 'witnesses');
    }

    /**
     * @return list<array{full_name: string, email: string, phone: string, address: string, role: string, row_index: int}>
     */
    protected function normalizedSigners(): array
    {
        return $this->normalizedPartyRows($this->signers, 'signers', 'signer');
    }

    /**
     * @return list<array{full_name: string, email: string, phone: string, address: string, role: string, witnessed_signer_row_index: int|null, row_index: int}>
     */
    protected function normalizedWitnesses(): array
    {
        return $this->normalizedPartyRows($this->witnesses, 'witnesses', 'witness');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function normalizedPartyRows(array $rows, string $errorPrefix, string $defaultRole): array
    {
        $normalized = [];

        foreach ($rows as $index => $row) {
            $fullName = trim((string) ($row['full_name'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));
            $phone = trim((string) ($row['phone'] ?? ''));
            $address = trim((string) ($row['address'] ?? ''));
            $role = trim((string) ($row['role'] ?? '')) !== '' ? trim((string) ($row['role'] ?? '')) : $defaultRole;
            $witnessedSignerRowIndex = $row['witnessed_signer_row_index'] ?? null;
            $witnessedSignerRowIndex = is_numeric($witnessedSignerRowIndex) ? (int) $witnessedSignerRowIndex : null;

            $hasAny = $fullName !== '' || $email !== '' || $phone !== '' || $address !== '';
            $hasAll = $fullName !== '' && $email !== '';

            if (! $hasAny) {
                continue;
            }

            if (! $hasAll) {
                throw ValidationException::withMessages([
                    "{$errorPrefix}.{$index}.full_name" => __('Enter both full name and email for each party, or leave the row empty.'),
                ]);
            }

            $normalized[] = [
                'full_name' => $fullName,
                'email' => strtolower($email),
                'phone' => $phone,
                'address' => $address,
                'role' => $role,
                'witnessed_signer_row_index' => $witnessedSignerRowIndex,
                'row_index' => $index,
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array{full_name: string, email: string, phone: string, address: string, role: string}>  $signers
     */
    protected function assertUniqueSignerEmails(array $signers, string $errorPrefix = 'signers'): void
    {
        $seen = [];

        foreach ($signers as $index => $signer) {
            $email = strtolower(trim((string) ($signer['email'] ?? '')));

            if ($email === '') {
                continue;
            }

            if (isset($seen[$email])) {
                throw ValidationException::withMessages([
                    "{$errorPrefix}.{$index}.email" => __('Each party must use a unique email address.'),
                ]);
            }

            $seen[$email] = true;
        }
    }

    /**
     * @return list<array{id: int, label: string, description: string, complete: bool, optional?: bool, current: bool}>
     */
    #[Computed]
    public function wizardSteps(): array
    {
        $hasSigner = collect($this->signers)->contains(
            fn (array $signer): bool => trim((string) ($signer['full_name'] ?? '')) !== ''
                && trim((string) ($signer['email'] ?? '')) !== ''
        );
        $hasWitness = collect($this->witnesses)->contains(
            fn (array $witness): bool => trim((string) ($witness['full_name'] ?? '')) !== ''
                && trim((string) ($witness['email'] ?? '')) !== ''
        );

        return [
            [
                'id' => 1,
                'label' => __('Case Info'),
                'description' => __('Title, act type, PDF, and notes'),
                'complete' => trim($this->title) !== '',
                'optional' => false,
                'current' => $this->wizardStep === 1,
            ],
            [
                'id' => 2,
                'label' => __('Client / Signer'),
                'description' => __('Primary signer or client details'),
                'complete' => $hasSigner,
                'optional' => true,
                'current' => $this->wizardStep === 2,
            ],
            [
                'id' => 3,
                'label' => __('Witness'),
                'description' => __('Witness details if needed'),
                'complete' => $hasWitness,
                'optional' => true,
                'current' => $this->wizardStep === 3,
            ],
            [
                'id' => 4,
                'label' => __('Review'),
                'description' => __('Confirm and create case'),
                'complete' => false,
                'optional' => false,
                'current' => $this->wizardStep === 4,
            ],
        ];
    }

    /**
     * @return list<array{label: string, description: string}>
     */
    #[Computed]
    public function workflowRoadmap(): array
    {
        return [
            ['label' => __('Open case'), 'description' => __('You are here — title and act type.')],
            ['label' => __('Upload & send'), 'description' => __('Upload PDF, prepare fields, send to signers.')],
            ['label' => __('Signers sign'), 'description' => __('Parties complete signatures.')],
            ['label' => __('Video conference'), 'description' => __('Identity verification session.')],
            ['label' => __('Attorney signs'), 'description' => __('You sign after verification.')],
            ['label' => __('Register & seal'), 'description' => __('Register entry and digital notarization.')],
        ];
    }

    public function with(): array
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        $isNotary = $user->role === UserRole::Notary;

        return [
            'isNotaryView' => $isNotary,
            'availableNotaries' => User::query()
                ->where('role', UserRole::Notary)
                ->orderBy('name')
                ->get(['id', 'name']),
            'practiceEligibility' => $isNotary
                ? app(AttorneyApplicationService::class)->practiceEligibility($user)
                : ['allowed' => true, 'message' => null],
            'notarialActHints' => $this->notarialActHints(),
        ];
    }

    public function save(): void
    {
        Gate::authorize('create', NotaryRequest::class);

        $user = Auth::user();
        abort_unless($user !== null, 401);

        $isNotary = $user->role === UserRole::Notary;

        if ($isNotary) {
            for ($step = 1; $step <= $this->totalWizardSteps(); $step++) {
                $this->validateWizardStep($step);
            }

            $eligibility = app(AttorneyApplicationService::class)->practiceEligibility($user);
            if (! $eligibility['allowed']) {
                throw ValidationException::withMessages([
                    'title' => $eligibility['message'] ?? __('Attorney practice is not enabled.'),
                ]);
            }

            $this->signers = $this->normalizedSigners();
            $this->witnesses = $this->normalizedWitnesses();
        } else {
            $this->validateWizardStep(1);
        }

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'requestType' => ['required', 'string', 'max:64'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];

        if ($isNotary) {
            $rules['caseDocument'] = ['nullable', 'file', 'mimes:pdf', 'max:10240'];

            if ($this->signers !== []) {
                $rules['signers'] = ['array'];
                $rules['signers.*.full_name'] = ['required', 'string', 'max:255'];
                $rules['signers.*.email'] = ['required', 'email', 'max:255'];
                $rules['signers.*.phone'] = ['nullable', 'string', 'max:64'];
                $rules['signers.*.address'] = ['nullable', 'string', 'max:500'];
                $rules['signers.*.role'] = ['nullable', 'string', 'max:64'];
                $rules['signerIdDocuments.*'] = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'];
            }

            if ($this->witnesses !== []) {
                $rules['witnesses'] = ['array'];
                $rules['witnesses.*.full_name'] = ['required', 'string', 'max:255'];
                $rules['witnesses.*.email'] = ['required', 'email', 'max:255'];
                $rules['witnesses.*.phone'] = ['nullable', 'string', 'max:64'];
                $rules['witnesses.*.address'] = ['nullable', 'string', 'max:500'];
                $rules['witnesses.*.role'] = ['nullable', 'string', 'max:64'];
                $rules['witnesses.*.witnessed_signer_row_index'] = ['nullable', 'integer', 'min:0'];
            }
        }

        $notaryUserId = null;
        if (trim((string) $this->notaryUserId) !== '') {
            $rules['notaryUserId'] = ['required', 'exists:users,id'];
        }

        $validated = $this->validate($rules);

        if (isset($validated['notaryUserId'])) {
            $notaryUserId = (int) $validated['notaryUserId'];
        }

        if ($isNotary && $this->signers !== []) {
            $this->assertUniqueSignerEmails($this->signers);
        }

        if ($isNotary && $this->witnesses !== []) {
            $this->assertUniqueSignerEmails($this->witnesses, 'witnesses');
        }

        if ($isNotary && $notaryUserId === null) {
            $notaryUserId = $user->id;
        }

        $documentPath = null;
        if ($isNotary && $this->caseDocument !== null) {
            $documentPath = $this->caseDocument->store('notary-requests', (string) config('filesystems.docutrust_disk', 'local'));
        }

        $request = $user->notaryRequests()->create([
            'title' => trim($validated['title']),
            'request_type' => trim($validated['requestType']),
            'notary_user_id' => $notaryUserId,
            'document_path' => $documentPath,
            'remarks' => trim((string) ($validated['remarks'] ?? '')) !== '' ? trim((string) $validated['remarks']) : null,
            'metadata' => [
                'created_from' => 'enotary_wizard',
                'wizard_steps_completed' => $isNotary ? $this->maxWizardStepReached : 1,
            ],
        ]);

        $document = null;
        if ($isNotary && $documentPath !== null) {
            $document = $user->documents()->create([
                'notary_request_id' => $request->id,
                'title' => trim($validated['title']),
                'file_path' => $documentPath,
                'status' => \App\Enums\DocumentStatus::Draft,
            ]);
        }

        $createdSignerIdsByRowIndex = [];

        if ($isNotary && $this->signers !== []) {
            foreach ($this->signers as $signerRow) {
                if (trim((string) ($signerRow['full_name'] ?? '')) === '') {
                    continue;
                }
                $rowIndex = (int) ($signerRow['row_index'] ?? 0);
                $idDocumentPath = isset($this->signerIdDocuments[$rowIndex]) && $this->signerIdDocuments[$rowIndex] !== null
                    ? $this->signerIdDocuments[$rowIndex]->store('notary/identity', (string) config('filesystems.docutrust_disk', 'local'))
                    : null;

                $createdSigner = NotarySigner::query()->create([
                    'notary_request_id' => $request->id,
                    'full_name' => trim((string) $signerRow['full_name']),
                    'email' => strtolower(trim((string) $signerRow['email'])),
                    'phone' => trim((string) ($signerRow['phone'] ?? '')) !== '' ? trim((string) $signerRow['phone']) : null,
                    'address' => trim((string) ($signerRow['address'] ?? '')) !== '' ? trim((string) $signerRow['address']) : null,
                    'id_document_path' => $idDocumentPath,
                    'role' => trim((string) ($signerRow['role'] ?? '')) !== '' ? trim((string) $signerRow['role']) : 'signer',
                ]);

                $createdSignerIdsByRowIndex[$rowIndex] = $createdSigner->id;
            }
        }

        if ($isNotary && $this->witnesses !== []) {
            foreach ($this->witnesses as $witnessRow) {
                if (trim((string) ($witnessRow['full_name'] ?? '')) === '') {
                    continue;
                }

                $witnessedSignerRowIndex = $witnessRow['witnessed_signer_row_index'] ?? null;
                $witnessedSignerId = $witnessedSignerRowIndex !== null
                    ? ($createdSignerIdsByRowIndex[(int) $witnessedSignerRowIndex] ?? null)
                    : null;

                NotarySigner::query()->create([
                    'notary_request_id' => $request->id,
                    'full_name' => trim((string) $witnessRow['full_name']),
                    'email' => strtolower(trim((string) $witnessRow['email'])),
                    'phone' => trim((string) ($witnessRow['phone'] ?? '')) !== '' ? trim((string) $witnessRow['phone']) : null,
                    'address' => trim((string) ($witnessRow['address'] ?? '')) !== '' ? trim((string) $witnessRow['address']) : null,
                    'role' => 'witness',
                    'witnessed_signer_id' => $witnessedSignerId,
                ]);
            }
        }

        if ($document !== null) {
            app(NotaryParticipantSyncService::class)->syncRequestSignersToDocument($document);
        }

        if ($isNotary) {
            if ($document !== null) {
                session()->flash('status', __('Case created with your PDF. Prepare all signer, witness, and attorney fields now.'));
            } else {
                session()->flash('status', __('Case opened. Upload your PDF and add parties from the case page when ready.'));
            }
        } else {
            session()->flash('status', __('Notarization created. The assigned attorney will upload documents and manage the signing process.'));
        }

        if ($isNotary && $document !== null) {
            $this->redirect(route('notary.documents.prepare', $document, absolute: false), navigate: true);

            return;
        }

        $showRoute = $isNotary ? 'notary.requests.workflow' : 'notary-requests.show';

        $this->redirect(route($showRoute, $request, absolute: false), navigate: true);
    }
}; ?>

<x-admin.page class="h-full flex-1 bg-slate-50/60 dark:bg-zinc-950" gap="gap-6" wide>
    <div class="space-y-1">
        <flux:button
            variant="ghost"
            size="sm"
            :href="$isNotaryView ? route('notary.requests.index') : route('notary-requests.index')"
            wire:navigate
            icon="arrow-left"
        >
            {{ __('Back to notarizations') }}
        </flux:button>
        <h1 class="text-2xl font-bold tracking-tight text-zinc-950 dark:text-white sm:text-3xl">
            {{ $isNotaryView ? __('Create Notary Case') : __('Create Notarization Request') }}
        </h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            {{ $isNotaryView ? __('Set up a new online notarization in four short steps.') : __('Submit the matter details. Your attorney will upload documents and manage signing.') }}
        </p>
    </div>

    @if ($isNotaryView && ! ($practiceEligibility['allowed'] ?? true))
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.heading>{{ __('Attorney practice not enabled') }}</flux:callout.heading>
            <flux:callout.text>{{ $practiceEligibility['message'] ?? __('Complete your attorney application before opening new cases.') }}</flux:callout.text>
        </flux:callout>
    @endif

    <form wire:submit="save">
        @if ($isNotaryView)
            <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <nav aria-label="{{ __('Create notary case steps') }}" class="border-b border-zinc-200 px-4 py-5 dark:border-zinc-800 sm:px-6">
                    <ol class="mx-auto flex max-w-3xl items-center justify-between gap-2">
                        @foreach ($this->wizardSteps as $step)
                            <li class="flex min-w-0 flex-1 items-center gap-2">
                                <button
                                    type="button"
                                    wire:click="goToWizardStep({{ $step['id'] }})"
                                    @disabled($step['id'] > $maxWizardStepReached)
                                    class="group flex min-w-0 items-center gap-2"
                                >
                                    <span @class([
                                        'flex size-7 shrink-0 items-center justify-center rounded-full border text-xs font-semibold transition',
                                        'border-blue-600 bg-blue-600 text-white shadow-sm shadow-blue-600/25' => $step['current'],
                                        'border-emerald-600 bg-emerald-600 text-white' => $step['complete'] && ! $step['current'],
                                        'border-zinc-200 bg-white text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400' => ! $step['complete'] && ! $step['current'],
                                    ])>
                                        @if ($step['complete'] && ! $step['current'])
                                            <flux:icon.check class="size-3.5" />
                                        @else
                                            {{ $step['id'] }}
                                        @endif
                                    </span>
                                    <span @class([
                                        'hidden truncate text-sm font-semibold sm:block',
                                        'text-zinc-950 dark:text-white' => $step['current'],
                                        'text-zinc-500 dark:text-zinc-400' => ! $step['current'],
                                    ])>{{ $step['label'] }}</span>
                                </button>
                                @if (! $loop->last)
                                    <span class="h-px flex-1 bg-zinc-200 dark:bg-zinc-700" aria-hidden="true"></span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </nav>

                <div class="p-4 sm:p-6">
                    @if ($wizardStep === 1)
                        <div class="space-y-5">
                            <div>
                                <h2 class="text-base font-semibold uppercase tracking-[0.16em] text-zinc-700 dark:text-zinc-300">{{ __('Case Info') }}</h2>
                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Add the basic case details and upload the PDF if it is ready.') }}</p>
                            </div>

                            <div class="grid gap-5 lg:grid-cols-2">
                                <flux:field>
                                    <flux:label>{{ __('Case title') }} <span class="text-rose-500">*</span></flux:label>
                                    <flux:input wire:model.live="title" type="text" required placeholder="{{ __('Deed of Absolute Sale — Reyes Property') }}" />
                                    <flux:error name="title" />
                                </flux:field>

                                <div>
                                    <flux:select wire:model.live="requestType" label="{{ __('Notarial act') }}" required>
                                        @foreach (config('docutrust.notary.notarial_act_types', []) as $act)
                                            <flux:select.option value="{{ $act }}">{{ __(ucfirst(str_replace('_', ' ', $act))) }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    @if (! empty($notarialActHints[$requestType] ?? null))
                                        <p class="mt-2 text-xs text-teal-700 dark:text-teal-300">{{ $notarialActHints[$requestType] }}</p>
                                    @endif
                                    <flux:error name="requestType" />
                                </div>

                                <div>
                                    <flux:label>{{ __('Document upload') }}</flux:label>
                                    @if ($caseDocument)
                                        <div class="mt-2 flex min-h-32 items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 dark:border-emerald-900/50 dark:bg-emerald-950/20">
                                            <flux:icon.document-text class="size-8 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-sm font-semibold text-emerald-900 dark:text-emerald-100">{{ $caseDocument->getClientOriginalName() }}</p>
                                                <p class="text-xs text-emerald-700 dark:text-emerald-300">{{ __('PDF ready for the workflow page.') }}</p>
                                            </div>
                                            <flux:button type="button" size="sm" variant="ghost" wire:click="removeCaseDocument" wire:loading.attr="disabled" wire:target="removeCaseDocument">
                                                {{ __('Remove') }}
                                            </flux:button>
                                        </div>
                                    @else
                                        <div
                                            class="relative mt-2 min-h-32 overflow-hidden rounded-2xl border-2 border-dashed border-blue-200 bg-gradient-to-br from-blue-50 via-white to-indigo-50 px-5 py-6 text-center shadow-[0_18px_48px_-28px_rgba(37,99,235,0.45)] transition dark:border-blue-900/60 dark:from-blue-950/30 dark:via-zinc-950 dark:to-indigo-950/20"
                                            x-data="{ progress: 0, dragging: false }"
                                            x-bind:class="dragging ? 'border-blue-500 bg-blue-50/90 ring-4 ring-blue-100 dark:border-blue-400 dark:ring-blue-950/50' : ''"
                                            x-on:livewire-upload-start="progress = 0"
                                            x-on:livewire-upload-finish="progress = 0"
                                            x-on:livewire-upload-error="progress = 0"
                                            x-on:livewire-upload-progress="progress = $event.detail.progress"
                                            x-on:dragover.prevent="dragging = true"
                                            x-on:dragleave.prevent="dragging = false"
                                            x-on:drop.prevent="
                                                dragging = false;
                                                if ($event.dataTransfer.files.length) {
                                                    $refs.casePdf.files = $event.dataTransfer.files;
                                                    $refs.casePdf.dispatchEvent(new Event('change', { bubbles: true }));
                                                }
                                            "
                                        >
                                            <div class="pointer-events-none absolute -left-8 -top-8 size-24 rounded-full bg-blue-200/50 blur-2xl dark:bg-blue-500/10"></div>
                                            <div class="pointer-events-none absolute -bottom-10 -right-10 size-28 rounded-full bg-indigo-200/60 blur-2xl dark:bg-indigo-500/10"></div>

                                            <div class="pointer-events-none absolute inset-x-4 top-3 flex justify-center">
                                                <span class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-white/90 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-blue-700 shadow-sm dark:border-blue-800/60 dark:bg-zinc-900/80 dark:text-blue-300">
                                                    <flux:icon.sparkles class="size-3.5" />
                                                    {{ __('Recommended if PDF is ready') }}
                                                </span>
                                            </div>

                                            <div class="relative mx-auto mt-7 flex size-14 items-center justify-center rounded-2xl bg-white text-blue-600 shadow-sm ring-1 ring-blue-100 dark:bg-zinc-900 dark:text-blue-400 dark:ring-blue-900/60">
                                                <span class="absolute inset-0 rounded-2xl bg-blue-400/20 animate-ping"></span>
                                                <flux:icon.cloud-arrow-up class="relative size-8" />
                                            </div>

                                            <p class="mt-4 text-base font-semibold text-zinc-900 dark:text-zinc-100">
                                                {{ __('Drop PDF here') }}
                                            </p>
                                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                                {{ __('or') }}
                                                <button type="button" class="font-semibold text-blue-600 underline-offset-2 hover:underline dark:text-blue-400" x-on:click="$refs.casePdf.click()">{{ __('browse your files') }}</button>
                                            </p>
                                            <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('PDF only · up to 10 MB · encrypted in transit') }}</p>
                                            <input x-ref="casePdf" type="file" wire:model="caseDocument" accept="application/pdf,.pdf" class="sr-only" />

                                            <div wire:loading wire:target="caseDocument" class="relative mx-auto mt-5 max-w-md rounded-2xl border border-blue-200 bg-white/90 p-4 text-left shadow-sm dark:border-blue-900/50 dark:bg-zinc-950/70">
                                                <div class="flex items-end justify-between gap-4">
                                                    <div>
                                                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700 dark:text-blue-300">{{ __('Upload progress') }}</p>
                                                        <p class="mt-1 text-sm font-medium text-zinc-700 dark:text-zinc-200">
                                                            <span x-text="progress > 0 ? '{{ __('Uploading') }}' : '{{ __('Preparing upload') }}'"></span>
                                                        </p>
                                                    </div>
                                                    <div class="text-2xl font-semibold tabular-nums text-blue-700 dark:text-blue-300" x-text="(progress > 0 ? progress : 0) + '%'"></div>
                                                </div>
                                                <div class="mt-3 h-3 overflow-hidden rounded-full bg-blue-100 dark:bg-blue-950/50">
                                                    <div class="h-full rounded-full bg-gradient-to-r from-blue-500 via-indigo-500 to-blue-600 transition-all duration-300" :style="'width: ' + Math.max(progress, 8) + '%'"></div>
                                                </div>
                                                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Please keep this page open until the upload finishes.') }}</p>
                                            </div>
                                        </div>
                                    @endif
                                    <flux:error name="caseDocument" />
                                </div>

                                <flux:field>
                                    <flux:label>{{ __('Notes') }} <span class="text-xs font-normal text-zinc-400">{{ __('Optional') }}</span></flux:label>
                                    <flux:textarea wire:model="remarks" rows="6" placeholder="{{ __('Internal notes for your team...') }}" />
                                    <flux:error name="remarks" />
                                </flux:field>
                            </div>
                        </div>
                    @endif

                    @if ($wizardStep === 2)
                        <div class="space-y-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h2 class="text-base font-semibold uppercase tracking-[0.16em] text-zinc-700 dark:text-zinc-300">{{ __('Client / Signer') }}</h2>
                                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Add the primary client, signer, principal, or affiant.') }}</p>
                                </div>
                                <flux:button type="button" variant="outline" size="sm" icon="plus" wire:click="addSignerRow">{{ __('Add signer') }}</flux:button>
                            </div>

                            <div class="space-y-4">
                                @forelse ($signers as $index => $signer)
                                    <details class="group rounded-xl border border-zinc-200 bg-zinc-50/70 dark:border-zinc-700 dark:bg-zinc-950/30" wire:key="signer-{{ $index }}" @if ($openSignerPanels[$index] ?? $loop->first) open @endif>
                                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 marker:content-none">
                                            <span class="truncate text-sm font-semibold text-zinc-800 dark:text-zinc-200">{{ ! empty($signers[$index]['full_name']) ? $signers[$index]['full_name'] : __('Signer :n', ['n' => $index + 1]) }}</span>
                                            <span class="flex items-center gap-2" x-on:click.stop>
                                                <flux:button type="button" size="sm" variant="ghost" icon="trash" wire:click="removeSignerRow({{ $index }})">{{ __('Remove') }}</flux:button>
                                                <flux:icon.chevron-down class="size-4 text-zinc-400 transition group-open:rotate-180" />
                                            </span>
                                        </summary>
                                        <div class="grid gap-4 border-t border-zinc-200 px-5 pb-5 pt-4 md:grid-cols-2 xl:grid-cols-3 dark:border-zinc-700">
                                            <flux:field>
                                                <flux:label>{{ __('Full name') }}</flux:label>
                                                <flux:input type="text" wire:model.blur="signers.{{ $index }}.full_name" placeholder="{{ __('Juan Dela Cruz') }}" />
                                                <flux:error name="signers.{{ $index }}.full_name" />
                                            </flux:field>
                                            <flux:field>
                                                <flux:label>{{ __('Email') }}</flux:label>
                                                <flux:input type="email" wire:model="signers.{{ $index }}.email" placeholder="{{ __('juan@example.com') }}" />
                                                <flux:error name="signers.{{ $index }}.email" />
                                            </flux:field>
                                            <flux:field>
                                                <flux:label>{{ __('Phone') }}</flux:label>
                                                <flux:input type="text" wire:model="signers.{{ $index }}.phone" placeholder="{{ __('+63 9XX XXX XXXX') }}" />
                                                <flux:error name="signers.{{ $index }}.phone" />
                                            </flux:field>
                                            <flux:field class="md:col-span-2">
                                                <flux:label>{{ __('Address') }}</flux:label>
                                                <flux:input type="text" wire:model="signers.{{ $index }}.address" placeholder="{{ __('For identity verification') }}" />
                                                <flux:error name="signers.{{ $index }}.address" />
                                            </flux:field>
                                            <flux:select wire:model="signers.{{ $index }}.role" label="{{ __('Role') }}">
                                                <flux:select.option value="signer">{{ __('Signer') }}</flux:select.option>
                                                <flux:select.option value="affiant">{{ __('Affiant') }}</flux:select.option>
                                                <flux:select.option value="principal">{{ __('Principal') }}</flux:select.option>
                                            </flux:select>
                                            <div class="md:col-span-2 xl:col-span-3">
                                                <flux:label>{{ __('ID document') }} <span class="text-xs font-normal text-zinc-400">{{ __('Optional') }}</span></flux:label>
                                                @if ($signerIdDocuments[$index] ?? null)
                                                    <div class="mt-2 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 dark:border-emerald-900/50 dark:bg-emerald-950/20">
                                                        <flux:icon.identification class="size-7 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                                        <div class="min-w-0 flex-1">
                                                            <p class="truncate text-sm font-semibold text-emerald-900 dark:text-emerald-100">{{ $signerIdDocuments[$index]->getClientOriginalName() }}</p>
                                                            <p class="text-xs text-emerald-700 dark:text-emerald-300">{{ __('ID upload ready for case setup.') }}</p>
                                                        </div>
                                                        <flux:button type="button" size="sm" variant="ghost" wire:click="removeSignerIdDocument({{ $index }})" wire:loading.attr="disabled" wire:target="removeSignerIdDocument({{ $index }})">
                                                            {{ __('Remove') }}
                                                        </flux:button>
                                                    </div>
                                                @else
                                                    <div
                                                        class="mt-2 rounded-xl border border-dashed border-zinc-300 bg-white px-4 py-4 transition dark:border-zinc-700 dark:bg-zinc-900/60"
                                                        x-data="{ progress: 0, dragging: false }"
                                                        x-bind:class="dragging ? 'border-blue-500 bg-blue-50 ring-4 ring-blue-100 dark:border-blue-400 dark:bg-blue-950/20 dark:ring-blue-950/50' : ''"
                                                        x-on:livewire-upload-start="progress = 0"
                                                        x-on:livewire-upload-finish="progress = 0"
                                                        x-on:livewire-upload-error="progress = 0"
                                                        x-on:livewire-upload-progress="progress = $event.detail.progress"
                                                        x-on:dragover.prevent="dragging = true"
                                                        x-on:dragleave.prevent="dragging = false"
                                                        x-on:drop.prevent="
                                                            dragging = false;
                                                            if ($event.dataTransfer.files.length) {
                                                                $refs.signerId.files = $event.dataTransfer.files;
                                                                $refs.signerId.dispatchEvent(new Event('change', { bubbles: true }));
                                                            }
                                                        "
                                                    >
                                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                                            <div class="flex min-w-0 items-center gap-3">
                                                                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300">
                                                                    <flux:icon.identification class="size-5" />
                                                                </span>
                                                                <div class="min-w-0">
                                                                    <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Upload ID document') }}</p>
                                                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('PDF, JPG, JPEG, or PNG up to 10 MB') }}</p>
                                                                </div>
                                                            </div>
                                                            <flux:button type="button" size="sm" variant="outline" icon="arrow-up-tray" x-on:click="$refs.signerId.click()">
                                                                {{ __('Browse') }}
                                                            </flux:button>
                                                        </div>
                                                        <input x-ref="signerId" type="file" wire:model="signerIdDocuments.{{ $index }}" accept="application/pdf,.pdf,.jpg,.jpeg,.png,image/jpeg,image/png" class="sr-only" />

                                                        <div wire:loading wire:target="signerIdDocuments.{{ $index }}" class="mt-3">
                                                            <div class="flex items-center justify-between gap-3 text-xs text-zinc-500 dark:text-zinc-400">
                                                                <span x-text="progress > 0 ? '{{ __('Uploading ID') }}' : '{{ __('Preparing upload') }}'"></span>
                                                                <span class="font-semibold tabular-nums text-blue-600 dark:text-blue-300" x-text="(progress > 0 ? progress : 0) + '%'"></span>
                                                            </div>
                                                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-blue-100 dark:bg-blue-950/50">
                                                                <div class="h-full rounded-full bg-gradient-to-r from-blue-500 via-indigo-500 to-blue-600 transition-all duration-300" :style="'width: ' + Math.max(progress, 8) + '%'"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif
                                                <flux:error name="signerIdDocuments.{{ $index }}" />
                                            </div>
                                        </div>
                                    </details>
                                @empty
                                    <div class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50 px-6 py-10 text-center dark:border-zinc-700 dark:bg-zinc-950/30">
                                        <flux:icon.user-plus class="mx-auto size-10 text-zinc-400" />
                                        <p class="mt-3 text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('No client or signer added yet') }}</p>
                                        <p class="mt-1 text-xs text-zinc-500">{{ __('You can skip and add them later on the workflow page.') }}</p>
                                        <flux:button type="button" class="mt-4" variant="outline" icon="plus" wire:click="addSignerRow">{{ __('Add client / signer') }}</flux:button>
                                    </div>
                                @endforelse
                                <flux:error name="signers" />
                            </div>
                        </div>
                    @endif

                    @if ($wizardStep === 3)
                        <div class="space-y-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h2 class="text-base font-semibold uppercase tracking-[0.16em] text-zinc-700 dark:text-zinc-300">{{ __('Witness') }}</h2>
                                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Add witnesses if the notarial act requires them.') }}</p>
                                </div>
                                <flux:button type="button" variant="outline" size="sm" icon="plus" wire:click="addWitnessRow">{{ __('Add witness') }}</flux:button>
                            </div>

                            <div class="space-y-4">
                                @forelse ($witnesses as $index => $witness)
                                    <details class="group rounded-xl border border-zinc-200 bg-zinc-50/70 dark:border-zinc-700 dark:bg-zinc-950/30" wire:key="witness-{{ $index }}" @if ($openWitnessPanels[$index] ?? $loop->first) open @endif>
                                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 marker:content-none">
                                            <span class="truncate text-sm font-semibold text-zinc-800 dark:text-zinc-200">{{ ! empty($witnesses[$index]['full_name']) ? $witnesses[$index]['full_name'] : __('Witness :n', ['n' => $index + 1]) }}</span>
                                            <span class="flex items-center gap-2" x-on:click.stop>
                                                <flux:button type="button" size="sm" variant="ghost" icon="trash" wire:click="removeWitnessRow({{ $index }})">{{ __('Remove') }}</flux:button>
                                                <flux:icon.chevron-down class="size-4 text-zinc-400 transition group-open:rotate-180" />
                                            </span>
                                        </summary>
                                        <div class="grid gap-4 border-t border-zinc-200 px-5 pb-5 pt-4 md:grid-cols-2 xl:grid-cols-3 dark:border-zinc-700">
                                            <flux:field>
                                                <flux:label>{{ __('Full name') }}</flux:label>
                                                <flux:input type="text" wire:model.blur="witnesses.{{ $index }}.full_name" placeholder="{{ __('Maria Santos') }}" />
                                                <flux:error name="witnesses.{{ $index }}.full_name" />
                                            </flux:field>
                                            <flux:field>
                                                <flux:label>{{ __('Email') }}</flux:label>
                                                <flux:input type="email" wire:model="witnesses.{{ $index }}.email" placeholder="{{ __('maria@example.com') }}" />
                                                <flux:error name="witnesses.{{ $index }}.email" />
                                            </flux:field>
                                            <flux:field>
                                                <flux:label>{{ __('Phone') }}</flux:label>
                                                <flux:input type="text" wire:model="witnesses.{{ $index }}.phone" placeholder="{{ __('+63 9XX XXX XXXX') }}" />
                                                <flux:error name="witnesses.{{ $index }}.phone" />
                                            </flux:field>
                                            <flux:field class="md:col-span-2">
                                                <flux:label>{{ __('Address') }}</flux:label>
                                                <flux:input type="text" wire:model="witnesses.{{ $index }}.address" placeholder="{{ __('For identity verification') }}" />
                                                <flux:error name="witnesses.{{ $index }}.address" />
                                            </flux:field>
                                            <div>
                                                <flux:select wire:model="witnesses.{{ $index }}.witnessed_signer_row_index" label="{{ __('Witness for') }}" placeholder="{{ __('Select client / signer') }}">
                                                    @foreach ($signers as $signerIndex => $signer)
                                                        @if (trim((string) ($signer['full_name'] ?? '')) !== '' || trim((string) ($signer['email'] ?? '')) !== '')
                                                            <flux:select.option value="{{ $signerIndex }}">
                                                                {{ trim((string) ($signer['full_name'] ?? '')) !== '' ? $signer['full_name'] : __('Signer :n', ['n' => $signerIndex + 1]) }}
                                                                @if (trim((string) ($signer['email'] ?? '')) !== '')
                                                                    · {{ $signer['email'] }}
                                                                @endif
                                                            </flux:select.option>
                                                        @endif
                                                    @endforeach
                                                </flux:select>
                                                <flux:description>{{ __('Optional, but helps show which signer this witness supports.') }}</flux:description>
                                                <flux:error name="witnesses.{{ $index }}.witnessed_signer_row_index" />
                                            </div>
                                            <input type="hidden" wire:model="witnesses.{{ $index }}.role" value="witness" />
                                        </div>
                                    </details>
                                @empty
                                    <div class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50 px-6 py-10 text-center dark:border-zinc-700 dark:bg-zinc-950/30">
                                        <flux:icon.user-group class="mx-auto size-10 text-zinc-400" />
                                        <p class="mt-3 text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('No witness added') }}</p>
                                        <p class="mt-1 text-xs text-zinc-500">{{ __('Witnesses are optional and can be added later.') }}</p>
                                        <flux:button type="button" class="mt-4" variant="outline" icon="plus" wire:click="addWitnessRow">{{ __('Add witness') }}</flux:button>
                                    </div>
                                @endforelse
                                <flux:error name="witnesses" />
                            </div>
                        </div>
                    @endif

                    @if ($wizardStep === 4)
                        @php
                            $completedSignerRows = collect($signers)->filter(
                                fn (array $signer): bool => trim((string) ($signer['full_name'] ?? '')) !== '' && trim((string) ($signer['email'] ?? '')) !== '',
                            );
                            $completedWitnessRows = collect($witnesses)->filter(
                                fn (array $witness): bool => trim((string) ($witness['full_name'] ?? '')) !== '' && trim((string) ($witness['email'] ?? '')) !== '',
                            );
                            $attachedSignerIds = collect($signerIdDocuments)->filter()->count();
                            $caseTitle = trim($title) !== '' ? $title : __('Untitled case');
                            $actLabel = __(ucfirst(str_replace('_', ' ', $requestType)));
                        @endphp

                        <div class="space-y-6">
                            <div class="overflow-hidden rounded-2xl border border-blue-200 bg-gradient-to-br from-blue-50 via-white to-indigo-50 shadow-sm dark:border-blue-900/40 dark:from-blue-950/20 dark:via-zinc-900 dark:to-indigo-950/20">
                                <div class="p-5 sm:p-6">
                                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                        <div class="min-w-0">
                                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700 dark:text-blue-300">{{ __('Final Review') }}</p>
                                            <h2 class="mt-2 text-2xl font-bold tracking-tight text-zinc-950 dark:text-white">{{ $caseTitle }}</h2>
                                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                                <flux:badge size="sm" color="blue">{{ $actLabel }}</flux:badge>
                                                <flux:badge size="sm" color="{{ $caseDocument ? 'emerald' : 'zinc' }}">
                                                    {{ $caseDocument ? __('PDF attached') : __('PDF later') }}
                                                </flux:badge>
                                                <flux:badge size="sm" color="{{ $completedSignerRows->isNotEmpty() ? 'emerald' : 'zinc' }}">
                                                    {{ trans_choice(':count signer|:count signers', $completedSignerRows->count(), ['count' => $completedSignerRows->count()]) }}
                                                </flux:badge>
                                                <flux:badge size="sm" color="{{ $completedWitnessRows->isNotEmpty() ? 'emerald' : 'zinc' }}">
                                                    {{ trans_choice(':count witness|:count witnesses', $completedWitnessRows->count(), ['count' => $completedWitnessRows->count()]) }}
                                                </flux:badge>
                                            </div>
                                        </div>

                                        <div class="rounded-2xl border border-white/70 bg-white/80 p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950/70 lg:min-w-64">
                                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-zinc-500">{{ __('Readiness') }}</p>
                                            <div class="mt-3 space-y-2 text-sm">
                                                <div class="flex items-center gap-2">
                                                    <flux:icon.check-circle class="size-4 text-emerald-600 dark:text-emerald-400" />
                                                    <span class="text-zinc-700 dark:text-zinc-300">{{ __('Case title ready') }}</span>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <flux:icon.check-circle class="size-4 text-emerald-600 dark:text-emerald-400" />
                                                    <span class="text-zinc-700 dark:text-zinc-300">{{ __('Notarial act selected') }}</span>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    @if ($caseDocument)
                                                        <flux:icon.check-circle class="size-4 text-emerald-600 dark:text-emerald-400" />
                                                    @else
                                                        <flux:icon.clock class="size-4 text-zinc-400" />
                                                    @endif
                                                    <span class="text-zinc-700 dark:text-zinc-300">{{ $caseDocument ? __('Document attached') : __('Document can be added later') }}</span>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    @if ($completedSignerRows->isNotEmpty())
                                                        <flux:icon.check-circle class="size-4 text-emerald-600 dark:text-emerald-400" />
                                                    @else
                                                        <flux:icon.clock class="size-4 text-zinc-400" />
                                                    @endif
                                                    <span class="text-zinc-700 dark:text-zinc-300">{{ $completedSignerRows->isNotEmpty() ? __('Party info started') : __('Parties can be added later') }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-4 lg:grid-cols-2">
                                <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Case Info') }}</p>
                                            <h3 class="mt-1 font-semibold text-zinc-950 dark:text-white">{{ $caseTitle }}</h3>
                                        </div>
                                        <flux:button type="button" size="sm" variant="ghost" wire:click="goToWizardStep(1)">{{ __('Edit') }}</flux:button>
                                    </div>
                                    <dl class="mt-4 space-y-3 text-sm">
                                        <div class="flex justify-between gap-4">
                                            <dt class="text-zinc-500">{{ __('Notarial act') }}</dt>
                                            <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $actLabel }}</dd>
                                        </div>
                                        <div class="flex justify-between gap-4">
                                            <dt class="text-zinc-500">{{ __('Document') }}</dt>
                                            <dd class="max-w-56 truncate font-medium text-zinc-900 dark:text-zinc-100">{{ $caseDocument ? $caseDocument->getClientOriginalName() : __('Not uploaded') }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-zinc-500">{{ __('Notes') }}</dt>
                                            <dd class="mt-1 rounded-xl bg-zinc-50 p-3 text-zinc-700 dark:bg-zinc-950/50 dark:text-zinc-300">{{ $remarks !== '' ? $remarks : __('No internal notes added.') }}</dd>
                                        </div>
                                    </dl>
                                </section>

                                <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Next after creation') }}</p>
                                            <h3 class="mt-1 font-semibold text-zinc-950 dark:text-white">{{ __('Guided workflow page') }}</h3>
                                        </div>
                                        <flux:icon.arrow-path-rounded-square class="size-5 text-blue-600 dark:text-blue-400" />
                                    </div>
                                    <ol class="mt-4 space-y-3 text-sm">
                                        @foreach ([__('Prepare document'), __('Confirm parties'), __('Send for signing'), __('Video verification'), __('Payment and register'), __('Seal and complete')] as $nextStep)
                                            <li class="flex items-center gap-2 text-zinc-700 dark:text-zinc-300">
                                                <span class="size-1.5 rounded-full bg-blue-500"></span>
                                                {{ $nextStep }}
                                            </li>
                                        @endforeach
                                    </ol>
                                </section>
                            </div>

                            <div class="grid gap-4 lg:grid-cols-2">
                                <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Client / Signer') }}</p>
                                            <h3 class="mt-1 font-semibold text-zinc-950 dark:text-white">
                                                {{ trans_choice(':count party ready|:count parties ready', $completedSignerRows->count(), ['count' => $completedSignerRows->count()]) }}
                                            </h3>
                                        </div>
                                        <flux:button type="button" size="sm" variant="ghost" wire:click="goToWizardStep(2)">{{ __('Edit') }}</flux:button>
                                    </div>
                                    <div class="mt-4 space-y-3">
                                        @forelse ($signers as $signerIndex => $signer)
                                            <div class="rounded-xl border border-zinc-100 bg-zinc-50 p-3 text-sm dark:border-zinc-800 dark:bg-zinc-950/50">
                                                <div class="flex flex-wrap items-start justify-between gap-2">
                                                    <div class="min-w-0">
                                                        <p class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $signer['full_name'] ?: __('Unnamed signer') }}</p>
                                                        <p class="truncate text-xs text-zinc-500">{{ $signer['email'] ?? __('No email yet') }}</p>
                                                    </div>
                                                    <flux:badge size="sm" color="zinc">{{ __(ucfirst((string) ($signer['role'] ?? 'signer'))) }}</flux:badge>
                                                </div>
                                                <div class="mt-3 flex flex-wrap gap-2">
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-white px-2 py-1 text-xs text-zinc-600 ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-zinc-300 dark:ring-zinc-700">
                                                        <flux:icon.phone class="size-3" />
                                                        {{ trim((string) ($signer['phone'] ?? '')) !== '' ? $signer['phone'] : __('No phone') }}
                                                    </span>
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-white px-2 py-1 text-xs text-zinc-600 ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-zinc-300 dark:ring-zinc-700">
                                                        <flux:icon.identification class="size-3" />
                                                        {{ ($signerIdDocuments[$signerIndex] ?? null) ? __('ID attached') : __('ID later') }}
                                                    </span>
                                                </div>
                                                @if ($signerIdDocuments[$signerIndex] ?? null)
                                                    <p class="mt-2 truncate text-xs text-blue-600 dark:text-blue-400">
                                                        {{ $signerIdDocuments[$signerIndex]->getClientOriginalName() }}
                                                    </p>
                                                @endif
                                            </div>
                                        @empty
                                            <div class="rounded-xl border border-dashed border-zinc-300 px-4 py-6 text-center dark:border-zinc-700">
                                                <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('No client/signer added yet') }}</p>
                                                <p class="mt-1 text-xs text-zinc-500">{{ __('You can add parties on the workflow page after creating the case.') }}</p>
                                            </div>
                                        @endforelse
                                    </div>
                                </section>

                                <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Witness') }}</p>
                                            <h3 class="mt-1 font-semibold text-zinc-950 dark:text-white">
                                                {{ trans_choice(':count witness ready|:count witnesses ready', $completedWitnessRows->count(), ['count' => $completedWitnessRows->count()]) }}
                                            </h3>
                                        </div>
                                        <flux:button type="button" size="sm" variant="ghost" wire:click="goToWizardStep(3)">{{ __('Edit') }}</flux:button>
                                    </div>
                                    <div class="mt-4 space-y-3">
                                        @forelse ($witnesses as $witness)
                                            @php
                                                $witnessedSigner = isset($witness['witnessed_signer_row_index'])
                                                    ? ($signers[(int) $witness['witnessed_signer_row_index']] ?? null)
                                                    : null;
                                            @endphp
                                            <div class="rounded-xl border border-zinc-100 bg-zinc-50 p-3 text-sm dark:border-zinc-800 dark:bg-zinc-950/50">
                                                <p class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $witness['full_name'] ?: __('Unnamed witness') }}</p>
                                                <p class="truncate text-xs text-zinc-500">{{ $witness['email'] ?? __('No email yet') }}</p>
                                                @if ($witnessedSigner !== null)
                                                    <p class="mt-2 inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-blue-100 dark:bg-blue-950/30 dark:text-blue-300 dark:ring-blue-900/40">
                                                        <flux:icon.link class="size-3" />
                                                        {{ __('Witness for: :name', ['name' => $witnessedSigner['full_name'] ?: ($witnessedSigner['email'] ?? __('Selected signer'))]) }}
                                                    </p>
                                                @else
                                                    <p class="mt-2 text-xs text-zinc-500">{{ __('No linked signer selected.') }}</p>
                                                @endif
                                            </div>
                                        @empty
                                            <div class="rounded-xl border border-dashed border-zinc-300 px-4 py-6 text-center dark:border-zinc-700">
                                                <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('No witness added') }}</p>
                                                <p class="mt-1 text-xs text-zinc-500">{{ __('Witnesses are optional and can be added later.') }}</p>
                                            </div>
                                        @endforelse
                                    </div>
                                </section>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 px-4 py-4 dark:border-zinc-800 sm:px-6">
                    @if ($wizardStep === 1)
                        <flux:button variant="ghost" :href="route('notary.requests.index')" wire:navigate icon="arrow-left">{{ __('Back') }}</flux:button>
                    @else
                        <flux:button type="button" variant="ghost" wire:click="previousStep" icon="arrow-left">{{ __('Back') }}</flux:button>
                    @endif

                    <div class="flex flex-wrap items-center gap-3">
                        @if ($wizardStep === 2)
                            <flux:button type="button" variant="outline" wire:click="skipPartiesStep">{{ __('Skip client/signer') }}</flux:button>
                        @endif
                        @if ($wizardStep === 3)
                            <flux:button type="button" variant="outline" wire:click="skipWitnessStep">{{ __('Skip witness') }}</flux:button>
                        @endif

                        @if ($wizardStep < 4)
                            <flux:button
                                type="button"
                                variant="primary"
                                wire:click="nextStep"
                                wire:loading.attr="disabled"
                                wire:target="nextStep,caseDocument"
                                icon="arrow-right"
                                icon:trailing
                                :disabled="! ($practiceEligibility['allowed'] ?? true)"
                            >
                                {{ __('Continue') }}
                            </flux:button>
                        @else
                            <flux:button
                                type="submit"
                                variant="primary"
                                wire:loading.attr="disabled"
                                wire:target="save,caseDocument"
                                icon="check"
                                :disabled="! ($practiceEligibility['allowed'] ?? true)"
                            >
                                <span wire:loading.remove wire:target="save">{{ __('Create Notary Case') }}</span>
                                <span wire:loading wire:target="save">{{ __('Creating...') }}</span>
                            </flux:button>
                        @endif
                    </div>
                </div>
            </section>
        @else
            <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 sm:p-8">
                <flux:heading size="lg">{{ __('Notarization details') }}</flux:heading>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Required details to open the notarization matter.') }}</p>

                <div class="mt-6 space-y-6">
                    <flux:field>
                        <flux:label>{{ __('Case title') }} <span class="text-rose-500">*</span></flux:label>
                        <flux:input wire:model.live="title" type="text" required placeholder="{{ __('e.g. Deed of Sale — Lot 5, Block 2, Greenfield Subd.') }}" />
                        <flux:error name="title" />
                    </flux:field>

                    <div class="grid gap-5 lg:grid-cols-2">
                        <div>
                            <flux:select wire:model.live="requestType" label="{{ __('Notarial act type') }}" required>
                                @foreach (config('docutrust.notary.notarial_act_types', []) as $act)
                                    <flux:select.option value="{{ $act }}">{{ __(ucfirst(str_replace('_', ' ', $act))) }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="requestType" />
                        </div>
                        <div>
                            <flux:select wire:model="notaryUserId" label="{{ __('Assign attorney') }}" placeholder="{{ __('Select an attorney...') }}">
                                @foreach ($availableNotaries as $notary)
                                    <flux:select.option value="{{ $notary->id }}">{{ $notary->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="notaryUserId" />
                        </div>
                    </div>

                    <flux:field>
                        <flux:label>{{ __('Remarks') }} <span class="text-xs font-normal text-zinc-400">{{ __('Optional') }}</span></flux:label>
                        <flux:textarea wire:model="remarks" rows="3" placeholder="{{ __('Instructions or context for the attorney...') }}" />
                        <flux:error name="remarks" />
                    </flux:field>
                </div>

                <div class="mt-8 flex justify-end gap-3 border-t border-zinc-200 pt-6 dark:border-zinc-800">
                    <flux:button variant="ghost" :href="route('notary-requests.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save" icon="check">
                        <span wire:loading.remove wire:target="save">{{ __('Create notarization') }}</span>
                        <span wire:loading wire:target="save">{{ __('Creating...') }}</span>
                    </flux:button>
                </div>
            </section>
        @endif
    </form>
</x-admin.page>
