<?php

use App\Events\DocumentSent;
use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\SignerCertificate;
use App\Models\Tag;
use App\Services\SendDocumentForSignatureService;
use App\Services\SignerCertificateRevocationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Illuminate\Support\Str;

new #[Layout('components.layouts.app')] class extends Component {
    public Document $document;

    /** @var list<int> */
    public array $tagIds = [];

    /** @var array<int, string> */
    public array $revocationReasons = [];

    public string $quickTagName = '';

    public function mount(Document $document): void
    {
        $this->authorize('view', $document);
        $this->document = $document->load(['documentSigners', 'signatureFields', 'signatures.signerCertificate', 'signatures.signer', 'user.contacts', 'tags']);
        $this->tagIds = $this->document->tags->pluck('id')->all();
    }

    #[On('document-updated')]
    public function refreshDocument(): void
    {
        $this->document->refresh()->load(['documentSigners', 'signatureFields', 'signatures.signerCertificate', 'signatures.signer', 'user.contacts', 'tags']);
        $this->tagIds = $this->document->tags->pluck('id')->all();
    }

    public function with(): array
    {
        return [
            'availableTags' => Auth::user()->tags()->orderBy('name')->get(),
            'workflow' => $this->workflowState(),
        ];
    }

    protected function workflowState(): array
    {
        $document = $this->document->loadMissing(['documentSigners', 'signatureFields']);
        $missingSigners = $document->signersMissingFields();

        return [
            'signerCount' => $document->documentSigners->count(),
            'fieldCount' => $document->signatureFields->count(),
            'missingSignerNames' => $missingSigners
                ->pluck('name')
                ->filter(fn ($name) => is_string($name) && $name !== '')
                ->values(),
            'canPrepare' => $document->canPrepareForSigning(),
            'canSend' => $document->canSendForSigning(),
        ];
    }

    public function quickAddTag(): void
    {
        $this->authorize('create', Tag::class);
        $this->validate([
            'quickTagName' => [
                'required',
                'string',
                'max:64',
                Rule::unique('tags', 'name')->where('user_id', Auth::id()),
            ],
        ]);

        $tag = Auth::user()->tags()->create(['name' => trim($this->quickTagName)]);
        $this->tagIds[] = $tag->id;
        $this->tagIds = array_values(array_unique($this->tagIds));
        $this->quickTagName = '';
        $this->saveTags();
    }

    public function saveTags(): void
    {
        $this->authorize('update', $this->document);

        $this->validate([
            'tagIds' => ['array'],
            'tagIds.*' => ['integer', Rule::exists('tags', 'id')->where('user_id', Auth::id())],
        ]);

        $this->document->tags()->sync(array_map('intval', $this->tagIds));
        $this->document->refresh()->load('tags');
        $this->tagIds = $this->document->tags->pluck('id')->all();
        session()->flash('status', __('Document tags updated.'));
    }

    public function sendForSignature(): void
    {
        $this->authorize('update', $this->document);

        try {
            app(SendDocumentForSignatureService::class)->send($this->document);
        } catch (\RuntimeException $exception) {
            $this->addError('send', $exception->getMessage());

            return;
        }

        $this->document->refresh()->load('documentSigners');
        session()->flash('status', __('Document sent for signature.'));
    }

    public function revokeCertificate(int $certificateId): void
    {
        abort_unless(Auth::user()?->role === UserRole::Admin, 403);
        $this->authorize('update', $this->document);

        $certificate = SignerCertificate::query()
            ->whereKey($certificateId)
            ->whereHas('documentSigner', fn ($query) => $query->where('document_id', $this->document->id))
            ->firstOrFail();

        $reason = trim((string) ($this->revocationReasons[$certificateId] ?? ''));

        validator(
            ['reason' => $reason],
            ['reason' => ['required', 'string', 'max:255']]
        )->validate();

        app(SignerCertificateRevocationService::class)->revoke($certificate, $reason);

        $this->revocationReasons[$certificateId] = '';
        $this->refreshDocument();
        session()->flash('status', __('Signer certificate revoked.'));
    }

}; ?>

