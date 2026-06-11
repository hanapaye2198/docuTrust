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
     * @var list<array{full_name: string, email: string, phone: string, address: string, role: string}>
     */
    public array $signers = [];

    public function mount(): void
    {
        $this->signers = [];
    }

    public function totalWizardSteps(): int
    {
        return Auth::user()?->role === UserRole::Notary ? 3 : 1;
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
        $this->signers[] = [
            'full_name' => '',
            'email' => '',
            'phone' => '',
            'address' => '',
            'role' => 'signer',
        ];
    }

    public function removeSignerRow(int $index): void
    {
        unset($this->signers[$index]);
        $this->signers = array_values($this->signers);
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
        if ($this->wizardStep !== 2) {
            return;
        }

        $this->validateWizardStep(1);
        $this->wizardStep = 3;
        $this->maxWizardStepReached = max($this->maxWizardStepReached, 3);
    }

    public function skipPartiesStep(): void
    {
        $this->signers = [];
        $this->save();
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

            return;
        }

        if (! $isNotary) {
            return;
        }

        if ($step === 2) {
            if ($this->caseDocument !== null) {
                $this->validate([
                    'caseDocument' => ['file', 'mimes:pdf', 'max:10240'],
                ]);
            }

            return;
        }

        if ($step === 3) {
            $this->validateSignerRows();
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
    }

    /**
     * @return list<array{full_name: string, email: string, phone: string, address: string, role: string}>
     */
    protected function normalizedSigners(): array
    {
        $normalized = [];

        foreach ($this->signers as $index => $signer) {
            $fullName = trim((string) ($signer['full_name'] ?? ''));
            $email = trim((string) ($signer['email'] ?? ''));
            $phone = trim((string) ($signer['phone'] ?? ''));
            $address = trim((string) ($signer['address'] ?? ''));
            $role = trim((string) ($signer['role'] ?? '')) !== '' ? trim((string) ($signer['role'] ?? '')) : 'signer';

            $hasAny = $fullName !== '' || $email !== '' || $phone !== '' || $address !== '';
            $hasAll = $fullName !== '' && $email !== '';

            if (! $hasAny) {
                continue;
            }

            if (! $hasAll) {
                throw ValidationException::withMessages([
                    "signers.{$index}.full_name" => __('Enter both full name and email for each party, or leave the row empty.'),
                ]);
            }

            $normalized[] = [
                'full_name' => $fullName,
                'email' => $email,
                'phone' => $phone,
                'address' => $address,
                'role' => $role,
            ];
        }

        return $normalized;
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

        return [
            [
                'id' => 1,
                'label' => __('Case details'),
                'description' => __('Title and notarial act type'),
                'complete' => trim($this->title) !== '',
                'optional' => false,
                'current' => $this->wizardStep === 1,
            ],
            [
                'id' => 2,
                'label' => __('Document'),
                'description' => __('PDF instrument (optional)'),
                'complete' => $this->caseDocument !== null,
                'optional' => true,
                'current' => $this->wizardStep === 2,
            ],
            [
                'id' => 3,
                'label' => __('Parties'),
                'description' => __('Signers and witnesses (optional)'),
                'complete' => $hasSigner,
                'optional' => true,
                'current' => $this->wizardStep === 3,
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

        if ($isNotary && ! empty($validated['signers'])) {
            foreach ($validated['signers'] as $signerRow) {
                if (trim((string) ($signerRow['full_name'] ?? '')) === '') {
                    continue;
                }
                NotarySigner::query()->create([
                    'notary_request_id' => $request->id,
                    'full_name' => trim((string) $signerRow['full_name']),
                    'email' => strtolower(trim((string) $signerRow['email'])),
                    'phone' => trim((string) ($signerRow['phone'] ?? '')) !== '' ? trim((string) $signerRow['phone']) : null,
                    'address' => trim((string) ($signerRow['address'] ?? '')) !== '' ? trim((string) $signerRow['address']) : null,
                    'role' => trim((string) ($signerRow['role'] ?? '')) !== '' ? trim((string) $signerRow['role']) : 'signer',
                ]);
            }
        }

        if ($document !== null) {
            app(NotaryParticipantSyncService::class)->syncRequestSignersToDocument($document);
        }

        if ($isNotary) {
            if ($document !== null) {
                session()->flash('status', __('Case created with your PDF. Use Prepare signature fields on the case page to continue.'));
            } else {
                session()->flash('status', __('Case opened. Upload your PDF and add parties from the case page when ready.'));
            }
        } else {
            session()->flash('status', __('Notarization created. The assigned attorney will upload documents and manage the signing process.'));
        }

        $showRoute = $isNotary ? 'notary.requests.show' : 'notary-requests.show';

        $this->redirect(route($showRoute, $request, absolute: false), navigate: true);
    }
}; ?>

<x-admin.page class="h-full flex-1" gap="gap-6">

    <div class="flex flex-col gap-4 border-b border-zinc-200/90 pb-5 dark:border-zinc-800 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-3xl">
                {{ __('New notarization') }}
            </h1>
            <p class="mt-1 max-w-3xl text-sm text-zinc-500 dark:text-zinc-400">
                @if ($isNotaryView)
                    {{ __('Only the case title is required. Add a PDF and parties now or on the next page.') }}
                @else
                    {{ __('Submit the matter details. Your attorney will upload documents and manage signing.') }}
                @endif
            </p>
        </div>
        <flux:button
            variant="ghost"
            :href="$isNotaryView ? route('notary.requests.index') : route('notary-requests.index')"
            wire:navigate
            icon="arrow-left"
        >
            {{ __('Back to notarizations') }}
        </flux:button>
    </div>

    @if ($isNotaryView && ! ($practiceEligibility['allowed'] ?? true))
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.heading>{{ __('Attorney practice not enabled') }}</flux:callout.heading>
            <flux:callout.text>{{ $practiceEligibility['message'] ?? __('Complete your attorney application before opening new cases.') }}</flux:callout.text>
        </flux:callout>
    @endif

    <form wire:submit="save" class="grid items-start gap-8 lg:grid-cols-12">

        <div class="min-w-0 space-y-6 lg:col-span-8 xl:col-span-9">

            @if ($isNotaryView)
                {{-- Wizard step indicator --}}
                <nav aria-label="{{ __('Case setup steps') }}" class="ui-panel p-4 sm:p-5">
                    <ol class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        @foreach ($this->wizardSteps as $step)
                            <li class="flex min-w-0 flex-1 items-center gap-3">
                                <button
                                    type="button"
                                    wire:click="goToWizardStep({{ $step['id'] }})"
                                    @disabled($step['id'] > $maxWizardStepReached)
                                    @class([
                                        'flex min-w-0 flex-1 items-center gap-3 rounded-xl px-3 py-2 text-left transition',
                                        'bg-teal-50 ring-1 ring-teal-200/80 dark:bg-teal-950/40 dark:ring-teal-800/60' => $step['current'],
                                        'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' => ! $step['current'] && $step['id'] <= $maxWizardStepReached,
                                        'cursor-not-allowed opacity-50' => $step['id'] > $maxWizardStepReached,
                                    ])
                                >
                                    <span @class([
                                        'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                                        'bg-teal-600 text-white' => $step['current'],
                                        'bg-emerald-600 text-white' => $step['complete'] && ! $step['current'],
                                        'bg-zinc-200 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300' => ! $step['complete'] && ! $step['current'],
                                    ])>
                                        @if ($step['complete'] && ! $step['current'])
                                            <flux:icon.check class="size-4" />
                                        @else
                                            {{ $step['id'] }}
                                        @endif
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $step['label'] }}</span>
                                        <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ ($step['optional'] ?? false) ? __('Optional') : __('Required') }}
                                        </span>
                                    </span>
                                </button>
                                @if (! $loop->last)
                                    <span class="hidden h-px flex-1 bg-zinc-200 dark:bg-zinc-700 sm:block" aria-hidden="true"></span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </nav>

                {{-- Step 1: Case details --}}
                @if ($wizardStep === 1)
                    <section class="ui-panel p-6 sm:p-8">
                        <flux:heading size="lg">{{ __('Step 1: Case details') }}</flux:heading>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Required to open the draft case.') }}</p>

                        <div class="mt-6 space-y-6">
                            <flux:field>
                                <flux:label>{{ __('Case title') }} <span class="text-rose-500">*</span></flux:label>
                                <flux:input wire:model.live="title" type="text" required placeholder="{{ __('e.g. Deed of Sale — Lot 5, Block 2, Greenfield Subd.') }}" />
                                <flux:description>{{ __('Shown on your notarization list and case page.') }}</flux:description>
                                <flux:error name="title" />
                            </flux:field>

                            <div>
                                <flux:select wire:model.live="requestType" label="{{ __('Notarial act type') }}" required>
                                    @foreach (config('docutrust.notary.notarial_act_types', []) as $act)
                                        <flux:select.option value="{{ $act }}">{{ __(ucfirst(str_replace('_', ' ', $act))) }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                @if (! empty($notarialActHints[$requestType] ?? null))
                                    <p class="mt-2 text-sm text-teal-700 dark:text-teal-300">{{ $notarialActHints[$requestType] }}</p>
                                @endif
                                <flux:error name="requestType" />
                            </div>

                            <details class="group rounded-xl border border-zinc-200/80 bg-zinc-50/50 dark:border-zinc-700/60 dark:bg-zinc-800/30">
                                <summary class="cursor-pointer list-none px-5 py-4 marker:content-none">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">{{ __('Internal notes') }}</span>
                                        <flux:badge size="sm" color="zinc">{{ __('Optional') }}</flux:badge>
                                        <flux:icon.chevron-down class="size-4 text-zinc-400 transition group-open:rotate-180" />
                                    </div>
                                    <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Deadlines, file references, or register context — not shown to signers.') }}</p>
                                </summary>
                                <div class="border-t border-zinc-200/80 px-5 pb-5 pt-4 dark:border-zinc-700/60">
                                    <flux:textarea wire:model="remarks" rows="3" placeholder="{{ __('e.g. Rush — client needs copy by Friday.') }}" />
                                    <flux:error name="remarks" />
                                </div>
                            </details>
                        </div>

                        <div class="mt-8 flex flex-wrap items-center justify-end gap-3 border-t border-zinc-200/80 pt-6 dark:border-zinc-700/60">
                            <flux:button
                                type="button"
                                variant="primary"
                                wire:click="nextStep"
                                wire:loading.attr="disabled"
                                wire:target="nextStep"
                                icon="arrow-right"
                                icon:trailing
                                :disabled="! ($practiceEligibility['allowed'] ?? true)"
                            >
                                {{ __('Continue to document') }}
                            </flux:button>
                        </div>
                    </section>
                @endif

                {{-- Step 2: Document --}}
                @if ($wizardStep === 2)
                    <section class="ui-panel p-6 sm:p-8">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <flux:heading size="lg">{{ __('Step 2: Document') }}</flux:heading>
                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Attach the PDF now or upload it on the case page after you open the case.') }}</p>
                            </div>
                            <flux:badge size="sm" color="zinc">{{ __('Optional') }}</flux:badge>
                        </div>

                        <details class="group mt-4 rounded-xl border border-zinc-200/80 bg-zinc-50/30 dark:border-zinc-700/60 dark:bg-zinc-900/30">
                            <summary class="cursor-pointer list-none px-4 py-3 marker:content-none">
                                <span class="flex items-center gap-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                    <flux:icon.information-circle class="size-4 shrink-0 text-teal-600 dark:text-teal-400" />
                                    {{ __('When should I upload here?') }}
                                    <flux:icon.chevron-down class="ml-auto size-4 text-zinc-400 transition group-open:rotate-180" />
                                </span>
                            </summary>
                            <p class="border-t border-zinc-200/80 px-4 pb-4 pt-3 text-sm text-zinc-600 dark:border-zinc-700/60 dark:text-zinc-400">
                                {{ __('Upload now if you already have the final PDF. Otherwise skip — you can upload from the case page, then use Prepare signature fields before sending to signers.') }}
                            </p>
                        </details>

                        <div class="mt-6">
                            @if ($caseDocument)
                                <div class="flex items-center gap-3 rounded-xl border border-emerald-200/80 bg-emerald-50/80 px-4 py-3 dark:border-emerald-900/40 dark:bg-emerald-950/25">
                                    <flux:icon.document-text class="size-8 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-emerald-900 dark:text-emerald-100">{{ $caseDocument->getClientOriginalName() }}</p>
                                        <p class="text-xs text-emerald-700 dark:text-emerald-300">{{ __('Next on case page: Prepare signature fields') }}</p>
                                    </div>
                                    <flux:button type="button" size="sm" variant="ghost" wire:click="removeCaseDocument" wire:loading.attr="disabled" wire:target="removeCaseDocument">
                                        {{ __('Remove') }}
                                    </flux:button>
                                </div>
                            @else
                                <div
                                    class="relative overflow-hidden rounded-3xl border-2 border-dashed border-teal-300 bg-gradient-to-br from-teal-50 via-white to-emerald-50 p-8 text-center shadow-[0_18px_48px_-28px_rgba(13,148,136,0.45)] transition dark:border-teal-700/70 dark:bg-gradient-to-br dark:from-teal-950/40 dark:via-zinc-900 dark:to-emerald-950/20"
                                    x-data="{ progress: 0 }"
                                    x-on:livewire-upload-start="progress = 0"
                                    x-on:livewire-upload-finish="progress = 0"
                                    x-on:livewire-upload-error="progress = 0"
                                    x-on:livewire-upload-progress="progress = $event.detail.progress"
                                    x-on:dragover.prevent="$el.classList.add('border-teal-500', 'bg-teal-50/80', 'dark:border-teal-400')"
                                    x-on:dragleave.prevent="$el.classList.remove('border-teal-500', 'bg-teal-50/80', 'dark:border-teal-400')"
                                    x-on:drop.prevent="
                                        $el.classList.remove('border-teal-500', 'bg-teal-50/80', 'dark:border-teal-400');
                                        if ($event.dataTransfer.files.length) {
                                            $refs.casePdf.files = $event.dataTransfer.files;
                                            $refs.casePdf.dispatchEvent(new Event('change', { bubbles: true }));
                                        }
                                    "
                                >
                                    <div class="pointer-events-none absolute inset-x-6 top-4 flex justify-center">
                                        <span class="inline-flex items-center gap-2 rounded-full border border-teal-200 bg-white/90 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.24em] text-teal-700 shadow-sm dark:border-teal-800/60 dark:bg-zinc-900/80 dark:text-teal-300">
                                            <flux:icon.sparkles class="size-3.5" />
                                            {{ __('Recommended if your PDF is ready') }}
                                        </span>
                                    </div>
                                    <flux:icon.document-text class="mx-auto mt-8 size-12 text-teal-600 dark:text-teal-400" />
                                    <p class="mt-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Upload your PDF now') }}</p>
                                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ __('Tap the button below or drag the file here. We will prepare it for signature fields next.') }}</p>
                                    <p class="mt-1 text-xs text-zinc-500">{{ __('PDF only · max 10 MB') }}</p>
                                    <div class="mt-5">
                                        <flux:button
                                            type="button"
                                            variant="primary"
                                            icon="arrow-up-tray"
                                            class="h-12 min-w-[220px] text-base font-semibold shadow-[0_14px_28px_-16px_rgba(13,148,136,0.65)] sm:min-w-[260px]"
                                            x-on:click="$refs.casePdf.click()"
                                        >
                                            {{ __('Choose PDF File') }}
                                        </flux:button>
                                        <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Visible upload button for first-time users') }}</p>
                                        <input
                                            x-ref="casePdf"
                                            type="file"
                                            wire:model="caseDocument"
                                            accept="application/pdf,.pdf"
                                            class="sr-only"
                                        />
                                    </div>
                                    <div wire:loading wire:target="caseDocument" class="mx-auto mt-6 max-w-md rounded-2xl border border-teal-200 bg-white/90 p-4 text-left shadow-sm dark:border-teal-900/50 dark:bg-zinc-950/70">
                                        <div class="flex items-end justify-between gap-4">
                                            <div>
                                                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-teal-700 dark:text-teal-300">{{ __('Upload progress') }}</p>
                                                <p class="mt-1 text-sm font-medium text-zinc-700 dark:text-zinc-200">
                                                    <span x-text="progress > 0 ? '{{ __('Uploading') }}' : '{{ __('Preparing upload') }}'"></span>
                                                </p>
                                            </div>
                                            <div class="text-2xl font-semibold tabular-nums text-teal-700 dark:text-teal-300" x-text="(progress > 0 ? progress : 0) + '%'"></div>
                                        </div>
                                        <div class="mt-3 h-3 overflow-hidden rounded-full bg-teal-100 dark:bg-teal-950/50">
                                            <div class="h-full rounded-full bg-gradient-to-r from-teal-500 via-emerald-500 to-teal-600 transition-all duration-300" :style="'width: ' + Math.max(progress, 8) + '%'"></div>
                                        </div>
                                        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Please keep this page open until the upload finishes.') }}</p>
                                    </div>
                                </div>
                            @endif
                            <flux:error name="caseDocument" />
                        </div>

                        <div class="mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200/80 pt-6 dark:border-zinc-700/60">
                            <flux:button type="button" variant="ghost" wire:click="previousStep" icon="arrow-left">
                                {{ __('Back') }}
                            </flux:button>
                            <div class="flex flex-wrap items-center gap-3">
                                <flux:button type="button" variant="outline" wire:click="skipDocumentStep">
                                    {{ __('Skip for now') }}
                                </flux:button>
                                <flux:button
                                    type="button"
                                    variant="primary"
                                    wire:click="nextStep"
                                    wire:loading.attr="disabled"
                                    wire:target="nextStep,caseDocument"
                                    icon="arrow-right"
                                    icon:trailing
                                >
                                    {{ __('Continue to parties') }}
                                </flux:button>
                            </div>
                        </div>
                    </section>
                @endif

                {{-- Step 3: Parties --}}
                @if ($wizardStep === 3)
                    <section class="ui-panel p-6 sm:p-8">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <flux:heading size="lg">{{ __('Step 3: Parties') }}</flux:heading>
                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Add signers and witnesses now, or invite them from the case page later.') }}</p>
                            </div>
                            <flux:button type="button" size="sm" variant="outline" icon="plus" wire:click="addSignerRow">
                                {{ __('Add party') }}
                            </flux:button>
                        </div>

                        <details class="group mt-4 rounded-xl border border-zinc-200/80 bg-zinc-50/30 dark:border-zinc-700/60 dark:bg-zinc-900/30">
                            <summary class="cursor-pointer list-none px-4 py-3 marker:content-none">
                                <span class="flex items-center gap-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                    <flux:icon.user-group class="size-4 shrink-0 text-teal-600 dark:text-teal-400" />
                                    {{ __('Party tips') }}
                                    <flux:icon.chevron-down class="ml-auto size-4 text-zinc-400 transition group-open:rotate-180" />
                                </span>
                            </summary>
                            <ul class="list-inside list-disc border-t border-zinc-200/80 px-4 pb-4 pt-3 text-sm text-zinc-600 dark:border-zinc-700/60 dark:text-zinc-400">
                                <li>{{ __('Email is used for signing invitations and identity checks.') }}</li>
                                <li>{{ __('Address helps during video verification.') }}</li>
                                <li>{{ __('You can add more parties anytime on the case page.') }}</li>
                            </ul>
                        </details>

                        <div class="mt-6 space-y-4">
                            @forelse ($signers as $index => $signer)
                                <details
                                    class="group rounded-xl border border-zinc-200/80 bg-zinc-50/50 dark:border-zinc-700/60 dark:bg-zinc-800/30"
                                    wire:key="signer-{{ $index }}"
                                    @if ($loop->first) open @endif
                                >
                                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 marker:content-none">
                                        <div class="flex min-w-0 items-center gap-2">
                                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-zinc-900 text-xs font-bold text-white dark:bg-zinc-100 dark:text-zinc-900">{{ $index + 1 }}</span>
                                            <span class="truncate text-sm font-semibold text-zinc-800 dark:text-zinc-200">
                                                {{ ! empty($signers[$index]['full_name']) ? $signers[$index]['full_name'] : __('Party :n', ['n' => $index + 1]) }}
                                            </span>
                                        </div>
                                        <div class="flex shrink-0 items-center gap-2" x-on:click.stop>
                                            <flux:button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                icon="trash"
                                                wire:click="removeSignerRow({{ $index }})"
                                            >
                                                {{ __('Remove') }}
                                            </flux:button>
                                            <flux:icon.chevron-down class="size-4 text-zinc-400 transition group-open:rotate-180" />
                                        </div>
                                    </summary>
                                    <div class="grid gap-4 border-t border-zinc-200/80 px-5 pb-5 pt-4 md:grid-cols-2 xl:grid-cols-3 dark:border-zinc-700/60">
                                        <flux:field class="md:col-span-2 xl:col-span-1">
                                            <flux:label>{{ __('Full name') }}</flux:label>
                                            <flux:input type="text" wire:model.live="signers.{{ $index }}.full_name" placeholder="{{ __('Juan Dela Cruz') }}" />
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
                                            <flux:select.option value="witness">{{ __('Witness') }}</flux:select.option>
                                            <flux:select.option value="affiant">{{ __('Affiant') }}</flux:select.option>
                                            <flux:select.option value="principal">{{ __('Principal') }}</flux:select.option>
                                        </flux:select>
                                    </div>
                                </details>
                            @empty
                                <div class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50/50 px-6 py-10 text-center dark:border-zinc-600 dark:bg-zinc-900/30">
                                    <flux:icon.user-plus class="mx-auto size-10 text-zinc-400" />
                                    <p class="mt-3 text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('No parties added yet') }}</p>
                                    <p class="mt-1 text-xs text-zinc-500">{{ __('You can skip this step and add parties on the case page.') }}</p>
                                    <flux:button type="button" class="mt-4" variant="outline" icon="plus" wire:click="addSignerRow">
                                        {{ __('Add first party') }}
                                    </flux:button>
                                </div>
                            @endforelse
                            <flux:error name="signers" />
                        </div>

                        <div class="mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200/80 pt-6 dark:border-zinc-700/60">
                            <flux:button type="button" variant="ghost" wire:click="previousStep" icon="arrow-left">
                                {{ __('Back') }}
                            </flux:button>
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="flex flex-col items-stretch gap-1 sm:items-end">
                                    <flux:button
                                        type="button"
                                        variant="outline"
                                        wire:click="skipPartiesStep"
                                        wire:loading.attr="disabled"
                                        wire:target="save,skipPartiesStep"
                                        :disabled="! ($practiceEligibility['allowed'] ?? true)"
                                    >
                                        {{ __('Open case now') }}
                                    </flux:button>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Add parties later on the case page') }}</p>
                                </div>
                                <flux:button
                                    type="submit"
                                    variant="primary"
                                    wire:loading.attr="disabled"
                                    wire:target="save,caseDocument,skipPartiesStep"
                                    icon="check"
                                    :disabled="! ($practiceEligibility['allowed'] ?? true)"
                                >
                                    <span wire:loading.remove wire:target="save,skipPartiesStep">{{ __('Open case') }}</span>
                                    <span wire:loading wire:target="save,skipPartiesStep">{{ __('Opening…') }}</span>
                                </flux:button>
                            </div>
                        </div>
                    </section>
                @endif
            @else
                {{-- Admin / non-attorney: single-step form --}}
                <section class="ui-panel p-6 sm:p-8">
                    <flux:heading size="lg">{{ __('Notarization details') }}</flux:heading>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Required details to open the notarization matter.') }}</p>

                    <div class="mt-6 space-y-6">
                        <flux:field>
                            <flux:label>{{ __('Case title') }} <span class="text-rose-500">*</span></flux:label>
                            <flux:input wire:model.live="title" type="text" required placeholder="{{ __('e.g. Deed of Sale — Lot 5, Block 2, Greenfield Subd.') }}" />
                            <flux:description>{{ __('A descriptive name shown on the notarization list and detail page.') }}</flux:description>
                            <flux:error name="title" />
                        </flux:field>

                        <div class="grid gap-5 lg:grid-cols-2">
                            <div>
                                <flux:select wire:model.live="requestType" label="{{ __('Notarial act type') }}" required>
                                    @foreach (config('docutrust.notary.notarial_act_types', []) as $act)
                                        <flux:select.option value="{{ $act }}">{{ __(ucfirst(str_replace('_', ' ', $act))) }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                @if (! empty($notarialActHints[$requestType] ?? null))
                                    <p class="mt-2 text-sm text-teal-700 dark:text-teal-300">{{ $notarialActHints[$requestType] }}</p>
                                @endif
                                <flux:error name="requestType" />
                            </div>
                            <div>
                                <flux:select wire:model="notaryUserId" label="{{ __('Assign attorney') }}" placeholder="{{ __('Select an attorney…') }}">
                                    @foreach ($availableNotaries as $notary)
                                        <flux:select.option value="{{ $notary->id }}">{{ $notary->name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:description>{{ __('Who will upload documents and run the session.') }}</flux:description>
                                <flux:error name="notaryUserId" />
                            </div>
                        </div>

                        <details class="group rounded-xl border border-zinc-200/80 bg-zinc-50/50 dark:border-zinc-700/60 dark:bg-zinc-800/30">
                            <summary class="cursor-pointer list-none px-5 py-4 marker:content-none">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">{{ __('Remarks') }}</span>
                                    <flux:badge size="sm" color="zinc">{{ __('Optional') }}</flux:badge>
                                    <flux:icon.chevron-down class="size-4 text-zinc-400 transition group-open:rotate-180" />
                                </div>
                            </summary>
                            <div class="border-t border-zinc-200/80 px-5 pb-5 pt-4 dark:border-zinc-700/60">
                                <flux:textarea wire:model="remarks" rows="3" placeholder="{{ __('Instructions or context for the attorney…') }}" />
                                <flux:error name="remarks" />
                            </div>
                        </details>
                    </div>
                </section>
            @endif

        </div>

        <aside class="space-y-4 lg:col-span-4 xl:col-span-3 lg:sticky lg:top-4">
            @if ($isNotaryView)
                <div class="ui-panel p-5 sm:p-6">
                    <flux:heading size="lg" class="mb-4">{{ __('Setup progress') }}</flux:heading>
                    <ol class="space-y-3">
                        @foreach ($this->wizardSteps as $step)
                            <li class="flex gap-3">
                                <span @class([
                                    'mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                                    'bg-teal-600 text-white ring-2 ring-teal-200 dark:ring-teal-900' => $step['current'],
                                    'bg-emerald-600 text-white' => $step['complete'] && ! $step['current'],
                                    'bg-zinc-200 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300' => ! $step['complete'] && ! $step['current'],
                                ])>
                                    @if ($step['complete'] && ! $step['current'])
                                        <flux:icon.check class="size-3.5" />
                                    @else
                                        {{ $step['id'] }}
                                    @endif
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $step['label'] }}</p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $step['description'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>

                <div class="ui-panel p-5 sm:p-6">
                    <flux:heading size="lg" class="mb-3">{{ __('Full case workflow') }}</flux:heading>
                    <ol class="space-y-3">
                        @foreach ($this->workflowRoadmap as $roadmapStep)
                            <li class="flex gap-3">
                                <span @class([
                                    'mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full',
                                    'bg-teal-500' => $loop->first,
                                    'bg-zinc-300 dark:bg-zinc-600' => ! $loop->first,
                                ])></span>
                                <div>
                                    <p @class([
                                        'text-sm font-medium',
                                        'text-teal-700 dark:text-teal-300' => $loop->first,
                                        'text-zinc-600 dark:text-zinc-400' => ! $loop->first,
                                    ])>{{ $roadmapStep['label'] }}</p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-500">{{ $roadmapStep['description'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @else
                <div class="ui-panel p-5 sm:p-6">
                    <flux:heading size="lg" class="mb-2">{{ __('What happens next?') }}</flux:heading>
                    <p class="text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">
                        {{ __('Your attorney uploads documents, adds signers, and sends signing links. You will be emailed when it is your turn to sign.') }}
                    </p>
                </div>
            @endif

            @if (! $isNotaryView)
                <div class="ui-panel hidden p-5 sm:p-6 lg:block">
                    <div class="flex flex-col gap-3">
                        <flux:button
                            type="submit"
                            variant="primary"
                            class="w-full"
                            wire:loading.attr="disabled"
                            wire:target="save"
                            icon="check"
                        >
                            <span wire:loading.remove wire:target="save">{{ __('Create notarization') }}</span>
                            <span wire:loading wire:target="save">{{ __('Creating…') }}</span>
                        </flux:button>
                        <flux:button
                            variant="ghost"
                            class="w-full"
                            :href="route('notary-requests.index')"
                            wire:navigate
                        >
                            {{ __('Cancel') }}
                        </flux:button>
                    </div>
                </div>
            @endif
        </aside>

        @if (! $isNotaryView)
            <div class="sticky bottom-4 z-10 ui-panel p-4 shadow-lg lg:col-span-12 lg:hidden">
                <div class="flex items-center justify-end gap-3">
                    <flux:button variant="ghost" :href="route('notary-requests.index')" wire:navigate>
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save" icon="check">
                        <span wire:loading.remove wire:target="save">{{ __('Create notarization') }}</span>
                        <span wire:loading wire:target="save">{{ __('Creating…') }}</span>
                    </flux:button>
                </div>
            </div>
        @endif

    </form>
</x-admin.page>
