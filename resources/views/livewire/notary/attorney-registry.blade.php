<?php

use App\Enums\UserRole;
use App\Models\AttorneyNotarialRegistry;
use App\Models\NotaryCredential;
use App\Models\NotaryRequest;
use App\Services\NotaryRequestWorkflowService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public NotaryRequest $notaryRequest;

    public string $entryNo = '';
    public string $title = '';
    public string $description = '';
    public string $notarialActType = 'acknowledgment';
    public string $fees = '';
    public string $officialReceiptNo = '';

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
        abort_unless((int) $notaryRequest->notary_user_id === (int) $user->id, 403);

        abort_unless(
            app(NotaryRequestWorkflowService::class)->hasAttorneySignedAllDocuments($notaryRequest),
            403,
            __('Attorney registry becomes available after the attorney signs the document.')
        );

        $this->notaryRequest = $notaryRequest->loadMissing('attorneyNotarialRegistry');

        $existing = $this->notaryRequest->attorneyNotarialRegistry;
        if ($existing instanceof AttorneyNotarialRegistry) {
            $this->entryNo = (string) ($existing->entry_no ?? '');
            $this->title = (string) $existing->title;
            $this->description = (string) ($existing->description ?? '');
            $this->parties = is_array($existing->parties) && $existing->parties !== [] ? $existing->parties : $this->parties;
            $this->witnesses = is_array($existing->witnesses) ? $existing->witnesses : [];
            $this->competentEvidence = is_array($existing->competent_evidence) && $existing->competent_evidence !== [] ? $existing->competent_evidence : $this->competentEvidence;
            $this->notarialActType = (string) $existing->notarial_act_type;
            $this->fees = number_format((float) $existing->fees, 2, '.', '');
            $this->officialReceiptNo = (string) ($existing->official_receipt_no ?? '');
        } else {
            $this->title = $notaryRequest->title;
        }
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

        $validated = $this->validate([
            'entryNo' => ['nullable', 'string', 'max:64'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'parties' => ['required', 'array', 'min:1'],
            'parties.*.name' => ['required', 'string', 'max:255'],
            'parties.*.address' => ['required', 'string', 'max:500'],
            'witnesses' => ['array'],
            'witnesses.*.name' => ['nullable', 'string', 'max:255'],
            'witnesses.*.address' => ['nullable', 'string', 'max:500'],
            'competentEvidence' => ['required', 'array', 'min:1'],
            'competentEvidence.*.person_name' => ['required', 'string', 'max:255'],
            'competentEvidence.*.id_type' => ['required', 'string', 'max:100'],
            'competentEvidence.*.id_number' => ['required', 'string', 'max:100'],
            'notarialActType' => ['required', 'in:acknowledgment,jurat,affidavit,oath,other'],
            'fees' => ['nullable', 'numeric', 'min:0'],
            'officialReceiptNo' => ['nullable', 'string', 'max:100'],
        ]);

        $credential = NotaryCredential::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        $notarizationTimestamps = collect($validated['parties'])
            ->mapWithKeys(fn (array $party): array => [
                strtolower(trim((string) ($party['name'] ?? ''))) => now()->timezone(config('docutrust.notary.timezone', 'Asia/Manila'))->toDateTimeString(),
            ])
            ->all();

        AttorneyNotarialRegistry::query()->updateOrCreate(
            ['notary_request_id' => $this->notaryRequest->id],
            [
                'entry_no' => trim((string) $validated['entryNo']) !== '' ? trim((string) $validated['entryNo']) : null,
                'title' => trim($validated['title']),
                'description' => trim((string) ($validated['description'] ?? '')) !== '' ? trim((string) $validated['description']) : null,
                'parties' => $validated['parties'],
                'witnesses' => collect($validated['witnesses'] ?? [])
                    ->filter(fn (array $w): bool => trim((string) ($w['name'] ?? '')) !== '' || trim((string) ($w['address'] ?? '')) !== '')
                    ->values()
                    ->all(),
                'competent_evidence' => $validated['competentEvidence'],
                'notarization_timestamps' => $notarizationTimestamps,
                'notarial_act_type' => $validated['notarialActType'],
                'fees' => (float) ($validated['fees'] ?? 0),
                'official_receipt_no' => trim((string) ($validated['officialReceiptNo'] ?? '')) !== '' ? trim((string) $validated['officialReceiptNo']) : null,
                'notary_signature_path' => $credential?->signature_image_path,
            ],
        );

        session()->flash('status', __('Attorney notarial registry draft saved. Continue to Payment.'));
        $this->redirect(route('notary.requests.show', $this->notaryRequest), navigate: true);
    }
}; ?>

