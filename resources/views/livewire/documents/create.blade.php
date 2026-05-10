<?php

use App\Enums\DocumentStatus;
use App\Http\Requests\StoreDocumentRequest;
use App\Models\Document;
use App\Models\Tag;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.app')] class extends Component {
    use WithFileUploads;

    public string $title = '';

    public $file;

    /** @var list<int> */
    public array $tagIds = [];

    public string $quickTagName = '';

    public string $accessPassword = '';

    public string $accessPasswordConfirmation = '';

    public string $accessPasswordHint = '';

    public string $emailSubject = '';

    public string $emailMessage = '';

    public bool $auditEnabled = true;

    /** @var array<string, bool> */
    public array $auditSettings = [];

    public function mount(): void
    {
        $this->auditSettings = Document::defaultAuditSettings();
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
    }

    public function save(): void
    {
        $this->authorize('create', Document::class);

        $this->validate(array_merge(
                StoreDocumentRequest::rulesForLivewireUpload(),
                [
                    'tagIds' => ['array'],
                    'tagIds.*' => ['integer', Rule::exists('tags', 'id')->where('user_id', Auth::id())],
                    'accessPassword' => ['nullable', 'string', 'min:6', 'max:255', 'same:accessPasswordConfirmation'],
                    'accessPasswordConfirmation' => ['nullable', 'string', 'max:255'],
                    'accessPasswordHint' => ['nullable', 'string', 'max:255'],
                    'emailSubject' => ['nullable', 'string', 'max:255'],
                    'emailMessage' => ['nullable', 'string', 'max:5000'],
                    'auditEnabled' => ['boolean'],
                    'auditSettings.show_email' => ['boolean'],
                    'auditSettings.show_document_id' => ['boolean'],
                    'auditSettings.show_author' => ['boolean'],
                    'auditSettings.show_mobile' => ['boolean'],
                    'auditSettings.show_id_details' => ['boolean'],
                ]
            ));

        try {
            $path = $this->file->store('documents', (string) config('filesystems.docutrust_disk', 'local'));

            $document = Auth::user()->documents()->create([
                'title' => $this->title,
                'file_path' => $path,
                'email_subject' => trim($this->emailSubject) !== '' ? trim($this->emailSubject) : null,
                'email_message' => trim($this->emailMessage) !== '' ? trim($this->emailMessage) : null,
                'audit_enabled' => $this->auditEnabled,
                'audit_settings' => $this->auditSettings,
                'access_password_hash' => $this->accessPassword !== '' ? Hash::make($this->accessPassword) : null,
                'access_password_hint' => $this->accessPasswordHint !== '' ? trim($this->accessPasswordHint) : null,
                'status' => DocumentStatus::Draft,
            ]);
            $document->tags()->sync(array_map('intval', $this->tagIds));

            Log::channel('audit')->info('Document created', [
                'document_id' => $document->id,
                'user_id' => Auth::id(),
            ]);

            $this->redirect(route('documents.show', $document, absolute: false), navigate: true);
        } catch (\Throwable $throwable) {
            Log::channel('errors')->error('Document upload/create failed', [
                'user_id' => Auth::id(),
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);

            $this->addError('file', __('Unable to upload document right now. Please try again.'));
        }
    }
}; ?>