<div class="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-8">
    @if (session('status'))
        <div
            class="flex items-start gap-3 rounded-2xl border border-emerald-200/90 bg-gradient-to-r from-emerald-50 to-white px-4 py-3 text-sm text-emerald-900 shadow-sm dark:border-emerald-900/50 dark:from-emerald-950/40 dark:to-zinc-900 dark:text-emerald-100"
        >
            <span class="mt-0.5 inline-flex size-2 shrink-0 rounded-full bg-emerald-500"></span>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div
            class="flex items-start gap-3 rounded-2xl border border-red-200/90 bg-gradient-to-r from-red-50 to-white px-4 py-3 text-sm text-red-900 shadow-sm dark:border-red-900/50 dark:from-red-950/40 dark:to-zinc-900 dark:text-red-100"
        >
            <span class="mt-0.5 inline-flex size-2 shrink-0 rounded-full bg-red-500"></span>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0 flex-1 space-y-3">
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50 sm:text-3xl">{{ $document->title }}</h1>
            <div class="flex flex-wrap items-center gap-2">
                <x-document-status-badge :status="$document->status" />
                @if ($document->sent_at)
                    <span class="rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-600 dark:bg-white/10 dark:text-zinc-300">
                        {{ __('Sent') }}: {{ $document->sent_at->format('M j, Y g:i A') }}
                    </span>
                @endif
            </div>
        </div>
        <div class="flex flex-shrink-0 flex-wrap items-center gap-2">
            @if ($document->status === DocumentStatus::Draft)
            @endif
            <flux:button variant="ghost" :href="route('documents.index')" wire:navigate>{{ __('Back to list') }}</flux:button>
        </div>
    </div>

    @error('send')
        <div class="rounded-2xl border border-red-200/90 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-100">
            {{ $message }}
        </div>
    @enderror

    @if ($document->status === DocumentStatus::Draft)
        <div class="ui-panel p-6">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Workflow') }}</h2>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Documents move in a fixed order: add signer, prepare fields, then send for signature.') }}</p>
                </div>
                <div class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                    {{ __('Draft workflow') }}
                </div>
            </div>

            <div class="mt-5 grid gap-4 lg:grid-cols-3">
                <div class="rounded-2xl border {{ $workflow['signerCount'] > 0 ? 'border-emerald-200 bg-emerald-50/80 dark:border-emerald-900/40 dark:bg-emerald-950/20' : 'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900/60' }} p-4">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('1. Add signer') }}</span>
                        <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $workflow['signerCount'] > 0 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' }}">
                            {{ $workflow['signerCount'] > 0 ? __('Done') : __('Required') }}
                        </span>
                    </div>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                        {{ trans_choice(':count signer added|:count signers added', $workflow['signerCount'], ['count' => $workflow['signerCount']]) }}
                    </p>
                </div>

                <div class="rounded-2xl border {{ $workflow['fieldCount'] > 0 ? 'border-emerald-200 bg-emerald-50/80 dark:border-emerald-900/40 dark:bg-emerald-950/20' : ($workflow['canPrepare'] ? 'border-sky-200 bg-sky-50/80 dark:border-sky-900/40 dark:bg-sky-950/20' : 'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900/60') }} p-4">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('2. Prepare') }}</span>
                        <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $workflow['fieldCount'] > 0 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : ($workflow['canPrepare'] ? 'bg-sky-100 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300') }}">
                            {{ $workflow['fieldCount'] > 0 ? __('Done') : ($workflow['canPrepare'] ? __('Next') : __('Locked')) }}
                        </span>
                    </div>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                        @if ($workflow['fieldCount'] > 0)
                            {{ trans_choice(':count field placed|:count fields placed', $workflow['fieldCount'], ['count' => $workflow['fieldCount']]) }}
                        @elseif ($workflow['canPrepare'])
                            {{ __('Open Prepare fields and assign at least one field to every signer.') }}
                        @else
                            {{ __('Add at least one signer first to unlock field placement.') }}
                        @endif
                    </p>
                </div>

                <div class="rounded-2xl border {{ $workflow['canSend'] ? 'border-sky-200 bg-sky-50/80 dark:border-sky-900/40 dark:bg-sky-950/20' : 'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900/60' }} p-4">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('3. Send') }}</span>
                        <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $workflow['canSend'] ? 'bg-sky-100 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' }}">
                            {{ $workflow['canSend'] ? __('Ready') : __('Locked') }}
                        </span>
                    </div>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                        @if ($workflow['canSend'])
                            {{ __('All signers have assigned fields. You can send the document now.') }}
                        @elseif ($workflow['fieldCount'] < 1)
                            {{ __('Prepare the document before sending.') }}
                        @elseif ($workflow['missingSignerNames']->isNotEmpty())
                            {{ __('Still missing fields for: :signers', ['signers' => $workflow['missingSignerNames']->implode(', ')]) }}
                        @else
                            {{ __('Complete the previous steps before sending.') }}
                        @endif
                    </p>
                </div>
            </div>
        </div>
    @endif

    <div class="ui-panel p-6 sm:p-8">
        <livewire:document-signers-manager :document-id="$document->id" :key="'signers-'.$document->id" />

        @if ($document->status === DocumentStatus::Draft)
            <div class="mt-6 flex justify-end border-t border-zinc-200/80 pt-6 dark:border-zinc-700/80">
                <flux:button variant="outline" :href="route('documents.prepare', $document)" wire:navigate :disabled="! $workflow['canPrepare']">
                    {{ __('Prepare fields') }}
                </flux:button>
            </div>
        @endif
    </div>

    @php
        $signerCertificates = $document->signatures
            ->map(fn ($signature) => [
                'signature' => $signature,
                'certificate' => $signature->signerCertificate,
            ])
            ->filter(fn (array $entry) => $entry['certificate'] !== null)
            ->unique(fn (array $entry) => $entry['certificate']->id)
            ->values();
    @endphp

    @if ($signerCertificates->isNotEmpty())
        <div class="ui-panel p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Signer certificates') }}</h2>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Monitor signer certificate status and revoke compromised credentials when needed.') }}</p>
                </div>
            </div>

            <div class="mt-4 space-y-4">
                @foreach ($signerCertificates as $entry)
                    @php
                        $signature = $entry['signature'];
                        $certificate = $entry['certificate'];
                        $isRevoked = $certificate->status === 'revoked' || $certificate->revoked_at !== null;
                    @endphp

                    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0 flex-1 space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $signature->signer?->name ?? __('Unknown signer') }}</span>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $isRevoked ? 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' }}">
                                        {{ ucfirst($certificate->status) }}
                                    </span>
                                </div>
                                <div class="space-y-1 text-sm text-zinc-600 dark:text-zinc-300">
                                    <div><span class="font-medium">{{ __('Serial:') }}</span> <span class="break-all">{{ $certificate->serial_number }}</span></div>
                                    <div><span class="font-medium">{{ __('Fingerprint:') }}</span> <span class="break-all">{{ $certificate->fingerprint_sha256 }}</span></div>
                                    <div><span class="font-medium">{{ __('Valid until:') }}</span> {{ $certificate->valid_to?->toDateTimeString() ?? '-' }}</div>
                                    @if ($certificate->revoked_at !== null)
                                        <div><span class="font-medium">{{ __('Revoked at:') }}</span> {{ $certificate->revoked_at->toDateTimeString() }}</div>
                                    @endif
                                    @if ($certificate->revocation_reason)
                                        <div><span class="font-medium">{{ __('Revocation reason:') }}</span> {{ $certificate->revocation_reason }}</div>
                                    @endif
                                </div>
                            </div>

                            @if (Auth::user()?->role === UserRole::Admin)
                                <div class="w-full max-w-sm space-y-3">
                                    @if ($isRevoked)
                                        <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-300">
                                            {{ __('Certificate revoked') }}
                                        </div>
                                    @else
                                        <flux:field>
                                            <flux:label>{{ __('Revocation reason') }}</flux:label>
                                            <flux:input wire:model="revocationReasons.{{ $certificate->id }}" type="text" placeholder="{{ __('e.g. Signer key compromised') }}" />
                                        </flux:field>
                                        <flux:button type="button" variant="outline" wire:click="revokeCertificate({{ $certificate->id }})">
                                            {{ __('Revoke certificate') }}
                                        </flux:button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="ui-panel p-6">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Tags') }}</h2>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Use tags for search and filtering.') }}</p>
        <div class="mt-4 space-y-4">
            @if ($availableTags->isNotEmpty())
                <div class="flex flex-wrap gap-3">
                    @foreach ($availableTags as $tag)
                        <label
                            class="flex cursor-pointer items-center gap-2 rounded-lg border border-zinc-200 bg-white px-3 py-2 transition hover:border-teal-400 dark:border-zinc-600 dark:bg-zinc-900 dark:hover:border-teal-500"
                            wire:key="show-tag-opt-{{ $tag->id }}"
                        >
                            <input
                                type="checkbox"
                                wire:model.live="tagIds"
                                wire:change="saveTags"
                                value="{{ $tag->id }}"
                                class="size-4 rounded border-zinc-300 text-teal-600 focus:ring-teal-500 dark:border-zinc-600 dark:bg-zinc-900"
                            />
                            <span class="text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $tag->name }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
            <flux:error name="tagIds" />

            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <flux:field class="min-w-0 flex-1">
                    <flux:label>{{ __('Quick add tag') }}</flux:label>
                    <flux:input
                        wire:model="quickTagName"
                        type="text"
                        placeholder="{{ __('New tag name') }}"
                        wire:keydown.enter.prevent="quickAddTag"
                    />
                    <flux:error name="quickTagName" />
                </flux:field>
                <flux:button type="button" variant="outline" wire:click="quickAddTag">{{ __('Add') }}</flux:button>
            </div>
        </div>
    </div>

    <div class="ui-panel p-6">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Preview') }}</h2>
        <a
            href="{{ route('documents.stream', $document) }}"
            target="_blank"
            rel="noopener noreferrer"
            class="mt-3 inline-flex items-center gap-2 text-sm font-medium text-teal-700 underline decoration-teal-500/30 underline-offset-4 transition hover:text-teal-800 hover:decoration-teal-500 dark:text-teal-300 dark:hover:text-teal-200"
        >
            {{ __('Open PDF in new tab') }}
            <span aria-hidden="true">↗</span>
        </a>
    </div>
</div>
