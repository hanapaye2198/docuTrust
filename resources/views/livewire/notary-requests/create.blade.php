<?php

use App\Enums\UserRole;
use App\Http\Requests\StoreNotaryClientCaseRequest;
use App\Models\NotarySigner;
use App\Models\User;
use App\Services\NotaryParticipantSyncService;
use Illuminate\Support\Facades\Auth;
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

    /**
     * @var list<array{full_name: string, email: string, phone: string, address: string, role: string}>
     */
    public array $signers = [];

    public function mount(): void
    {
        $this->signers = [
            [
                'full_name' => '',
                'email' => '',
                'phone' => '',
                'address' => '',
                'role' => 'signer',
            ],
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
        if (count($this->signers) <= 1) {
            return;
        }

        unset($this->signers[$index]);
        $this->signers = array_values($this->signers);
    }

    public function removeCaseDocument(): void
    {
        $this->caseDocument = null;
    }

    /**
     * @return list<array{id: string, label: string, description: string, complete: bool, optional?: bool}>
     */
    #[Computed]
    public function creationSteps(): array
    {
        $user = Auth::user();
        $isNotary = $user?->role === UserRole::Notary;

        $steps = [
            [
                'id' => 'case',
                'label' => __('Case details'),
                'description' => __('Title, act type, and assignment'),
                'complete' => trim($this->title) !== '' && trim($this->requestType) !== '',
            ],
        ];

        if (! $isNotary) {
            return $steps;
        }

        $steps[] = [
            'id' => 'document',
            'label' => __('Document'),
            'description' => __('Primary PDF instrument'),
            'complete' => $this->caseDocument !== null,
            'optional' => true,
        ];

        $hasSigner = collect($this->signers)->contains(
            fn (array $signer): bool => trim((string) ($signer['full_name'] ?? '')) !== ''
                && trim((string) ($signer['email'] ?? '')) !== ''
        );

        $steps[] = [
            'id' => 'signers',
            'label' => __('Parties'),
            'description' => __('Signers and witnesses'),
            'complete' => $hasSigner,
            'optional' => true,
        ];

        return $steps;
    }

    public function with(): array
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        return [
            'isNotaryView' => $user->role === UserRole::Notary,
            'availableNotaries' => User::query()
                ->where('role', UserRole::Notary)
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }

    public function save(): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'requestType' => ['required', 'string', 'max:64'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];

        // Attorney can upload documents and add signers during creation
        $isNotary = $user->role === UserRole::Notary;

        if ($isNotary) {
            $eligibility = app(\App\Services\AttorneyApplicationService::class)->practiceEligibility($user);
            if (! $eligibility['allowed']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'title' => $eligibility['message'] ?? __('Attorney practice is not enabled.'),
                ]);
            }
        }

        if ($isNotary) {
            $rules['caseDocument'] = ['nullable', 'file', 'mimes:pdf', 'max:10240'];
            $rules['signers'] = ['nullable', 'array'];
            $rules['signers.*.full_name'] = ['required_with:signers', 'string', 'max:255'];
            $rules['signers.*.email'] = ['required_with:signers', 'email', 'max:255'];
            $rules['signers.*.phone'] = ['nullable', 'string', 'max:64'];
            $rules['signers.*.address'] = ['nullable', 'string', 'max:500'];
            $rules['signers.*.role'] = ['nullable', 'string', 'max:64'];
        }

        $notaryUserId = null;
        if (trim((string) $this->notaryUserId) !== '') {
            $rules['notaryUserId'] = ['required', 'exists:users,id'];
        }

        $validated = $this->validate($rules);

        if (isset($validated['notaryUserId'])) {
            $notaryUserId = (int) $validated['notaryUserId'];
        }

        // For attorney creating their own request, they are the notary
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
            ],
        ]);

        // Create a Document record if attorney uploaded one
        $document = null;
        if ($isNotary && $documentPath !== null) {
            $document = $user->documents()->create([
                'notary_request_id' => $request->id,
                'title' => trim($validated['title']),
                'file_path' => $documentPath,
                'status' => \App\Enums\DocumentStatus::Draft,
            ]);
        }

        // Create signers if attorney provided them
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
            session()->flash('status', __('eNOTARY case created. You can upload documents and prepare signature fields from the request page.'));
        } else {
            session()->flash('status', __('eNOTARY request created. The assigned attorney will upload documents and manage the signing process.'));
        }

        $this->redirect(route($isNotary ? 'notary.requests.show' : 'notary-requests.show', $request, absolute: false), navigate: true);
    }
}; ?>

