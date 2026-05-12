<?php

use App\Enums\NotaryRequestStatus;
use App\Enums\UserRole;
use App\Models\NotaryCredential;
use App\Models\NotaryRequest;
use App\Services\NotarialCertificateService;
use App\Services\NotarialRegisterService;
use App\Services\NotarySealService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public NotaryRequest $notaryRequest;

    public string $documentTitle = '';
    public string $documentDescription = '';
    public string $notarialActType = 'acknowledgment';
    public string $fees = '';
    public string $officialReceiptNumber = '';
    public string $pageNumber = '';
    public string $bookNumber = '';

    /** @var array<int, array{name: string, address: string}> */
    public array $parties = [['name' => '', 'address' => '']];

    /** @var array<int, array{name: string, address: string}> */
    public array $witnesses = [];

    /** @var array<int, array{person_name: string, id_type: string, id_number: string}> */
    public array $competentEvidence = [['person_name' => '', 'id_type' => '', 'id_number' => '']];

    public function mount(NotaryRequest $notaryRequest): void
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->role === UserRole::Notary, 403);
        abort_unless($notaryRequest->notary_user_id === $user->id, 403);
        abort_unless(in_array($notaryRequest->status, [
            NotaryRequestStatus::AttorneyApproved,
            NotaryRequestStatus::Notarized,
        ], true), 403);

        $this->notaryRequest = $notaryRequest;
        $this->documentTitle = $notaryRequest->title;
        $this->notarialActType = $notaryRequest->request_type ?? 'acknowledgment';
    }

    public function addParty(): void
    {
        $this->parties[] = ['name' => '', 'address' => ''];
    }

    public function removeParty(int $index): void
    {
        if (count($this->parties) > 1) {
            unset($this->parties[$index]);
            $this->parties = array_values($this->parties);
        }
    }

    public function addWitness(): void
    {
        $this->witnesses[] = ['name' => '', 'address' => ''];
    }

    public function removeWitness(int $index): void
    {
        unset($this->witnesses[$index]);
        $this->witnesses = array_values($this->witnesses);
    }

    public function addEvidence(): void
    {
        $this->competentEvidence[] = ['person_name' => '', 'id_type' => '', 'id_number' => ''];
    }

    public function removeEvidence(int $index): void
    {
        if (count($this->competentEvidence) > 1) {
            unset($this->competentEvidence[$index]);
            $this->competentEvidence = array_values($this->competentEvidence);
        }
    }

    public function save(): void
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->role === UserRole::Notary, 403);

        $this->validate([
            'documentTitle' => ['required', 'string', 'max:255'],
            'notarialActType' => ['required', 'in:acknowledgment,jurat,affidavit,oath,other'],
            'parties' => ['required', 'array', 'min:1'],
            'parties.*.name' => ['required', 'string', 'max:255'],
            'parties.*.address' => ['required', 'string', 'max:500'],
            'competentEvidence' => ['required', 'array', 'min:1'],
            'competentEvidence.*.person_name' => ['required', 'string', 'max:255'],
            'competentEvidence.*.id_type' => ['required', 'string', 'max:100'],
            'competentEvidence.*.id_number' => ['required', 'string', 'max:100'],
            'fees' => ['nullable', 'numeric', 'min:0'],
            'officialReceiptNumber' => ['nullable', 'string', 'max:100'],
        ]);

        $credential = NotaryCredential::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        if ($credential === null || ! $credential->isActive()) {
            $this->addError('credential', __('You must have an active notary commission to create register entries. Please update your credentials.'));
            return;
        }

        $document = $this->notaryRequest->documents()->first();

        try {
            $entry = app(NotarialRegisterService::class)->createEntry(
                $this->notaryRequest,
                $credential,
                [
                    'document_title' => trim($this->documentTitle),
                    'document_description' => trim($this->documentDescription) !== '' ? trim($this->documentDescription) : null,
                    'parties' => $this->parties,
                    'witnesses' => $this->witnesses,
                    'competent_evidence' => $this->competentEvidence,
                    'notarial_act_type' => $this->notarialActType,
                    'fees' => $this->fees !== '' ? (float) $this->fees : null,
                    'official_receipt_number' => trim($this->officialReceiptNumber) !== '' ? trim($this->officialReceiptNumber) : null,
                    'page_number' => $this->pageNumber !== '' ? (int) $this->pageNumber : null,
                    'book_number' => trim($this->bookNumber) !== '' ? trim($this->bookNumber) : null,
                ],
                $document,
            );

            // Generate QR code
            app(NotarySealService::class)->generateVerificationQrCode($entry);

            // Generate notarial certificate PDF
            app(NotarialCertificateService::class)->generate($entry);

            session()->flash('status', __('Notarial register entry :number created successfully.', [
                'number' => str_pad((string) $entry->entry_number, 3, '0', STR_PAD_LEFT),
            ]));

            $this->redirect(route('notary.requests.show', $this->notaryRequest, absolute: false), navigate: true);
        } catch (\RuntimeException $e) {
            $this->addError('save', $e->getMessage());
        }
    }

    public function with(): array
    {
        $user = Auth::user();
        $credential = NotaryCredential::query()
            ->where('user_id', $user?->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        return [
            'credential' => $credential,
            'existingEntries' => $this->notaryRequest->registerEntries()->with('notaryCredential')->get(),
        ];
    }
}; ?>

