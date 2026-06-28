<?php

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
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

    /**
     * Search for verified DocuTrust users in the same organization.
     * Called from Alpine.js in the Advanced Settings invite search.
     *
     * @return array<int, array{id: int, name: string, email: string}>
     */
    public function searchVerifiedUsersForInvite(string $term): array
    {
        if ($this->savedDocumentId === 0 || strlen(trim($term)) < 2) {
            return [];
        }

        $doc = Document::findOrFail($this->savedDocumentId);
        $this->authorize('update', $doc);

        $like = '%'.trim($term).'%';

        return User::query()
            ->whereNotNull('email_verified_at')
            ->where(function ($q) use ($like): void {
                $q->where('name', 'like', $like)
                  ->orWhere('email', 'like', $like);
            })
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(fn (User $u) => [
                'id'    => $u->id,
                'name'  => $u->name,
                'email' => $u->email,
            ])
            ->values()
            ->all();
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

<div class="relative isolate flex w-full min-w-0 flex-col gap-5">
    <div class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-72 bg-[radial-gradient(circle_at_top_left,rgba(20,184,166,0.14),transparent_32%),radial-gradient(circle_at_top_right,rgba(59,130,246,0.12),transparent_28%)] dark:bg-[radial-gradient(circle_at_top_left,rgba(20,184,166,0.18),transparent_32%),radial-gradient(circle_at_top_right,rgba(59,130,246,0.16),transparent_28%)]"></div>

    {{-- ── Breadcrumb + title ── --}}
    <div class="rounded-3xl border border-white/70 bg-white/85 p-5 shadow-sm shadow-zinc-200/70 backdrop-blur dark:border-white/10 dark:bg-zinc-950/70 dark:shadow-black/20 sm:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                <div class="flex items-center gap-2 text-sm text-zinc-400 dark:text-zinc-500">
                    <a href="{{ route('documents.index') }}" wire:navigate
                       class="transition hover:text-zinc-700 dark:hover:text-zinc-300">{{ __('Documents') }}</a>
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                    <span class="text-zinc-600 dark:text-zinc-400">{{ __('Prepare Documents') }}</span>
                </div>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-zinc-950 dark:text-white sm:text-3xl">
                    {{ __('Prepare Document') }}
                </h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    {{ __('Upload a PDF, add participants, protect access, then place signing fields on the document canvas.') }}
                </p>
            </div>

            <div class="grid grid-cols-3 gap-2 rounded-2xl border border-zinc-200/70 bg-zinc-50/70 p-2 dark:border-white/10 dark:bg-white/5 sm:min-w-80">
                <div class="rounded-xl bg-white px-3 py-3 text-center shadow-sm dark:bg-zinc-900/80">
                    <p class="text-sm font-bold text-teal-600 dark:text-teal-300">1</p>
                    <p class="mt-1 text-[10px] font-semibold uppercase tracking-wider text-zinc-400">{{ __('Upload') }}</p>
                </div>
                <div class="rounded-xl bg-white px-3 py-3 text-center shadow-sm dark:bg-zinc-900/80">
                    <p class="text-sm font-bold text-blue-600 dark:text-blue-300">2</p>
                    <p class="mt-1 text-[10px] font-semibold uppercase tracking-wider text-zinc-400">{{ __('Signers') }}</p>
                </div>
                <div class="rounded-xl bg-white px-3 py-3 text-center shadow-sm dark:bg-zinc-900/80">
                    <p class="text-sm font-bold text-indigo-600 dark:text-indigo-300">3</p>
                    <p class="mt-1 text-[10px] font-semibold uppercase tracking-wider text-zinc-400">{{ __('Prepare') }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Single card ── --}}
    <div class="overflow-hidden rounded-3xl border border-white/70 bg-white/90 shadow-xl shadow-zinc-200/60 backdrop-blur dark:border-white/10 dark:bg-zinc-950/80 dark:shadow-black/20">

        {{-- ── Upload Documents for Signature ── --}}
        <div class="px-5 py-5 sm:px-6">
            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-teal-600 dark:text-teal-300">{{ __('Document upload') }}</p>
                            <h2 class="mt-1 text-lg font-bold tracking-tight text-zinc-950 dark:text-white">{{ __('Upload Documents for Signature') }}</h2>
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Add the PDF that signers will review and complete.') }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2 text-[11px] font-semibold">
                            <span class="rounded-full bg-teal-50 px-2.5 py-1 text-teal-700 dark:bg-teal-500/10 dark:text-teal-300">{{ __('PDF only') }}</span>
                            <span class="rounded-full bg-blue-50 px-2.5 py-1 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300">{{ __('Max 50 MB') }}</span>
                            <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">{{ __('1,000 pages') }}</span>
                        </div>
                    </div>

            <div class="mt-4 space-y-3">
                @if (! $documentSaved)
                    <div
                        x-data="{ progress: 0, dragging: false }"
                        class="relative overflow-hidden rounded-3xl border-2 border-dashed border-blue-200 bg-gradient-to-br from-blue-50 via-white to-teal-50 px-5 py-7 text-center shadow-[0_18px_48px_-28px_rgba(37,99,235,0.45)] transition dark:border-blue-900/60 dark:from-blue-950/30 dark:via-zinc-950 dark:to-teal-950/20 sm:py-9"
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
                                $refs.docFile.files = $event.dataTransfer.files;
                                $refs.docFile.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        "
                    >
                        <div class="pointer-events-none absolute -left-8 -top-8 size-24 rounded-full bg-blue-200/50 blur-2xl dark:bg-blue-500/10"></div>
                        <div class="pointer-events-none absolute -bottom-10 -right-10 size-28 rounded-full bg-teal-200/60 blur-2xl dark:bg-teal-500/10"></div>

                        <div class="pointer-events-none absolute inset-x-4 top-3 flex justify-center">
                            <span class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-white/90 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-blue-700 shadow-sm dark:border-blue-800/60 dark:bg-zinc-900/80 dark:text-blue-300">
                                <flux:icon.sparkles class="size-3.5" />
                                {{ __('Ready for signing workflow') }}
                            </span>
                        </div>

                        <div class="relative mx-auto mt-7 flex size-16 items-center justify-center rounded-3xl bg-white text-blue-600 shadow-sm ring-1 ring-blue-100 dark:bg-zinc-900 dark:text-blue-400 dark:ring-blue-900/60">
                            <span class="absolute inset-0 rounded-3xl bg-blue-400/20 animate-ping"></span>
                            <flux:icon.cloud-arrow-up class="relative size-9" />
                        </div>

                        <p class="mt-5 text-lg font-bold text-zinc-900 dark:text-zinc-100">
                            {{ __('Drop PDF here') }}
                        </p>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                            {{ __('or') }}
                            <button type="button" class="font-semibold text-blue-600 underline-offset-2 hover:underline dark:text-blue-400" x-on:click="$refs.docFile.click()">{{ __('browse your files') }}</button>
                        </p>
                        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('PDF only · up to 50 MB · encrypted in transit') }}</p>
                        <input x-ref="docFile" type="file" wire:model="file" accept="application/pdf,.pdf" class="sr-only" />

                        {{-- Progress card (visible only while uploading) --}}
                        <div wire:loading wire:target="file"
                             class="relative mx-auto mt-5 max-w-md overflow-hidden rounded-2xl border border-blue-200 bg-white/90 text-left shadow-sm dark:border-blue-900/50 dark:bg-zinc-950/70">
                            <div class="p-4">
                                <div class="flex items-end justify-between gap-4">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700 dark:text-blue-300">{{ __('Upload progress') }}</p>
                                        <p class="mt-0.5 text-sm font-medium text-zinc-700 dark:text-zinc-200">
                                            <span x-text="progress > 0 ? '{{ __('Uploading document…') }}' : '{{ __('Preparing upload…') }}'"></span>
                                        </p>
                                    </div>
                                    <div class="text-2xl font-semibold tabular-nums text-blue-700 dark:text-blue-300"
                                         x-text="(progress > 0 ? progress : 0) + '%'"></div>
                                </div>
                                <div class="mt-3 h-3 overflow-hidden rounded-full bg-blue-100 dark:bg-blue-950/50">
                                    <div class="h-full rounded-full bg-gradient-to-r from-blue-500 via-indigo-500 to-teal-500 transition-all duration-300"
                                         :style="'width: ' + Math.max(progress, 8) + '%'"></div>
                                </div>
                                <p class="mt-2 text-xs text-zinc-400 dark:text-zinc-500">{{ __('Please keep this page open until the upload finishes.') }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Template shortcut --}}
                @if (! $documentSaved)
                    <p class="text-center text-xs text-zinc-400 dark:text-zinc-500">
                        {{ __('Have a template?') }}
                        <a href="{{ route('templates.index') }}" wire:navigate
                           class="font-medium text-teal-600 hover:text-teal-700 dark:text-teal-400 dark:hover:text-teal-300">
                            {{ __('Start from a template →') }}
                        </a>
                    </p>
                @endif

                {{-- Uploaded file row --}}
                @if ($documentSaved)
                    <div class="flex min-h-32 items-center gap-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 dark:border-emerald-900/50 dark:bg-emerald-950/20">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-white text-emerald-600 shadow-sm ring-1 ring-emerald-100 dark:bg-zinc-950 dark:text-emerald-400 dark:ring-emerald-900/50">
                            <flux:icon.document-text class="size-7" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-emerald-900 dark:text-emerald-100">
                                {{ $file?->getClientOriginalName() ?? $title }}
                            </p>
                            <p class="text-xs text-emerald-700 dark:text-emerald-300">{{ __('PDF uploaded and ready for participants.') }}</p>
                        </div>
                        <span class="shrink-0 rounded-full bg-white px-3 py-1 text-xs font-semibold text-emerald-700 shadow-sm dark:bg-emerald-500/10 dark:text-emerald-300">{{ __('Uploaded') }}</span>
                    </div>
                @endif

                <flux:error name="file" />
            </div>

                </div>

                <aside class="rounded-3xl border border-zinc-200/80 bg-zinc-50/80 p-5 dark:border-white/10 dark:bg-white/[0.04]">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-400 dark:text-zinc-500">{{ __('What happens next') }}</p>
                    <div class="mt-4 space-y-3">
                        <div class="flex gap-3">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-teal-100 text-xs font-bold text-teal-700 dark:bg-teal-500/10 dark:text-teal-300">1</span>
                            <div>
                                <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Add participants') }}</p>
                                <p class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">{{ __('Choose signers, approvers, and recipients after upload.') }}</p>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700 dark:bg-blue-500/10 dark:text-blue-300">2</span>
                            <div>
                                <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Secure access') }}</p>
                                <p class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">{{ __('Optionally add a shared document password and hint.') }}</p>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">3</span>
                            <div>
                                <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Place fields') }}</p>
                                <p class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">{{ __('Prepare signature, name, date, and checkbox fields on the canvas.') }}</p>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>

        </div>

        <div class="border-t border-zinc-100 dark:border-white/10"></div>

        {{-- ── Participants ── --}}
        <div class="px-5 py-5 sm:px-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-600 dark:text-blue-300">{{ __('Participants') }}</p>
                    <h2 class="mt-1 text-lg font-bold tracking-tight text-zinc-950 dark:text-white">{{ __('Who needs to act?') }}</h2>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Add signers, approvers, or recipients for this document.') }}</p>
                </div>
                <span class="inline-flex w-fit items-center rounded-full bg-zinc-100 px-3 py-1 text-xs font-semibold text-zinc-600 dark:bg-white/10 dark:text-zinc-300">
                    {{ $documentSaved ? __('Ready') : __('Upload required') }}
                </span>
            </div>

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
                    <div class="rounded-2xl border border-dashed border-zinc-200 bg-zinc-50/70 px-4 py-6 text-center dark:border-white/10 dark:bg-white/[0.03]">
                        <flux:icon.user-group class="mx-auto size-8 text-zinc-300 dark:text-zinc-600" />
                        <p class="mt-2 text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Upload a document above to add participants.') }}</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="border-t border-zinc-100 dark:border-white/10"></div>

        {{-- ── Document Password ── --}}
        <div class="px-5 py-5 sm:px-6">
            <div class="rounded-3xl border border-zinc-200/80 bg-zinc-50/70 p-5 dark:border-white/10 dark:bg-white/[0.03]">
                <div class="flex items-start gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                        <flux:icon.lock-closed class="size-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Document Password') }}</h2>
                        <p class="mt-1 text-xs leading-5 text-zinc-500 dark:text-zinc-400">{{ __('Optional. Share this password with signers so only invited participants can view and sign the document.') }}</p>
                    </div>
                </div>

                <div class="mt-4">
                    <flux:input
                        wire:model="accessPassword"
                        type="password"
                        autocomplete="new-password"
                        placeholder="{{ __('Document password') }}"
                        viewable
                    />
                    <flux:error name="accessPassword" />
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
        </div>

        <div class="border-t border-zinc-100 dark:border-white/10"></div>

        {{-- ── Advanced settings (collapsible) ── --}}
        <div>
            <button
                type="button"
                wire:click="$toggle('advancedOpen')"
                class="flex w-full items-center justify-between px-5 py-4 text-left transition hover:bg-zinc-50 dark:hover:bg-white/[0.03] sm:px-6"
            >
                <span class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">{{ __('Advanced settings') }}</span>
                <svg class="h-4 w-4 shrink-0 text-zinc-400 transition-transform duration-200 {{ $advancedOpen ? 'rotate-180' : '' }}"
                     fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                </svg>
            </button>

            @if ($advancedOpen)
                <div class="border-t border-zinc-100 px-5 py-5 dark:border-white/10 sm:px-6">
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

                            {{-- Invite verified DocuTrust accounts --}}
                            <div>
                                <p class="mb-1.5 text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Invite verified accounts') }}</p>

                                @if ($documentSaved)
                                    <div x-data="{
                                        term: '',
                                        results: [],
                                        selected: [],
                                        loading: false,
                                        focused: false,
                                        async search() {
                                            if (this.term.length < 2) { this.results = []; return; }
                                            this.loading = true;
                                            try {
                                                const res = await $wire.searchVerifiedUsersForInvite(this.term);
                                                const selectedIds = this.selected.map(s => s.id);
                                                this.results = (res ?? []).filter(u => !selectedIds.includes(u.id));
                                            } finally { this.loading = false; }
                                        },
                                        pick(user) {
                                            this.selected.push(user);
                                            $wire.dispatch('add-verified-signer', { userId: user.id });
                                            this.term = '';
                                            this.results = [];
                                            $nextTick(() => this.$refs.searchInput.focus());
                                        },
                                        remove(userId) {
                                            this.selected = this.selected.filter(s => s.id !== userId);
                                        }
                                    }" class="relative">

                                        {{-- Input box with chips --}}
                                        <div
                                            @click="$refs.searchInput.focus()"
                                            :class="focused ? 'ring-2 ring-teal-500 border-teal-500' : 'border-zinc-200 dark:border-zinc-700'"
                                            class="flex min-h-[42px] cursor-text flex-wrap items-center gap-1.5 rounded-2xl border bg-white px-3 py-2 transition dark:bg-zinc-900">

                                            {{-- Selected chips --}}
                                            <template x-for="user in selected" :key="user.id">
                                                <span class="inline-flex items-center gap-1 rounded-full bg-teal-100 py-0.5 pl-2 pr-1 text-xs font-medium text-teal-800 dark:bg-teal-900/40 dark:text-teal-200">
                                                    <span x-text="user.name"></span>
                                                    <button type="button"
                                                        @click.stop="remove(user.id)"
                                                        class="flex h-4 w-4 items-center justify-center rounded-full hover:bg-teal-200 dark:hover:bg-teal-800">
                                                        <svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                                                        </svg>
                                                    </button>
                                                </span>
                                            </template>

                                            {{-- Search input --}}
                                            <input
                                                x-ref="searchInput"
                                                type="text"
                                                x-model="term"
                                                @input.debounce.300ms="search()"
                                                @focus="focused = true"
                                                @blur="focused = false; setTimeout(() => results = [], 200)"
                                                placeholder="{{ __('Search name or email…') }}"
                                                class="min-w-[140px] flex-1 bg-transparent text-sm text-zinc-800 placeholder-zinc-400 focus:outline-none dark:text-zinc-100 dark:placeholder-zinc-500"
                                            />

                                            <svg x-show="loading" class="h-4 w-4 shrink-0 animate-spin text-teal-500" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"/>
                                            </svg>
                                        </div>

                                        {{-- Dropdown results --}}
                                        <ul x-show="results.length > 0" x-transition
                                            class="absolute left-0 right-0 top-full z-30 mt-1 max-h-56 overflow-auto rounded-xl border border-zinc-200 bg-white py-1 shadow-xl dark:border-zinc-700 dark:bg-zinc-900"
                                            role="listbox">
                                            <template x-for="user in results" :key="user.id">
                                                <li>
                                                    <button type="button"
                                                        @mousedown.prevent="pick(user)"
                                                        class="flex w-full items-center gap-3 px-3 py-2.5 text-left transition hover:bg-teal-50 dark:hover:bg-teal-900/20">
                                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-teal-100 text-sm font-bold uppercase text-teal-700 dark:bg-teal-900/40 dark:text-teal-300"
                                                            x-text="user.name.charAt(0)"></span>
                                                        <div class="min-w-0 flex-1">
                                                            <p class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-50" x-text="user.name"></p>
                                                            <p class="truncate text-xs text-zinc-500 dark:text-zinc-400" x-text="user.email"></p>
                                                        </div>
                                                        <span class="flex shrink-0 items-center gap-1 rounded-full bg-teal-100 px-2 py-0.5 text-xs font-medium text-teal-700 dark:bg-teal-900/40 dark:text-teal-300">
                                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75"/>
                                                            </svg>
                                                            {{ __('Verified') }}
                                                        </span>
                                                    </button>
                                                </li>
                                            </template>
                                        </ul>

                                        <p x-show="term.length >= 2 && results.length === 0 && !loading"
                                           class="mt-1.5 text-xs text-zinc-400 dark:text-zinc-500">
                                            {{ __('No verified accounts found.') }}
                                        </p>
                                    </div>
                                @else
                                    <div class="flex items-center gap-2 rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-800/50">
                                        <svg class="h-4 w-4 shrink-0 text-zinc-300 dark:text-zinc-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                                        </svg>
                                        <span class="text-sm text-zinc-400 dark:text-zinc-500">{{ __('Upload a document first to search verified accounts.') }}</span>
                                    </div>
                                @endif
                            </div>
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
        <div class="flex flex-col-reverse gap-3 border-t border-zinc-100 bg-zinc-50/60 px-5 py-4 dark:border-white/10 dark:bg-white/[0.03] sm:flex-row sm:items-center sm:justify-between sm:px-6">
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