<x-admin.page class="h-full flex-1" gap="gap-6">

    <div class="flex flex-col gap-4 border-b border-zinc-200/90 pb-5 dark:border-zinc-800 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-3xl">{{ __('Create eNOTARY request') }}</h1>
            <p class="mt-1 max-w-3xl text-sm text-zinc-500 dark:text-zinc-400">
                {{ $isNotaryView
                    ? __('Set up the case, optionally attach the instrument and parties, then continue on the request page.')
                    : __('Submit the matter details. Your attorney will upload documents and manage signing.') }}
            </p>
        </div>
        <flux:button
            variant="ghost"
            :href="$isNotaryView ? route('notary.requests.index') : route('notary-requests.index')"
            wire:navigate
            icon="arrow-left"
        >
            {{ __('Back to requests') }}
        </flux:button>
    </div>

    <form wire:submit="save" class="grid items-start gap-8 lg:grid-cols-12">

        <div class="min-w-0 space-y-6 lg:col-span-8 xl:col-span-9">

            <section id="case-details" class="ui-panel scroll-mt-6 p-6 sm:p-8">
                <div class="flex items-start gap-3">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-zinc-900 text-xs font-bold text-white dark:bg-zinc-100 dark:text-zinc-900">1</span>
                    <div>
                        <flux:heading size="lg" class="!mb-0">{{ __('Case information') }}</flux:heading>
                        <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Required details to open the notarization matter.') }}</p>
                    </div>
                </div>

                <div class="mt-6 space-y-6">
                    <flux:field>
                        <flux:label>{{ __('Case title') }} <span class="text-rose-500">*</span></flux:label>
                        <flux:input wire:model.live="title" type="text" required placeholder="{{ __('e.g. Deed of Sale — Lot 5, Block 2, Greenfield Subd.') }}" />
                        <flux:description>{{ __('A descriptive name shown on the request list and detail page.') }}</flux:description>
                        <flux:error name="title" />
                    </flux:field>

                    <div class="grid gap-5 lg:grid-cols-2">
                        <div>
                            <flux:select wire:model="requestType" label="{{ __('Notarial act type') }}" required>
                                @foreach (config('docutrust.notary.notarial_act_types', []) as $act)
                                    <flux:select.option value="{{ $act }}">{{ __(ucfirst(str_replace('_', ' ', $act))) }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="requestType" />
                        </div>
                        @if (! $isNotaryView)
                            <div>
                                <flux:select wire:model="notaryUserId" label="{{ __('Assign attorney / notary') }}" placeholder="{{ __('Select an attorney…') }}">
                                    @foreach ($availableNotaries as $notary)
                                        <flux:select.option value="{{ $notary->id }}">{{ $notary->name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:description>{{ __('Who will upload documents and run the session.') }}</flux:description>
                                <flux:error name="notaryUserId" />
                            </div>
                        @endif
                    </div>

                    <flux:field>
                        <flux:label>{{ __('Remarks') }}</flux:label>
                        <flux:textarea wire:model="remarks" rows="3" placeholder="{{ __('Instructions, deadlines, or context for the attorney…') }}" />
                        <flux:error name="remarks" />
                    </flux:field>
                </div>
            </section>

            @if ($isNotaryView)
            <section id="document-upload" class="ui-panel scroll-mt-6 p-6 sm:p-8">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-zinc-900 text-xs font-bold text-white dark:bg-zinc-100 dark:text-zinc-900">2</span>
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:heading size="lg" class="!mb-0">{{ __('Document upload') }}</flux:heading>
                                <flux:badge size="sm" color="zinc">{{ __('Optional') }}</flux:badge>
                            </div>
                            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Attach the primary PDF now or add it on the request page.') }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-6">
                    @if ($caseDocument)
                        <div class="flex items-center gap-3 rounded-xl border border-emerald-200/80 bg-emerald-50/80 px-4 py-3 dark:border-emerald-900/40 dark:bg-emerald-950/25">
                            <flux:icon.document-text class="size-8 shrink-0 text-emerald-600 dark:text-emerald-400" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-emerald-900 dark:text-emerald-100">{{ $caseDocument->getClientOriginalName() }}</p>
                                <p class="text-xs text-emerald-700 dark:text-emerald-300">{{ __('Ready to include with this request') }}</p>
                            </div>
                            <flux:button type="button" size="sm" variant="ghost" wire:click="removeCaseDocument" wire:loading.attr="disabled" wire:target="removeCaseDocument">
                                {{ __('Remove') }}
                            </flux:button>
                        </div>
                    @else
                        <div
                            class="relative rounded-2xl border-2 border-dashed border-zinc-300 bg-zinc-50/80 p-8 text-center transition dark:border-zinc-600 dark:bg-zinc-900/40"
                            x-data
                            x-on:dragover.prevent="$el.classList.add('border-teal-400', 'bg-teal-50/50', 'dark:border-teal-500')"
                            x-on:dragleave.prevent="$el.classList.remove('border-teal-400', 'bg-teal-50/50', 'dark:border-teal-500')"
                            x-on:drop.prevent="
                                $el.classList.remove('border-teal-400', 'bg-teal-50/50', 'dark:border-teal-500');
                                if ($event.dataTransfer.files.length) {
                                    $refs.casePdf.files = $event.dataTransfer.files;
                                    $refs.casePdf.dispatchEvent(new Event('change', { bubbles: true }));
                                }
                            "
                        >
                            <flux:icon.document-text class="mx-auto size-10 text-zinc-400" />
                            <p class="mt-3 text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('Drag & drop PDF here') }}</p>
                            <p class="mt-1 text-xs text-zinc-500">{{ __('PDF only · max 10 MB') }}</p>
                            <label class="mt-4 inline-flex cursor-pointer">
                                <span class="text-sm font-semibold text-teal-600 hover:text-teal-700 dark:text-teal-400">{{ __('Browse files') }}</span>
                                <input
                                    x-ref="casePdf"
                                    type="file"
                                    wire:model="caseDocument"
                                    accept="application/pdf,.pdf"
                                    class="sr-only"
                                />
                            </label>
                        </div>
                    @endif

                    <div wire:loading wire:target="caseDocument" class="mt-3 text-sm font-medium text-teal-700 dark:text-teal-300">
                        {{ __('Uploading file…') }}
                    </div>
                    <flux:error name="caseDocument" />
                </div>
            </section>
            @endif

            @if ($isNotaryView)
            <section id="parties" class="ui-panel scroll-mt-6 p-6 sm:p-8">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-zinc-900 text-xs font-bold text-white dark:bg-zinc-100 dark:text-zinc-900">3</span>
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:heading size="lg" class="!mb-0">{{ __('Signers & parties') }}</flux:heading>
                                <flux:badge size="sm" color="zinc">{{ __('Optional') }}</flux:badge>
                            </div>
                            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Add parties now or invite them from the request page.') }}</p>
                        </div>
                    </div>
                    <flux:button type="button" size="sm" variant="outline" icon="plus" wire:click="addSignerRow">
                        {{ __('Add party') }}
                    </flux:button>
                </div>

                <div class="mt-6 space-y-4">
                    @foreach ($signers as $index => $signer)
                        <div
                            class="rounded-xl border border-zinc-200/80 bg-zinc-50/50 p-5 dark:border-zinc-700/60 dark:bg-zinc-800/30"
                            wire:key="signer-{{ $index }}"
                        >
                            <div class="mb-4 flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-zinc-900 text-xs font-bold text-white dark:bg-zinc-100 dark:text-zinc-900">{{ $index + 1 }}</span>
                                    <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">
                                        {{ ! empty($signers[$index]['full_name']) ? $signers[$index]['full_name'] : __('Party :n', ['n' => $index + 1]) }}
                                    </span>
                                </div>
                                @if (count($signers) > 1)
                                    <flux:button type="button" size="sm" variant="ghost" icon="trash" wire:click="removeSignerRow({{ $index }})">
                                        {{ __('Remove') }}
                                    </flux:button>
                                @endif
                            </div>

                            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
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
                        </div>
                    @endforeach
                    <flux:error name="signers" />
                </div>
            </section>
            @endif

        </div>

        <aside class="space-y-4 lg:col-span-4 xl:col-span-3 lg:sticky lg:top-4">
            <div class="ui-panel p-5 sm:p-6">
                <flux:heading size="lg" class="mb-4">{{ __('Your progress') }}</flux:heading>
                <ol class="space-y-3">
                    @foreach ($this->creationSteps as $step)
                        <li class="flex gap-3">
                            <span @class([
                                'mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                                'bg-emerald-600 text-white' => $step['complete'],
                                'bg-zinc-200 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300' => ! $step['complete'],
                            ])>
                                @if ($step['complete'])
                                    <flux:icon.check class="size-3.5" />
                                @else
                                    {{ $loop->iteration }}
                                @endif
                            </span>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="#{{ $step['id'] === 'case' ? 'case-details' : ($step['id'] === 'document' ? 'document-upload' : 'parties') }}" class="text-sm font-semibold text-zinc-900 hover:text-teal-700 dark:text-zinc-100 dark:hover:text-teal-400">
                                        {{ $step['label'] }}
                                    </a>
                                    @if ($step['optional'] ?? false)
                                        <flux:badge size="sm" color="zinc">{{ __('Optional') }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="rose">{{ __('Required') }}</flux:badge>
                                    @endif
                                </div>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $step['description'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>

            <div class="ui-panel p-5 sm:p-6">
                <flux:heading size="lg" class="mb-2">{{ __('What happens next?') }}</flux:heading>
                <p class="text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">
                    @if ($isNotaryView)
                        {{ __('After you create the request, you can place signature fields, schedule the video session, and run identity checks from the request detail page.') }}
                    @else
                        {{ __('Your attorney uploads documents, adds signers, and sends signing links. You will be emailed when it is your turn to sign.') }}
                    @endif
                </p>
                @if ($isNotaryView)
                    <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-500">{{ __('Document and parties can be skipped now and completed later.') }}</p>
                @endif
            </div>

            <div class="ui-panel hidden p-5 sm:p-6 lg:block">
                <div class="flex flex-col gap-3">
                    <flux:button
                        type="submit"
                        variant="primary"
                        class="w-full"
                        wire:loading.attr="disabled"
                        wire:target="save,caseDocument"
                        icon="check"
                    >
                        <span wire:loading.remove wire:target="save">{{ __('Create request') }}</span>
                        <span wire:loading wire:target="save">{{ __('Creating…') }}</span>
                    </flux:button>
                    <flux:button
                        variant="ghost"
                        class="w-full"
                        :href="$isNotaryView ? route('notary.requests.index') : route('notary-requests.index')"
                        wire:navigate
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                </div>
            </div>
        </aside>

        <div class="sticky bottom-4 z-10 ui-panel p-4 shadow-lg lg:col-span-12 lg:hidden">
            <div class="flex items-center justify-end gap-3">
                <flux:button
                    variant="ghost"
                    :href="$isNotaryView ? route('notary.requests.index') : route('notary-requests.index')"
                    wire:navigate
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    type="submit"
                    variant="primary"
                    wire:loading.attr="disabled"
                    wire:target="save,caseDocument"
                    icon="check"
                >
                    <span wire:loading.remove wire:target="save">{{ __('Create request') }}</span>
                    <span wire:loading wire:target="save">{{ __('Creating…') }}</span>
                </flux:button>
            </div>
        </div>

    </form>
</x-admin.page>
