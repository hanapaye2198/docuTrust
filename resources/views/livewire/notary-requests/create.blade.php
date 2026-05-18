<?php

use App\Enums\UserRole;
use App\Http\Requests\StoreNotaryClientCaseRequest;
use App\Models\NotarySigner;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
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

        if ($isNotary) {
            session()->flash('status', __('eNOTARY case created. You can upload documents and prepare signature fields from the request page.'));
        } else {
            session()->flash('status', __('eNOTARY request created. The assigned attorney will upload documents and manage the signing process.'));
        }

        $this->redirect(route($isNotary ? 'notary.requests.show' : 'notary-requests.show', $request, absolute: false), navigate: true);
    }
}; ?>

<div class="mx-auto flex w-full max-w-4xl min-w-0 flex-col gap-8 px-1 py-4 sm:py-6">

    {{-- Page header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-2xl">{{ __('Create eNOTARY Request') }}</h1>
                <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Open a trackable notarization case with document and signer details.') }}</p>
            </div>
        </div>
        <a href="{{ $isNotaryView ? route('notary.requests.index') : route('notary-requests.index') }}" wire:navigate
           class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3.5 py-2 text-sm font-medium text-zinc-600 shadow-sm transition-all hover:bg-zinc-50 hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700 dark:hover:text-white">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
            {{ __('Back') }}
        </a>
    </div>

    <form wire:submit="save" class="space-y-6">

        {{-- Section 1: Case Details --}}
        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-zinc-950/5 dark:bg-zinc-900 dark:ring-white/10">
            <div class="flex items-center gap-3 border-b border-zinc-100 bg-zinc-50/50 px-6 py-4 dark:border-zinc-800 dark:bg-zinc-800/30">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg text-xs font-bold text-white shadow-sm" style="background-color: #18181b;">1</span>
                <div>
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Case Information') }}</h2>
                    <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Basic details about the notarization matter') }}</p>
                </div>
            </div>
            <div class="space-y-6 p-6 sm:p-8">
                <flux:field>
                    <flux:label>{{ __('Case title') }} <span class="text-rose-500">*</span></flux:label>
                    <flux:input wire:model="title" type="text" required placeholder="{{ __('e.g. Deed of Sale — Lot 5, Block 2, Greenfield Subd.') }}" />
                    <flux:description>{{ __('A descriptive name for this notarization matter.') }}</flux:description>
                    <flux:error name="title" />
                </flux:field>

                <div class="grid gap-5 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Notarial act type') }} <span class="text-rose-500">*</span></flux:label>
                        <div class="relative">
                            <select wire:model="requestType" class="w-full appearance-none rounded-lg border border-zinc-200 bg-white py-2.5 pl-3.5 pr-10 text-sm text-zinc-900 shadow-sm transition focus:border-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900/10 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 dark:focus:border-zinc-300 dark:focus:ring-zinc-300/10">
                                @foreach (config('docutrust.notary.notarial_act_types', []) as $act)
                                    <option value="{{ $act }}">{{ __(ucfirst(str_replace('_', ' ', $act))) }}</option>
                                @endforeach
                            </select>
                            <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                        </div>
                        <flux:error name="requestType" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Assign notary') }}</flux:label>
                        <div class="relative">
                            <select wire:model="notaryUserId" class="w-full appearance-none rounded-lg border border-zinc-200 bg-white py-2.5 pl-3.5 pr-10 text-sm text-zinc-900 shadow-sm transition focus:border-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900/10 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 dark:focus:border-zinc-300 dark:focus:ring-zinc-300/10">
                                <option value="">{{ __('— Assign later —') }}</option>
                                @foreach ($availableNotaries as $notary)
                                    <option value="{{ $notary->id }}">{{ $notary->name }}</option>
                                @endforeach
                            </select>
                            <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                        </div>

                        <flux:error name="notaryUserId" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>{{ __('Remarks') }}</flux:label>
                    <flux:textarea wire:model="remarks" rows="3" placeholder="{{ __('Any additional context, instructions, or notes for the notary…') }}" />
                    <flux:error name="remarks" />
                </flux:field>
            </div>
        </div>

        {{-- Section 2: Document Upload (Attorney only) --}}
        @if ($isNotaryView)
        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-zinc-950/5 dark:bg-zinc-900 dark:ring-white/10">
            <div class="flex items-center gap-3 border-b border-zinc-100 bg-zinc-50/50 px-6 py-4 dark:border-zinc-800 dark:bg-zinc-800/30">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg text-xs font-bold text-white shadow-sm" style="background-color: #18181b;">2</span>
                <div>
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Document Upload') }}</h2>
                    <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('The primary instrument to be notarized') }}</p>
                </div>
            </div>
            <div class="p-6 sm:p-8">
                <flux:field>
                    <flux:label>{{ __('Primary instrument (PDF)') }}</flux:label>
                    <label class="group relative flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-zinc-200 bg-gradient-to-b from-zinc-50/80 to-white px-6 py-12 transition-all duration-200 hover:border-indigo-400 hover:from-indigo-50/50 hover:to-white hover:shadow-sm dark:border-zinc-700 dark:from-zinc-800/80 dark:to-zinc-900 dark:hover:border-indigo-500 dark:hover:from-indigo-950/20 dark:hover:to-zinc-900">
                        <input
                            type="file"
                            wire:model="caseDocument"
                            accept="application/pdf,.pdf"
                            class="sr-only"
                        />
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-100 shadow-sm transition-all duration-200 group-hover:scale-110 group-hover:bg-indigo-200 group-hover:shadow-md dark:bg-indigo-950/50 dark:group-hover:bg-indigo-900/60">
                            <svg class="h-7 w-7 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
                            </svg>
                        </div>
                        <p class="mt-4 text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            <span class="text-indigo-600 underline decoration-indigo-300 underline-offset-2 dark:text-indigo-400 dark:decoration-indigo-700">{{ __('Click to upload') }}</span>
                            <span class="text-zinc-400"> {{ __('or drag and drop') }}</span>
                        </p>
                        <p class="mt-1.5 text-xs text-zinc-400 dark:text-zinc-500">{{ __('PDF format only • Maximum 10MB') }}</p>
                    </label>

                    {{-- Upload progress --}}
                    <div wire:loading wire:target="caseDocument" class="mt-4 flex items-center gap-2.5 rounded-xl border border-indigo-100 bg-indigo-50 px-4 py-3 dark:border-indigo-900/40 dark:bg-indigo-950/20">
                        <svg class="h-5 w-5 animate-spin text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span class="text-sm font-medium text-indigo-700 dark:text-indigo-300">{{ __('Uploading file…') }}</span>
                    </div>

                    {{-- File uploaded confirmation --}}
                    @if ($caseDocument)
                        <div class="mt-4 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 dark:border-emerald-900/40 dark:bg-emerald-950/20">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/40">
                                <svg class="h-5 w-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-emerald-800 dark:text-emerald-200">{{ $caseDocument->getClientOriginalName() }}</p>
                                <p class="text-xs text-emerald-600 dark:text-emerald-400">{{ __('Ready to submit') }}</p>
                            </div>
                        </div>
                    @endif
                    <flux:error name="caseDocument" />
                </flux:field>
                <p class="mt-3 text-xs text-zinc-400 dark:text-zinc-500">{{ __('You can also upload documents later from the request page.') }}</p>
            </div>
        </div>
        @endif

        {{-- Section 3: Signers (Attorney only — clients don't assign signers) --}}
        @if ($isNotaryView)
        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-zinc-950/5 dark:bg-zinc-900 dark:ring-white/10">
            <div class="flex items-center justify-between border-b border-zinc-100 bg-zinc-50/50 px-6 py-4 dark:border-zinc-800 dark:bg-zinc-800/30">
                <div class="flex items-center gap-3">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg text-xs font-bold text-white shadow-sm" style="background-color: #18181b;">3</span>
                    <div>
                        <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Signers & Parties') }}</h2>
                        <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Add all parties involved in this notarization') }}</p>
                    </div>
                </div>
                <button type="button" wire:click="addSignerRow"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-300 bg-zinc-50 px-3 py-1.5 text-xs font-semibold text-zinc-700 transition-all hover:bg-zinc-100 hover:shadow-sm dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    {{ __('Add signer') }}
                </button>
            </div>
            <div class="space-y-5 p-6 sm:p-8">
                @foreach ($signers as $index => $signer)
                    <div class="relative overflow-hidden rounded-xl border border-zinc-200/80 bg-white p-5 shadow-sm transition-all duration-200 hover:shadow-md dark:border-zinc-700/60 dark:bg-zinc-800/40" wire:key="signer-{{ $index }}">
                        {{-- Accent bar --}}
                        <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl" style="background-color: #18181b;"></div>

                        {{-- Signer header --}}
                        <div class="mb-5 flex items-center justify-between pl-3">
                            <div class="flex items-center gap-2.5">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold text-white shadow-sm" style="background-color: #18181b;">
                                    {{ $index + 1 }}
                                </div>
                                <div>
                                    <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">{{ __('Signer') }} {{ $index + 1 }}</span>
                                    @if (!empty($signers[$index]['full_name']))
                                        <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ $signers[$index]['full_name'] }}</p>
                                    @endif
                                </div>
                            </div>
                            @if (count($signers) > 1)
                                <button type="button" wire:click="removeSignerRow({{ $index }})"
                                    class="rounded-lg border border-transparent p-2 text-zinc-400 transition-all hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600 dark:hover:border-rose-900/40 dark:hover:bg-rose-950/20 dark:hover:text-rose-400"
                                    title="{{ __('Remove signer') }}">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                                </button>
                            @endif
                        </div>

                        {{-- Signer fields --}}
                        <div class="grid gap-4 pl-3 sm:grid-cols-2">
                            <flux:field class="sm:col-span-2">
                                <flux:label>{{ __('Full name') }} <span class="text-rose-500">*</span></flux:label>
                                <flux:input type="text" wire:model="signers.{{ $index }}.full_name" required placeholder="{{ __('Juan Dela Cruz') }}" />
                                <flux:error name="signers.{{ $index }}.full_name" />
                            </flux:field>
                            <flux:field>
                                <flux:label>{{ __('Email address') }} <span class="text-rose-500">*</span></flux:label>
                                <flux:input type="email" wire:model="signers.{{ $index }}.email" required placeholder="{{ __('juan@example.com') }}" />
                                <flux:error name="signers.{{ $index }}.email" />
                            </flux:field>
                            <flux:field>
                                <flux:label>{{ __('Phone number') }}</flux:label>
                                <flux:input type="text" wire:model="signers.{{ $index }}.phone" placeholder="{{ __('+63 9XX XXX XXXX') }}" />
                                <flux:error name="signers.{{ $index }}.phone" />
                            </flux:field>
                            <flux:field class="sm:col-span-2">
                                <flux:label>{{ __('Address') }}</flux:label>
                                <flux:input type="text" wire:model="signers.{{ $index }}.address" placeholder="{{ __('Complete address for identity verification') }}" />
                                <flux:error name="signers.{{ $index }}.address" />
                            </flux:field>
                            <flux:field>
                                <flux:label>{{ __('Role in document') }}</flux:label>
                                <div class="relative">
                                    <select wire:model="signers.{{ $index }}.role" class="w-full appearance-none rounded-lg border border-zinc-200 bg-white py-2.5 pl-3.5 pr-10 text-sm text-zinc-900 shadow-sm transition focus:border-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900/10 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                                        <option value="signer">{{ __('Signer') }}</option>
                                        <option value="witness">{{ __('Witness') }}</option>
                                        <option value="affiant">{{ __('Affiant') }}</option>
                                        <option value="principal">{{ __('Principal') }}</option>
                                    </select>
                                    <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                                </div>
                                <flux:error name="signers.{{ $index }}.role" />
                            </flux:field>
                        </div>
                    </div>
                @endforeach
                <flux:error name="signers" />
            </div>
        </div>
        @endif

        {{-- Info note for clients --}}
        @if (! $isNotaryView)
        <div class="rounded-2xl border border-sky-200 bg-sky-50 px-6 py-4 dark:border-sky-900/40 dark:bg-sky-950/20">
            <div class="flex items-start gap-3">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/></svg>
                <div>
                    <p class="text-sm font-medium text-sky-800 dark:text-sky-200">{{ __('What happens next?') }}</p>
                    <p class="mt-1 text-sm text-sky-700 dark:text-sky-300">{{ __('After you create this request, the assigned attorney will upload the documents, assign signers, and send the document for signing. You will receive a signing link via email when it is your turn to sign.') }}</p>
                </div>
            </div>
        </div>
        @endif

        {{-- Submit footer --}}
        <div class="sticky bottom-4 z-10 rounded-2xl border border-zinc-200/80 bg-white/90 px-6 py-4 shadow-lg shadow-zinc-900/5 backdrop-blur-xl dark:border-zinc-700/60 dark:bg-zinc-900/90 dark:shadow-none">
            <div class="flex items-center justify-between">
                <div class="hidden items-center gap-2 sm:flex">
                    <svg class="h-4 w-4 text-zinc-300 dark:text-zinc-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/></svg>
                    <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('You can upload identity documents after creating the request.') }}</p>
                </div>
                <div class="flex w-full items-center justify-end gap-3 sm:w-auto">
                    <a href="{{ $isNotaryView ? route('notary.requests.index') : route('notary-requests.index') }}" wire:navigate
                       class="rounded-lg px-4 py-2.5 text-sm font-medium text-zinc-600 transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white">
                        {{ __('Cancel') }}
                    </a>
                    <button type="submit" wire:loading.attr="disabled" wire:target="save,caseDocument"
                        class="inline-flex items-center gap-2 rounded-lg px-5 py-2.5 text-sm font-semibold text-white shadow-md transition-all duration-200 hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                        style="background-color: #18181b;">
                        <span wire:loading.remove wire:target="save" class="flex items-center gap-2">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                            {{ __('Create Request') }}
                        </span>
                        <span wire:loading wire:target="save" class="flex items-center gap-2">
                            <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            {{ __('Creating…') }}
                        </span>
                    </button>
                </div>
            </div>
        </div>

    </form>
</div>
