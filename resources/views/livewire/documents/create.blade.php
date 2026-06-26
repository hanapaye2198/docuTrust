<?php

use App\Enums\DocumentStatus;
use App\Models\Document;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.app')] class extends Component {
    use WithFileUploads;

    public string $title = '';

    public $file;

    public bool $documentSaved = false;

    public int $savedDocumentId = 0;

    public string $accessPassword = '';

    public string $accessPasswordConfirmation = '';

    public string $accessPasswordHint = '';

    public string $emailSubject = '';

    public string $emailMessage = '';

    public bool $auditEnabled = true;

    /** @var array<string, bool> */
    public array $auditSettings = [];

    public bool $advancedOpen = false;

    public function mount(): void
    {
        $this->auditSettings = Document::defaultAuditSettings();
    }

    public function updatedFile(): void
    {
        $this->authorize('create', Document::class);

        $this->validateOnly('file', [
            'file' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'extensions:pdf'],
        ]);

        if ($this->file === null || $this->savedDocumentId > 0) {
            return;
        }

        if ($this->title === '') {
            $this->title = pathinfo($this->file->getClientOriginalName(), PATHINFO_FILENAME);
        }

        try {
            $path = $this->file->store('documents', (string) config('filesystems.docutrust_disk', 'local'));

            $document = Auth::user()->documents()->create([
                'title'     => $this->title ?: __('Untitled Document'),
                'file_path' => $path,
                'status'    => DocumentStatus::Draft,
            ]);

            Log::channel('audit')->info('Document created', [
                'document_id' => $document->id,
                'user_id'     => Auth::id(),
            ]);

            $this->savedDocumentId = $document->id;
            $this->documentSaved   = true;
        } catch (\Throwable $throwable) {
            Log::channel('errors')->error('Document upload/create failed', [
                'user_id'   => Auth::id(),
                'exception' => $throwable::class,
                'message'   => $throwable->getMessage(),
            ]);

            $this->addError('file', __('Unable to upload document right now. Please try again.'));
        }
    }

    public function updatedTitle(): void
    {
        if ($this->savedDocumentId > 0 && trim($this->title) !== '') {
            Document::whereKey($this->savedDocumentId)->update(['title' => $this->title]);
        }
    }

    public function goToCanvas(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $doc = Document::findOrFail($this->savedDocumentId);
        $this->authorize('update', $doc);

        // Ensure at least one signer has been added before proceeding to the canvas.
        if ($doc->documentSigners()->count() === 0) {
            $this->addError('signers', __('Add at least one signer before preparing fields.'));
            return;
        }

        $this->validate([
            'accessPassword'                 => ['nullable', 'string', 'min:6', 'max:255', 'same:accessPasswordConfirmation'],
            'accessPasswordConfirmation'     => ['nullable', 'string', 'max:255'],
            'accessPasswordHint'             => ['nullable', 'string', 'max:255'],
            'emailSubject'                   => ['nullable', 'string', 'max:255'],
            'emailMessage'                   => ['nullable', 'string', 'max:5000'],
            'auditEnabled'                   => ['boolean'],
            'auditSettings.show_email'       => ['boolean'],
            'auditSettings.show_document_id' => ['boolean'],
            'auditSettings.show_author'      => ['boolean'],
            'auditSettings.show_mobile'      => ['boolean'],
            'auditSettings.show_id_details'  => ['boolean'],
        ]);

        $doc->update([
            'title'                => $this->title,
            'email_subject'        => trim($this->emailSubject) !== '' ? trim($this->emailSubject) : null,
            'email_message'        => trim($this->emailMessage) !== '' ? trim($this->emailMessage) : null,
            'audit_enabled'        => $this->auditEnabled,
            'audit_settings'       => $this->auditSettings,
            'access_password_hash' => $this->accessPassword !== '' ? Hash::make($this->accessPassword) : null,
            'access_password_hint' => $this->accessPasswordHint !== '' ? trim($this->accessPasswordHint) : null,
        ]);

        $this->redirect(route('documents.prepare', ['document' => $this->savedDocumentId]), navigate: true);
    }
}; ?>