<div class="flex w-full min-w-0 flex-col gap-6 p-1">

    {{-- ── Page header ── --}}
    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2 text-sm text-zinc-400 dark:text-zinc-500">
                <a href="{{ route('documents.index') }}" wire:navigate
                   class="transition hover:text-zinc-700 dark:hover:text-zinc-300">
                    {{ __('Documents') }}
                </a>
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                <span class="text-zinc-600 dark:text-zinc-400">{{ __('Upload') }}</span>
            </div>
            <h1 class="mt-2 text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50 sm:text-3xl">
                {{ __('Upload document') }}
            </h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Add a PDF and give it a clear title for your team.') }}
            </p>
        </div>
    </div>

    {{-- ── Main card ── --}}
    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex flex-col lg:grid lg:grid-cols-12">

            {{-- ── Form panel ── --}}
            <div class="min-w-0 p-6 sm:p-8 lg:col-span-8 lg:border-r lg:border-zinc-200/80 dark:lg:border-zinc-800 xl:col-span-9">

                <form wire:submit="save" class="flex flex-col gap-6">

                    {{-- Title field --}}
                    <flux:field>
                        <flux:label class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">
                            {{ __('Document title') }}
                        </flux:label>
                        <flux:input
                            wire:model="title"
                            type="text"
                            required
                            autofocus
                            placeholder="{{ __('e.g. Contract agreement, NDA 2025…') }}"
                            class="w-full"
                        />
                        <flux:error name="title" />
                    </flux:field>

                    {{-- File upload --}}
                    <flux:field>
                        <flux:label class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">
                            {{ __('PDF file') }}
                        </flux:label>

                        <label
                            class="group relative flex cursor-pointer flex-col items-center justify-center gap-4 rounded-2xl border-2 border-dashed border-zinc-300 bg-zinc-50/60 px-6 py-10 text-center transition hover:border-teal-400 hover:bg-teal-50/30 dark:border-zinc-700 dark:bg-zinc-800/40 dark:hover:border-teal-600 dark:hover:bg-teal-900/10"
                        >
                            {{-- Icon --}}
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl border border-zinc-200 bg-white shadow-sm transition group-hover:border-teal-200 group-hover:bg-teal-50 dark:border-zinc-700 dark:bg-zinc-800 dark:group-hover:border-teal-800 dark:group-hover:bg-teal-950/40">
                                <svg class="h-6 w-6 text-zinc-400 transition group-hover:text-teal-500" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
                                </svg>
                            </div>

                            {{-- Text --}}
                            <div>
                                <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">
                                    {{ __('Click to choose a PDF') }}
                                    <span class="text-zinc-400 font-normal dark:text-zinc-500"> {{ __('or drag and drop') }}</span>
                                </p>
                                <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">{{ __('PDF only · Max 50 MB') }}</p>
                            </div>

                            {{-- Hidden input --}}
                            <input
                                type="file"
                                wire:model="file"
                                accept="application/pdf,.pdf"
                                class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                            />
                        </label>

                        {{-- Upload progress --}}
                        <div wire:loading wire:target="file"
                             class="mt-2 flex items-center gap-2 rounded-xl border border-teal-200 bg-teal-50 px-4 py-2.5 text-sm text-teal-700 dark:border-teal-800 dark:bg-teal-950/30 dark:text-teal-300">
                            <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            {{ __('Uploading file…') }}
                        </div>

                        {{-- File selected indicator (shown when not loading) --}}
                        <div wire:loading.remove wire:target="file" class="mt-1">
                            @if ($file)
                                <div class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-300">
                                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                    <span class="truncate font-medium">{{ $file->getClientOriginalName() }}</span>
                                </div>
                            @endif
                        </div>

                        <flux:error name="file" />
                    </flux:field>

                    <section class="space-y-4 rounded-2xl border border-zinc-200/80 bg-zinc-50/50 p-4 dark:border-zinc-700 dark:bg-zinc-800/30">
                        <div>
                            <h2 class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ __('Tags') }}</h2>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Add tags to make this document easier to search.') }}</p>
                        </div>

                        @if ($availableTags->isNotEmpty())
                            <div class="flex flex-wrap gap-3">
                                @foreach ($availableTags as $tag)
                                    <label
                                        class="flex cursor-pointer items-center gap-2 rounded-lg border border-zinc-200 bg-white px-3 py-2 transition hover:border-teal-400 dark:border-zinc-600 dark:bg-zinc-900 dark:hover:border-teal-500"
                                        wire:key="create-tag-opt-{{ $tag->id }}"
                                    >
                                        <input
                                            type="checkbox"
                                            wire:model.live="tagIds"
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
                    </section>

                    <section class="space-y-4 rounded-2xl border border-zinc-200/80 bg-zinc-50/50 p-4 dark:border-zinc-700 dark:bg-zinc-800/30">
                        <div>
                            <h2 class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ __('Document password') }}</h2>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Optional. If set, any signer must enter this shared password before opening the signing document.') }}</p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>{{ __('Password') }}</flux:label>
                                <flux:input
                                    wire:model="accessPassword"
                                    type="password"
                                    autocomplete="new-password"
                                    placeholder="{{ __('Leave blank for no password') }}"
                                />
                                <flux:error name="accessPassword" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Confirm password') }}</flux:label>
                                <flux:input
                                    wire:model="accessPasswordConfirmation"
                                    type="password"
                                    autocomplete="new-password"
                                    placeholder="{{ __('Repeat the password') }}"
                                />
                                <flux:error name="accessPasswordConfirmation" />
                            </flux:field>
                        </div>

                        <flux:field>
                            <flux:label>{{ __('Password hint') }}</flux:label>
                            <flux:input
                                wire:model="accessPasswordHint"
                                type="text"
                                placeholder="{{ __('Optional hint for recipients') }}"
                            />
                            <flux:error name="accessPasswordHint" />
                        </flux:field>
                    </section>

                    <section class="space-y-4 rounded-2xl border border-zinc-200/80 bg-zinc-50/50 p-4 dark:border-zinc-700 dark:bg-zinc-800/30">
                        <div>
                            <h2 class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ __('Invitation email') }}</h2>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Optional custom subject and message for the signer invitation email. Leave blank to use the default DocuTrust invitation copy.') }}</p>
                        </div>

                        <flux:field>
                            <flux:label>{{ __('Email subject') }}</flux:label>
                            <flux:input
                                wire:model="emailSubject"
                                type="text"
                                placeholder="{{ __('Please review and sign this document') }}"
                            />
                            <flux:error name="emailSubject" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Email message') }}</flux:label>
                            <flux:textarea
                                wire:model="emailMessage"
                                rows="4"
                                placeholder="{{ __('Hello, please review and sign this document at your earliest convenience.') }}"
                            />
                            <flux:error name="emailMessage" />
                        </flux:field>
                    </section>

                    <section class="space-y-4 rounded-2xl border border-zinc-200/80 bg-zinc-50/50 p-4 dark:border-zinc-700 dark:bg-zinc-800/30">
                        <div>
                            <h2 class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ __('Public audit trail') }}</h2>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Control what outsiders can see on the public verification page after this document is completed.') }}</p>
                        </div>

                        <div class="flex items-start gap-3 rounded-xl border border-zinc-200/80 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900/30">
                            <flux:checkbox wire:model.live="auditEnabled" :label="__('Enable public audit details')" />
                        </div>

                        @if ($auditEnabled)
                            <div class="grid gap-3 sm:grid-cols-2">
                                <flux:checkbox wire:model="auditSettings.show_email" :label="__('Show signer email')" />
                                <flux:checkbox wire:model="auditSettings.show_document_id" :label="__('Show document ID')" />
                                <flux:checkbox wire:model="auditSettings.show_author" :label="__('Show document author')" />
                                <flux:checkbox wire:model="auditSettings.show_mobile" :label="__('Show verified mobile')" />
                                <flux:checkbox wire:model="auditSettings.show_id_details" :label="__('Show verified ID details')" />
                            </div>
                        @else
                            <div class="rounded-xl border border-zinc-200/80 bg-white px-4 py-3 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/30 dark:text-zinc-300">
                                {{ __('Signer and timeline details will be hidden from the public verification page.') }}
                            </div>
                        @endif
                    </section>

                    {{-- Actions --}}
                    <div class="flex flex-wrap items-center gap-3 border-t border-zinc-100 pt-5 dark:border-zinc-800">
                        <flux:button variant="primary" type="submit">
                            <span class="inline-flex items-center gap-1.5">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
                                <span>{{ __('Save document') }}</span>
                            </span>
                        </flux:button>
                        <flux:button variant="ghost" :href="route('documents.index')" wire:navigate type="button">
                            {{ __('Cancel') }}
                        </flux:button>
                    </div>

                </form>
            </div>

            {{-- ── Sidebar guidelines ── --}}
            <aside
                class="border-t border-zinc-200/80 bg-zinc-50/70 p-6 sm:p-8 lg:col-span-4 lg:border-l-0 lg:border-t-0 xl:col-span-3 dark:border-zinc-800 dark:bg-zinc-800/30"
                aria-label="{{ __('Upload guidelines') }}"
            >
                <div class="flex items-center gap-2">
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-teal-100 dark:bg-teal-900/50">
                        <svg class="h-3.5 w-3.5 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/></svg>
                    </div>
                    <h2 class="text-xs font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">
                        {{ __('Guidelines') }}
                    </h2>
                </div>

                <ul class="mt-5 space-y-4">
                    <li class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-teal-100 dark:bg-teal-900/40">
                            <svg class="h-3 w-3 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                        </span>
                        <span class="text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">
                            {{ __('PDF files only.') }}
                        </span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-teal-100 dark:bg-teal-900/40">
                            <svg class="h-3 w-3 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                        </span>
                        <span class="text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">
                            {{ __('Maximum file size 50 MB.') }}
                        </span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-teal-100 dark:bg-teal-900/40">
                            <svg class="h-3 w-3 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                        </span>
                        <span class="text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">
                            {{ __('Use a clear title so your team can find this document later.') }}
                        </span>
                    </li>
                </ul>

                <div class="mt-8 rounded-xl border border-amber-200/80 bg-amber-50/60 p-4 dark:border-amber-900/40 dark:bg-amber-950/20">
                    <p class="text-xs font-semibold text-amber-700 dark:text-amber-400">{{ __('After uploading') }}</p>
                    <p class="mt-1 text-xs leading-relaxed text-amber-600/90 dark:text-amber-400/80">
                        {{ __("You'll be able to add signers and configure signing fields on the next screen.") }}
                    </p>
                </div>
            </aside>

        </div>
    </div>

</div>