<div class="mx-auto flex w-full max-w-5xl flex-col gap-6">
    <header>
        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50 sm:text-3xl">{{ __('Attorney notarial registry') }}</h1>
        <p class="mt-2 text-zinc-600 dark:text-zinc-400">
            {{ __('Prepare registry details before payment and final register entry.') }}
        </p>
    </header>

    <div class="ui-panel p-6 sm:p-8">
        <form wire:submit="save" class="space-y-8">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Entry no.') }}</flux:label>
                    <flux:input wire:model="entryNo" type="text" placeholder="{{ __('Optional draft reference') }}" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Type of notarial act') }}</flux:label>
                    <select wire:model="notarialActType" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                        @foreach (['acknowledgment', 'jurat', 'affidavit', 'oath', 'other'] as $type)
                            <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </flux:field>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Title') }}</flux:label>
                    <flux:input wire:model="title" type="text" required />
                    <flux:error name="title" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Description') }}</flux:label>
                    <flux:input wire:model="description" type="text" />
                </flux:field>
            </div>

            <div class="space-y-3">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Name and address of parties') }}</h3>
                @foreach ($parties as $index => $party)
                    <div class="flex gap-3">
                        <flux:input class="flex-1" wire:model="parties.{{ $index }}.name" type="text" placeholder="{{ __('Full name') }}" required />
                        <flux:input class="flex-1" wire:model="parties.{{ $index }}.address" type="text" placeholder="{{ __('Complete address') }}" required />
                        @if (count($parties) > 1)
                            <flux:button variant="ghost" type="button" wire:click="removeParty({{ $index }})">✕</flux:button>
                        @endif
                    </div>
                @endforeach
                <flux:button variant="outline" size="sm" type="button" wire:click="addParty">{{ __('+ Add party') }}</flux:button>
            </div>

            <div class="space-y-3">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Name and address of witness (if any)') }}</h3>
                @foreach ($witnesses as $index => $witness)
                    <div class="flex gap-3">
                        <flux:input class="flex-1" wire:model="witnesses.{{ $index }}.name" type="text" placeholder="{{ __('Full name') }}" />
                        <flux:input class="flex-1" wire:model="witnesses.{{ $index }}.address" type="text" placeholder="{{ __('Complete address') }}" />
                        <flux:button variant="ghost" type="button" wire:click="removeWitness({{ $index }})">✕</flux:button>
                    </div>
                @endforeach
                <flux:button variant="outline" size="sm" type="button" wire:click="addWitness">{{ __('+ Add witness') }}</flux:button>
            </div>

            <div class="space-y-3">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Competent evidence of identity') }}</h3>
                @foreach ($competentEvidence as $index => $evidence)
                    <div class="flex gap-3">
                        <flux:input class="flex-1" wire:model="competentEvidence.{{ $index }}.person_name" type="text" placeholder="{{ __('Person name') }}" required />
                        <flux:input class="w-44" wire:model="competentEvidence.{{ $index }}.id_type" type="text" placeholder="{{ __('ID type') }}" required />
                        <flux:input class="flex-1" wire:model="competentEvidence.{{ $index }}.id_number" type="text" placeholder="{{ __('ID number') }}" required />
                        @if (count($competentEvidence) > 1)
                            <flux:button variant="ghost" type="button" wire:click="removeEvidence({{ $index }})">✕</flux:button>
                        @endif
                    </div>
                @endforeach
                <flux:button variant="outline" size="sm" type="button" wire:click="addEvidence">{{ __('+ Add evidence') }}</flux:button>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Fees (PHP)') }}</flux:label>
                    <flux:input wire:model="fees" type="number" step="0.01" min="0" placeholder="500.00" />
                    <flux:error name="fees" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('O.R. no.') }}</flux:label>
                    <flux:input wire:model="officialReceiptNo" type="text" />
                </flux:field>
            </div>

            <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-4 text-sm text-sky-900 dark:border-sky-900/40 dark:bg-sky-950/30 dark:text-sky-100">
                {{ __('Date & time of notarization is auto-timestamped per listed party when you save this draft.') }}
            </div>

            <div class="flex items-center gap-3">
                <flux:button variant="primary" type="submit">{{ __('Save attorney registry') }}</flux:button>
                <flux:button variant="ghost" :href="route('notary.requests.show', $notaryRequest)" wire:navigate type="button">{{ __('Back') }}</flux:button>
            </div>
        </form>
    </div>
</div>