<div class="flex w-full min-w-0 flex-col gap-4 p-1">

    {{-- ── Breadcrumb + title ── --}}
    <div>
        <div class="flex items-center gap-2 text-sm text-zinc-400 dark:text-zinc-500">
            <a href="{{ route('documents.index') }}" wire:navigate
               class="transition hover:text-zinc-700 dark:hover:text-zinc-300">{{ __('Documents') }}</a>
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            <span class="text-zinc-600 dark:text-zinc-400">{{ __('Prepare Documents') }}</span>
        </div>
        <h1 class="mt-1.5 text-xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50 sm:text-2xl">
            {{ __('Prepare Document') }}
        </h1>
    </div>

    {{-- ── Single card ── --}}
    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">

        {{-- ── Upload Documents for Signature ── --}}
        <div class="px-6 py-5">
            <h2 class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ __('Upload Documents for Signature') }}</h2>
            <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Max 50 MB file size and 1,000 pages for each individual document.') }}</p>

            <div class="mt-3 space-y-3">
                @if (! $documentSaved)
                    <div
                        x-data="{ progress: 0, dragging: false }"
                        x-on:livewire-upload-start="progress = 0"
                        x-on:livewire-upload-finish="progress = 0"
                        x-on:livewire-upload-error="progress = 0"
                        x-on:livewire-upload-progress="progress = $event.detail.progress"
                        x-on:dragover.prevent="dragging = true"
                        x-on:dragleave.prevent="dragging = false"
                        x-on:drop.prevent="
                            dragging = false;
                            if ($event.dataTransfer.files.length) {
                                $refs.docFile.files = $event.dataTransfer.files;
                                $refs.docFile.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        "
                    >
                        {{-- Drop zone (hidden while uploading) --}}
                        <label
                            wire:loading.remove wire:target="file"
                            x-bind:class="dragging
                                ? 'border-teal-500 bg-teal-50/80 dark:border-teal-500 dark:bg-teal-900/20'
                                : 'border-zinc-300 bg-zinc-50/60 hover:border-teal-400 hover:bg-teal-50/30 dark:border-zinc-700 dark:bg-zinc-800/40 dark:hover:border-teal-600 dark:hover:bg-teal-900/10'"
                            class="group relative flex cursor-pointer items-center justify-center gap-3 rounded-xl border-2 border-dashed px-5 py-6 transition"
                        >
                            <svg class="h-5 w-5 shrink-0 text-zinc-400 transition group-hover:text-teal-500" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
                            </svg>
                            <span class="text-sm font-medium text-zinc-600 dark:text-zinc-300">
                                <span class="text-teal-600 dark:text-teal-400">{{ __('Upload') }}</span>
                                {{ __('or Drop File') }}
                            </span>
                            <input x-ref="docFile" type="file" wire:model="file" accept="application/pdf,.pdf"
                                   class="absolute inset-0 h-full w-full cursor-pointer opacity-0" />
                        </label>

                        {{-- Progress card (visible only while uploading) --}}
                        <div wire:loading wire:target="file"
                             class="overflow-hidden rounded-xl border border-teal-200 bg-white dark:border-teal-800 dark:bg-zinc-900">
                            <div class="px-4 pt-4 pb-3">
                                <div class="flex items-end justify-between gap-4">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-widest text-teal-600 dark:text-teal-400">{{ __('Upload progress') }}</p>
                                        <p class="mt-0.5 text-sm font-medium text-zinc-700 dark:text-zinc-200">
                                            <span x-text="progress > 0 ? '{{ __('Uploading document…') }}' : '{{ __('Preparing upload…') }}'"></span>
                                        </p>
                                    </div>
                                    <div class="text-2xl font-semibold tabular-nums text-teal-600 dark:text-teal-400"
                                         x-text="(progress > 0 ? progress : 0) + '%'"></div>
                                </div>
                                <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-teal-100 dark:bg-teal-900/40">
                                    <div class="h-full rounded-full bg-gradient-to-r from-teal-400 via-teal-500 to-teal-600 transition-all duration-300"
                                         :style="'width: ' + Math.max(progress, 6) + '%'"></div>
                                </div>
                                <p class="mt-2 text-xs text-zinc-400 dark:text-zinc-500">{{ __('Please keep this page open until the upload finishes.') }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Uploaded file row --}}
                @if ($documentSaved)
                    <div class="flex items-center gap-3 rounded-lg border border-zinc-200 bg-zinc-50/70 px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-800/40">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-red-100 dark:bg-red-900/30">
                            <svg class="h-3.5 w-3.5 text-red-600 dark:text-red-400" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M7.5 2.25A.75.75 0 0 1 8.25 3v.75h7.5V3a.75.75 0 0 1 1.5 0v.75H18A2.25 2.25 0 0 1 20.25 6v.75H3.75V6A2.25 2.25 0 0 1 6 3.75h.75V3a.75.75 0 0 1 .75-.75ZM3.75 9h16.5v10.5A2.25 2.25 0 0 1 18 21.75H6A2.25 2.25 0 0 1 3.75 19.5V9Z"/>
                            </svg>
                        </span>
                        <span class="min-w-0 flex-1 truncate text-sm font-medium text-zinc-800 dark:text-zinc-100">
                            {{ $file?->getClientOriginalName() ?? $title }}
                        </span>
                        <span class="shrink-0 text-xs font-medium text-emerald-600 dark:text-emerald-400">{{ __('Uploaded') }}</span>
                    </div>
                @endif

                <flux:error name="file" />
            </div>

        </div>

        <div class="border-t border-zinc-100 dark:border-zinc-800"></div>

        {{-- ── Participants ── --}}
        <div class="px-6 py-5">
            <h2 class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ __('Participants') }}</h2>
            <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Who needs to sign, approve, or receive a copy of this document?') }}</p>

            <div class="mt-3">
                @if ($documentSaved)
                    @error('signers')
                        <div class="mb-3 flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-600 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                            </svg>
                            {{ $message }}
                        </div>
                    @enderror
                    <livewire:document-signers-manager
                        :document-id="$savedDocumentId"
                        wire:key="signers-{{ $savedDocumentId }}"
                    />
                @else
                    <p class="py-2 text-sm text-zinc-400 dark:text-zinc-500">{{ __('Upload a document above to add participants.') }}</p>
                @endif
            </div>
        </div>

        <div class="border-t border-zinc-100 dark:border-zinc-800"></div>

        {{-- ── Document Password ── --}}
        <div class="px-6 py-5">
            <h2 class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ __('Document Password') }}</h2>
            <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Password should be given to all signers to view and sign the document.') }}</p>

            <div class="mt-3">
                <flux:input
                    wire:model="accessPassword"
                    type="password"
                    autocomplete="new-password"
                    placeholder="{{ __('Document password') }}"
                    viewable
                />
                <flux:error name="accessPassword" />
                <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">{{ __('Password should be given to all signers to view and sign the document.') }}</p>
            </div>

            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div>
                    <flux:input
                        wire:model="accessPasswordConfirmation"
                        type="password"
                        autocomplete="new-password"
                        placeholder="{{ __('Confirm password') }}"
                    />
                    <flux:error name="accessPasswordConfirmation" />
                </div>
                <div>
                    <flux:input
                        wire:model="accessPasswordHint"
                        type="text"
                        placeholder="{{ __('Password hint (optional)') }}"
                    />
                    <flux:error name="accessPasswordHint" />
                </div>
            </div>
        </div>

        <div class="border-t border-zinc-100 dark:border-zinc-800"></div>

        {{-- ── Advanced settings (collapsible) ── --}}
        <div>
            <button
                type="button"
                wire:click="$toggle('advancedOpen')"
                class="flex w-full items-center justify-between px-6 py-4 text-left transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
            >
                <span class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">{{ __('Advanced settings') }}</span>
                <svg class="h-4 w-4 shrink-0 text-zinc-400 transition-transform duration-200 {{ $advancedOpen ? 'rotate-180' : '' }}"
                     fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                </svg>
            </button>

            @if ($advancedOpen)
                <div class="border-t border-zinc-100 px-6 py-5 dark:border-zinc-800">
                    <div class="space-y-5">

                        {{-- Invitation email --}}
                        <div class="space-y-3">
                            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Invitation email') }}</p>
                            <flux:input wire:model="emailSubject" type="text"
                                placeholder="{{ __('Email subject (optional)') }}" />
                            <flux:error name="emailSubject" />
                            <flux:textarea wire:model="emailMessage" rows="3"
                                placeholder="{{ __('Email message (optional)') }}" />
                            <flux:error name="emailMessage" />
                        </div>

                        {{-- Audit trail --}}
                        <div class="space-y-3">
                            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Public audit trail') }}</p>
                            <flux:checkbox wire:model.live="auditEnabled" :label="__('Enable public audit details')" />
                            @if ($auditEnabled)
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <flux:checkbox wire:model="auditSettings.show_email" :label="__('Show signer email')" />
                                    <flux:checkbox wire:model="auditSettings.show_document_id" :label="__('Show document ID')" />
                                    <flux:checkbox wire:model="auditSettings.show_author" :label="__('Show document author')" />
                                    <flux:checkbox wire:model="auditSettings.show_mobile" :label="__('Show verified mobile')" />
                                    <flux:checkbox wire:model="auditSettings.show_id_details" :label="__('Show verified ID details')" />
                                </div>
                            @endif
                        </div>

                    </div>
                </div>
            @endif
        </div>

        {{-- ── Bottom actions ── --}}
        <div class="flex items-center justify-between border-t border-zinc-100 px-6 py-4 dark:border-zinc-800">
            <flux:button
                variant="ghost"
                :href="$documentSaved ? route('documents.show', $savedDocumentId) : route('documents.index')"
                wire:navigate
                type="button"
            >
                {{ __('Cancel') }}
            </flux:button>

            <flux:button
                variant="primary"
                type="button"
                wire:click="goToCanvas"
                wire:loading.attr="disabled"
                wire:target="goToCanvas"
                :disabled="! $documentSaved"
            >
                <span class="inline-flex items-center gap-1.5">
                    {{ __('Prepare document') }}
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                </span>
            </flux:button>
        </div>

    </div>{{-- /single card --}}

</div>