<div class="mx-auto flex w-full max-w-5xl flex-col gap-6">
    <header>
        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50 sm:text-3xl">{{ __('Notarial register entry') }}</h1>
        <p class="mt-2 text-zinc-600 dark:text-zinc-400">
            {{ __('Create a notarial register entry for ":title". All 9 required fields must be completed.', ['title' => $notaryRequest->title]) }}
        </p>
    </header>

    @if (session('status'))
        <div class="rounded-2xl border border-emerald-200/90 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-100">
            {{ session('status') }}
        </div>
    @endif

    @error('credential')
        <div class="rounded-2xl border border-red-200/90 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-100">
            {{ $message }}
        </div>
    @enderror

    @error('save')
        <div class="rounded-2xl border border-red-200/90 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-100">
            {{ $message }}
        </div>
    @enderror

    @if ($existingEntries->isNotEmpty())
        <div class="ui-panel p-6">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Existing entries') }}</h2>
            <div class="mt-4 space-y-2">
                @foreach ($existingEntries as $existing)
                    <div class="rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="font-medium text-zinc-800 dark:text-zinc-200">Entry {{ str_pad($existing->entry_number, 3, '0', STR_PAD_LEFT) }}</span>
                                <span class="mx-2 text-zinc-300">•</span>
                                <span class="text-zinc-600 dark:text-zinc-400">{{ $existing->document_title }}</span>
                            </div>
                            <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-medium text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">{{ ucfirst(str_replace('_', ' ', $existing->notarial_act_type)) }}</span>
                        </div>
                        <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $existing->notarized_at?->timezone('Asia/Manila')->format('M j, Y g:i:s A') }} (PHT)</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="ui-panel p-6 sm:p-8">
        <form wire:submit="save" class="space-y-8">
            {{-- Field 1 & 2: Document Title & Description --}}
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('① Document information') }}</h3>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Title and description of document') }}</flux:label>
                        <flux:input wire:model="documentTitle" type="text" required />
                        <flux:error name="documentTitle" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Additional description') }}</flux:label>
                        <flux:input wire:model="documentDescription" type="text" placeholder="{{ __('Optional details') }}" />
                    </flux:field>
                </div>
            </div>

            {{-- Field 3: Parties --}}
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('② Names & address of parties') }}</h3>
                <div class="mt-4 space-y-3">
                    @foreach ($parties as $index => $party)
                        <div class="flex gap-3">
                            <div class="flex-1">
                                <flux:input wire:model="parties.{{ $index }}.name" type="text" placeholder="{{ __('Full name') }}" required />
                            </div>
                            <div class="flex-1">
                                <flux:input wire:model="parties.{{ $index }}.address" type="text" placeholder="{{ __('Complete address') }}" required />
                            </div>
                            @if (count($parties) > 1)
                                <flux:button variant="ghost" type="button" wire:click="removeParty({{ $index }})">✕</flux:button>
                            @endif
                        </div>
                    @endforeach
                    <flux:button variant="outline" size="sm" type="button" wire:click="addParty">{{ __('+ Add party') }}</flux:button>
                </div>
            </div>

            {{-- Field 4: Witnesses --}}
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('③ Names & address of witnesses (if any)') }}</h3>
                <div class="mt-4 space-y-3">
                    @foreach ($witnesses as $index => $witness)
                        <div class="flex gap-3">
                            <div class="flex-1">
                                <flux:input wire:model="witnesses.{{ $index }}.name" type="text" placeholder="{{ __('Full name') }}" />
                            </div>
                            <div class="flex-1">
                                <flux:input wire:model="witnesses.{{ $index }}.address" type="text" placeholder="{{ __('Complete address') }}" />
                            </div>
                            <flux:button variant="ghost" type="button" wire:click="removeWitness({{ $index }})">✕</flux:button>
                        </div>
                    @endforeach
                    <flux:button variant="outline" size="sm" type="button" wire:click="addWitness">{{ __('+ Add witness') }}</flux:button>
                </div>
            </div>

            {{-- Field 5: Competent Evidence of Identities --}}
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('④ Competent evidence of identities') }}</h3>
                <div class="mt-4 space-y-3">
                    @foreach ($competentEvidence as $index => $evidence)
                        <div class="flex gap-3">
                            <div class="flex-1">
                                <flux:input wire:model="competentEvidence.{{ $index }}.person_name" type="text" placeholder="{{ __('Person name') }}" required />
                            </div>
                            <div class="w-40">
                                <select wire:model="competentEvidence.{{ $index }}.id_type" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100" required>
                                    <option value="">{{ __('ID type') }}</option>
                                    <option value="Passport">{{ __('Passport') }}</option>
                                    <option value="PhilID">{{ __('PhilID') }}</option>
                                    <option value="Driver License">{{ __('Driver License') }}</option>
                                    <option value="SSS ID">{{ __('SSS ID') }}</option>
                                    <option value="UMID">{{ __('UMID') }}</option>
                                    <option value="Voter ID">{{ __('Voter ID') }}</option>
                                    <option value="PRC ID">{{ __('PRC ID') }}</option>
                                    <option value="PhilHealth ID">{{ __('PhilHealth ID') }}</option>
                                    <option value="Other">{{ __('Other') }}</option>
                                </select>
                            </div>
                            <div class="flex-1">
                                <flux:input wire:model="competentEvidence.{{ $index }}.id_number" type="text" placeholder="{{ __('ID number') }}" required />
                            </div>
                            @if (count($competentEvidence) > 1)
                                <flux:button variant="ghost" type="button" wire:click="removeEvidence({{ $index }})">✕</flux:button>
                            @endif
                        </div>
                    @endforeach
                    <flux:button variant="outline" size="sm" type="button" wire:click="addEvidence">{{ __('+ Add evidence') }}</flux:button>
                </div>
            </div>

            {{-- Field 7: Type of Notarial Act --}}
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('⑤ Type of notarial act') }}</h3>
                <div class="mt-4 flex flex-wrap gap-3">
                    @foreach (['acknowledgment', 'jurat', 'affidavit', 'oath', 'other'] as $type)
                        <label class="flex cursor-pointer items-center gap-2 rounded-xl border px-4 py-2.5 text-sm transition {{ $notarialActType === $type ? 'border-teal-500 bg-teal-50 text-teal-700 dark:border-teal-600 dark:bg-teal-950/30 dark:text-teal-300' : 'border-zinc-200 bg-white text-zinc-700 hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300' }}">
                            <input type="radio" wire:model.live="notarialActType" value="{{ $type }}" class="sr-only" />
                            {{ ucfirst($type) }}
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Field 8: Fees & O.R. --}}
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('⑥ Fees, official receipt & register location') }}</h3>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Fees (₱)') }}</flux:label>
                        <flux:input wire:model="fees" type="number" step="0.01" min="0" placeholder="500.00" />
                        <flux:error name="fees" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('O.R. number') }}</flux:label>
                        <flux:input wire:model="officialReceiptNumber" type="text" placeholder="CR: 0001234" />
                        <flux:error name="officialReceiptNumber" />
                    </flux:field>
                </div>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Page number') }}</flux:label>
                        <flux:input wire:model="pageNumber" type="number" min="1" placeholder="1" />
                        <p class="mt-1 text-xs text-zinc-500">{{ __('Page number in the notarial register book') }}</p>
                        <flux:error name="pageNumber" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Book number') }}</flux:label>
                        <flux:input wire:model="bookNumber" type="text" placeholder="I" />
                        <p class="mt-1 text-xs text-zinc-500">{{ __('Roman numeral or number of the notarial register book') }}</p>
                        <flux:error name="bookNumber" />
                    </flux:field>
                </div>
            </div>

            {{-- Info: Auto-generated fields --}}
            <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-4 dark:border-sky-900/40 dark:bg-sky-950/30">
                <div class="text-xs font-semibold uppercase tracking-wider text-sky-700 dark:text-sky-300">{{ __('Auto-generated on save') }}</div>
                <ul class="mt-2 space-y-1 text-sm text-sky-800 dark:text-sky-200">
                    <li>⑦ {{ __('Entry number (sequential per year)') }}</li>
                    <li>⑧ {{ __('Date & time of notarization (PHT auto-timestamp)') }}</li>
                    <li>⑨ {{ __('Notary signature (from your registered credentials)') }}</li>
                </ul>
            </div>

            <div class="flex items-center gap-3">
                <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">{{ __('Create register entry') }}</span>
                    <span wire:loading wire:target="save">{{ __('Creating…') }}</span>
                </flux:button>
                <flux:button variant="ghost" :href="route('notary.requests.show', $notaryRequest)" wire:navigate type="button">{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    </div>
</div>
