<?php

use App\Events\DocumentSent;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\Tag;
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

    public string $quickTagName = '';

    public function mount(Document $document): void
    {
        $this->authorize('view', $document);
        $this->document = $document->load(['documentSigners', 'signatures', 'user.contacts', 'tags']);
        $this->tagIds = $this->document->tags->pluck('id')->all();
    }

    #[On('document-updated')]
    public function refreshDocument(): void
    {
        $this->document->refresh()->load(['documentSigners', 'signatures', 'user.contacts', 'tags']);
        $this->tagIds = $this->document->tags->pluck('id')->all();
    }

    public function with(): array
    {
        return [
            'availableTags' => Auth::user()->tags()->orderBy('name')->get(),
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

        if ($this->document->status !== DocumentStatus::Draft) {
            return;
        }

        if ($this->document->documentSigners()->count() < 1) {
            $this->addError('send', __('Add at least one signer before sending.'));

            return;
        }

        if ($this->document->signatureFields()->count() < 1) {
            $this->addError('send', __('Add at least one signature field on the Prepare page before sending.'));

            return;
        }

        $this->document->update([
            'status' => DocumentStatus::Pending,
            'sent_at' => now(),
        ]);

        $this->document->documentSigners()->get()->each(function (DocumentSigner $signer): void {
            $signer->update([
                'access_token' => (string) Str::uuid(),
                'expires_at' => now()->addDays(7),
            ]);
        });

        $this->document->refresh()->load('documentSigners');
        event(new DocumentSent($this->document));
        session()->flash('status', __('Document sent for signature.'));
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
                <flux:button variant="outline" :href="route('documents.prepare', $document)" wire:navigate>
                    {{ __('Prepare fields') }}
                </flux:button>
                <flux:button variant="primary" type="button" wire:click="sendForSignature">
                    {{ __('Send for signature') }}
                </flux:button>
            @endif
            <flux:button variant="ghost" :href="route('documents.index')" wire:navigate>{{ __('Back to list') }}</flux:button>
        </div>
    </div>

    @error('send')
        <div class="rounded-2xl border border-red-200/90 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-100">
            {{ $message }}
        </div>
    @enderror

    <div class="ui-panel p-6 sm:p-8">
        <livewire:document-signers-manager :document-id="$document->id" :key="'signers-'.$document->id" />
    </div>

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
