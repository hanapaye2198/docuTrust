<?php

use App\Enums\TemplateRoleType;
use App\Enums\TemplateSigningMethod;
use App\Models\Tag;
use App\Models\Template;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.app')] class extends Component {
    use WithFileUploads;

    public ?int $templateId = null;

    public string $name = '';

    /** @var list<string> */
    public array $storedFilePaths = [];

    /** @var array<int, mixed> */
    public array $newUploads = [];

    public bool $documentWorkflow = false;

    /** @var array<int, array{role_name: string, role_type: string}> */
    public array $roles = [];

    public ?string $emailSubject = null;

    public ?string $emailMessage = null;

    public string $signingMethod = 'docutrust_sign';

    public bool $auditEnabled = true;

    /** @var array<string, bool> */
    public array $auditSettings = [];

    /** @var list<int> */
    public array $tagIds = [];

    public string $quickTagName = '';

    public function mount(?Template $template = null): void
    {
        if ($template !== null) {
            $this->authorize('update', $template);
            $this->templateId = $template->id;
            $this->tagIds = $template->tags()->pluck('tags.id')->all();
            $this->name = $template->name;
            $this->storedFilePaths = $template->files ?? [];
            $this->documentWorkflow = $template->document_workflow;
            $this->roles = $template->templateSigners->map(fn ($s) => [
                'role_name' => $s->role_name,
                'role_type' => $s->role_type->value,
            ])->values()->all();
            $this->emailSubject = $template->email_subject;
            $this->emailMessage = $template->email_message;
            $this->signingMethod = $template->signing_method instanceof TemplateSigningMethod
                ? $template->signing_method->value
                : (string) $template->signing_method;
            $this->auditEnabled = $template->audit_enabled;
            $this->auditSettings = array_merge(
                Template::defaultAuditSettings(),
                $template->audit_settings ?? []
            );
        }

        if ($this->roles === []) {
            $this->roles = [
                ['role_name' => 'Client', 'role_type' => TemplateRoleType::Signer->value],
                ['role_name' => 'Legal', 'role_type' => TemplateRoleType::Approver->value],
            ];
        }

        if ($this->auditSettings === []) {
            $this->auditSettings = Template::defaultAuditSettings();
        }
    }

    public function addRole(): void
    {
        $this->roles[] = [
            'role_name' => '',
            'role_type' => TemplateRoleType::Signer->value,
        ];
    }

    public function removeRole(int $index): void
    {
        unset($this->roles[$index]);
        $this->roles = array_values($this->roles);
    }

    public function removeStoredFile(int $index): void
    {
        $path = $this->storedFilePaths[$index] ?? null;
        if ($path === null) {
            return;
        }
        array_splice($this->storedFilePaths, $index, 1);
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    public function removeNewUpload(int $index): void
    {
        unset($this->newUploads[$index]);
        $this->newUploads = array_values($this->newUploads);
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

    public function with(): array
    {
        return [
            'availableTags' => Auth::user()->tags()->orderBy('name')->get(),
        ];
    }

    public function saveTemplate(): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'documentWorkflow' => ['boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*.role_name' => ['required', 'string', 'max:255', 'distinct'],
            'roles.*.role_type' => ['required', 'in:signer,approver,recipient'],
            'emailSubject' => ['nullable', 'string', 'max:255'],
            'emailMessage' => ['nullable', 'string', 'max:5000'],
            'signingMethod' => ['required', 'in:docutrust_sign,email'],
            'auditEnabled' => ['boolean'],
            'auditSettings.show_email' => ['boolean'],
            'auditSettings.show_document_id' => ['boolean'],
            'auditSettings.show_author' => ['boolean'],
            'auditSettings.show_mobile' => ['boolean'],
            'auditSettings.show_id_details' => ['boolean'],
            'newUploads' => ['array'],
            'newUploads.*' => ['file', 'mimes:pdf,docx', 'max:15360'],
            'tagIds' => ['array'],
            'tagIds.*' => ['integer', Rule::exists('tags', 'id')->where('user_id', Auth::id())],
        ];

        $this->validate($rules);

        $hasSigner = collect($this->roles)->contains(
            fn (array $r) => $r['role_type'] === TemplateRoleType::Signer->value
        );

        if (! $hasSigner) {
            $this->addError('roles', __('Add at least one signer role.'));

            return;
        }

        $paths = $this->storedFilePaths;
        foreach ($this->newUploads as $file) {
            $paths[] = $file->store('templates', 'public');
        }
        $this->newUploads = [];

        if ($paths === []) {
            $this->addError('newUploads', __('Add at least one document (PDF or DOCX).'));

            return;
        }

        $payload = [
            'name' => $this->name,
            'files' => $paths,
            'document_workflow' => $this->documentWorkflow,
            'email_subject' => $this->emailSubject,
            'email_message' => $this->emailMessage,
            'signing_method' => $this->signingMethod,
            'audit_enabled' => $this->auditEnabled,
            'audit_settings' => $this->auditSettings,
        ];

        if ($this->templateId !== null) {
            $template = Template::query()->findOrFail($this->templateId);
            $this->authorize('update', $template);
            $template->update($payload);

            $template->templateSigners()->delete();
            foreach ($this->roles as $index => $role) {
                $template->templateSigners()->create([
                    'role_name' => $role['role_name'],
                    'role_type' => TemplateRoleType::from($role['role_type']),
                    'signing_order' => $this->documentWorkflow ? $index : null,
                ]);
            }
            $template->tags()->sync(array_map('intval', $this->tagIds));
        } else {
            $template = Auth::user()->templates()->create($payload);
            $this->templateId = $template->id;

            foreach ($this->roles as $index => $role) {
                $template->templateSigners()->create([
                    'role_name' => $role['role_name'],
                    'role_type' => TemplateRoleType::from($role['role_type']),
                    'signing_order' => $this->documentWorkflow ? $index : null,
                ]);
            }
            $template->tags()->sync(array_map('intval', $this->tagIds));
        }

        $template = Template::query()->findOrFail($this->templateId);
        $this->redirect(route('templates.prepare', $template), navigate: false);
    }
}; ?>

<div
    class="mx-auto w-full max-w-7xl lg:grid lg:grid-cols-12 lg:items-start lg:gap-6"
    x-data="{ advancedOpen: false }"
>
    <div class="min-w-0 lg:col-span-8">
        <x-template-stepper :current="1" />

        <header
            class="mt-4 border-b border-zinc-200/90 pb-4 dark:border-zinc-800"
        >
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50 md:text-3xl">
                {{ $templateId ? __('Edit template') : __('Create template') }}
            </h1>
            <p class="mt-1 text-base text-zinc-500 dark:text-zinc-400">
                {{ __('Configure documents, participants, and delivery. Field placement is the next step.') }}
            </p>
        </header>

        <div class="mt-6 space-y-6 pb-8">

            {{-- Documents --}}
            <section class="ui-panel p-5 sm:p-6">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">{{ __('Documents') }}</h2>
                <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Upload one or more PDF or Word files.') }}</p>

                <div class="mt-4 space-y-4">
                    <flux:field>
                        <flux:label>{{ __('Template name') }}</flux:label>
                        <flux:input wire:model="name" type="text" required placeholder="{{ __('e.g. Employment agreement') }}" />
                        <flux:error name="name" />
                    </flux:field>

                    <div>
                        <flux:label class="mb-2">{{ __('Files') }}</flux:label>
                        <div
                            class="relative rounded-2xl border-2 border-dashed border-zinc-300 bg-zinc-50/80 p-8 text-center transition dark:border-zinc-600 dark:bg-zinc-900/40"
                            x-on:dragover.prevent="$el.classList.add('border-teal-400', 'bg-teal-50/50', 'dark:border-teal-500')"
                            x-on:dragleave.prevent="$el.classList.remove('border-teal-400', 'bg-teal-50/50', 'dark:border-teal-500')"
                            x-on:drop.prevent="
                                $el.classList.remove('border-teal-400', 'bg-teal-50/50', 'dark:border-teal-500');
                                if ($event.dataTransfer.files.length) {
                                    $refs.fileInput.files = $event.dataTransfer.files;
                                    $refs.fileInput.dispatchEvent(new Event('change', { bubbles: true }));
                                }
                            "
                        >
                            <flux:icon.document-text class="mx-auto size-10 text-zinc-400" />
                            <p class="mt-3 text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('Drag & drop files here') }}</p>
                            <p class="mt-1 text-xs text-zinc-500">{{ __('PDF or DOCX — max 15 MB each') }}</p>
                            <label class="mt-4 inline-flex cursor-pointer">
                                <span class="text-sm font-semibold text-teal-600 hover:text-teal-700 dark:text-teal-400">{{ __('Browse files') }}</span>
                                <input
                                    x-ref="fileInput"
                                    type="file"
                                    wire:model="newUploads"
                                    multiple
                                    accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                    class="sr-only"
                                />
                            </label>
                        </div>
                        <flux:error name="newUploads" />
                        <div wire:loading wire:target="newUploads" class="mt-2 text-sm text-zinc-500">{{ __('Uploading…') }}</div>
                    </div>

                    @if ($storedFilePaths !== [] || $newUploads !== [])
                        <ul class="divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                            @foreach ($storedFilePaths as $index => $path)
                                <li class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                                    <span class="min-w-0 truncate font-medium text-zinc-800 dark:text-zinc-100">{{ basename($path) }}</span>
                                    <flux:button size="sm" variant="ghost" type="button" wire:click="removeStoredFile({{ $index }})" wire:key="stored-{{ $index }}">
                                        {{ __('Remove') }}
                                    </flux:button>
                                </li>
                            @endforeach
                            @foreach ($newUploads as $index => $upload)
                                <li class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                                    <span class="min-w-0 truncate font-medium text-zinc-800 dark:text-zinc-100">{{ $upload->getClientOriginalName() }}</span>
                                    <flux:button size="sm" variant="ghost" type="button" wire:click="removeNewUpload({{ $index }})" wire:key="new-{{ $index }}">
                                        {{ __('Remove') }}
                                    </flux:button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>

            {{-- Tags --}}
            <section class="ui-panel p-5 sm:p-6">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">{{ __('Tags') }}</h2>
                <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Organize this template in your library.') }}</p>

                <div class="mt-4 space-y-4">
                    @if ($availableTags->isNotEmpty())
                        <div class="flex flex-wrap gap-3">
                            @foreach ($availableTags as $tag)
                                <label
                                    class="flex cursor-pointer items-center gap-2 rounded-lg border border-zinc-200 px-3 py-2 transition hover:border-teal-400 dark:border-zinc-600 dark:hover:border-teal-500"
                                    wire:key="tag-opt-{{ $tag->id }}"
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
                    @else
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No tags yet. Add one below.') }}</p>
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
            </section>

            {{-- Participants --}}
            <section class="ui-panel p-5 sm:p-6">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">{{ __('Participants') }}</h2>

                <div class="mt-4 flex items-start gap-3 rounded-xl border border-zinc-200/80 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900/30">
                    <flux:checkbox wire:model.live="documentWorkflow" :label="__('Set document workflow')" />
                </div>
                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('When enabled, participants follow the order below (sequential flow).') }}
                </p>

                <div class="mt-4 space-y-4">
                    @foreach ($roles as $index => $role)
                        <div
                            wire:key="role-{{ $index }}"
                            class="rounded-xl border border-zinc-200/90 bg-zinc-50/50 p-4 dark:border-zinc-700 dark:bg-zinc-900/40"
                        >
                            @if ($documentWorkflow)
                                <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-teal-700 dark:text-teal-400">
                                    {{ __('Participant order :n', ['n' => $index + 1]) }}
                                </p>
                            @endif
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                                <flux:field class="min-w-0 flex-1">
                                    <flux:label>{{ __('Role name') }}</flux:label>
                                    <flux:input wire:model="roles.{{ $index }}.role_name" type="text" required placeholder="{{ __('e.g. Client') }}" />
                                    <flux:error name="roles.{{ $index }}.role_name" />
                                </flux:field>
                                <flux:field class="w-full sm:w-48">
                                    <flux:label>{{ __('Role type') }}</flux:label>
                                    <flux:select wire:model="roles.{{ $index }}.role_type">
                                        <flux:select.option value="signer">{{ __('Signer') }}</flux:select.option>
                                        <flux:select.option value="approver">{{ __('Approver') }}</flux:select.option>
                                        <flux:select.option value="recipient">{{ __('Recipient') }}</flux:select.option>
                                    </flux:select>
                                </flux:field>
                                <flux:button variant="ghost" type="button" wire:click="removeRole({{ $index }})" class="shrink-0 self-end sm:self-auto">
                                    <span class="sr-only">{{ __('Delete') }}</span>
                                    <flux:icon.trash class="size-5 text-zinc-500" />
                                </flux:button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4">
                    <flux:button variant="outline" type="button" wire:click="addRole" icon="plus">
                        {{ __('Add role') }}
                    </flux:button>
                </div>

                @error('roles')
                    <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-100">
                        {{ $message }}
                    </div>
                @enderror
            </section>

            {{-- Email --}}
            <section class="ui-panel p-5 sm:p-6">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">{{ __('Email to participants') }}</h2>
                <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Default content when sending this template.') }}</p>
                <div class="mt-4 space-y-4">
                    <flux:field>
                        <flux:label>{{ __('Subject') }}</flux:label>
                        <flux:input wire:model="emailSubject" type="text" placeholder="{{ __('Please review and sign') }}" />
                        <flux:error name="emailSubject" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Message') }}</flux:label>
                        <flux:textarea wire:model="emailMessage" rows="4" placeholder="{{ __('Hello, …') }}" />
                        <flux:error name="emailMessage" />
                    </flux:field>
                </div>
            </section>

            {{-- Signing method --}}
            <section class="ui-panel p-5 sm:p-6">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">{{ __('Signing method') }}</h2>
                <div class="mt-4 space-y-3">
                    <label class="flex cursor-pointer gap-3 rounded-xl border border-zinc-200 p-4 has-[:checked]:border-teal-500 has-[:checked]:bg-teal-50/50 dark:border-zinc-700 dark:has-[:checked]:border-teal-500 dark:has-[:checked]:bg-teal-950/30">
                        <input type="radio" wire:model="signingMethod" value="docutrust_sign" class="mt-1 text-teal-600" />
                        <span>
                            <span class="block font-medium text-zinc-900 dark:text-zinc-50">{{ __('Via DocuTrust Sign') }}</span>
                            <span class="text-sm text-zinc-500">{{ __('Sign using verified email') }}</span>
                        </span>
                    </label>
                    <label class="flex cursor-not-allowed gap-3 rounded-xl border border-zinc-200/80 bg-zinc-50/70 p-4 opacity-80 dark:border-zinc-700 dark:bg-zinc-900/40">
                        <input type="radio" value="email" class="mt-1 text-teal-600" disabled />
                        <span>
                            <span class="block font-medium text-zinc-700 dark:text-zinc-200">{{ __('Via email') }}</span>
                            <span class="text-sm text-zinc-500">{{ __('Coming soon. Disabled for demo stability.') }}</span>
                        </span>
                    </label>
                </div>
            </section>

            {{-- Advanced --}}
            <section class="ui-panel overflow-hidden p-0">
                <button
                    type="button"
                    class="flex w-full items-center justify-between px-6 py-4 text-left text-sm font-semibold text-zinc-900 transition hover:bg-zinc-50 dark:text-zinc-50 dark:hover:bg-zinc-800/50"
                    x-on:click="advancedOpen = ! advancedOpen"
                >
                    {{ __('Advanced settings') }}
                    <flux:icon.chevron-down class="size-5 text-zinc-400 transition" x-bind:class="{ 'rotate-180': advancedOpen }" />
                </button>
                <div x-show="advancedOpen" x-transition class="border-t border-zinc-200 px-6 py-6 dark:border-zinc-700">
                    <div class="flex items-start gap-3">
                        <flux:checkbox wire:model="auditEnabled" :label="__('Audit trail enabled')" />
                    </div>
                    <p class="mt-2 text-xs text-zinc-500">{{ __('Choose what appears on the audit trail.') }}</p>

                    <div class="mt-6 grid gap-3 sm:grid-cols-2">
                        <flux:checkbox wire:model="auditSettings.show_email" :label="__('Show email address')" />
                        <flux:checkbox wire:model="auditSettings.show_document_id" :label="__('Show document ID')" />
                        <flux:checkbox wire:model="auditSettings.show_author" :label="__('Show author')" />
                        <flux:checkbox wire:model="auditSettings.show_mobile" :label="__('Show mobile number')" />
                        <flux:checkbox wire:model="auditSettings.show_id_details" :label="__('Show ID details')" />
                    </div>
                </div>
            </section>

            <div class="flex flex-wrap gap-3">
                <flux:button variant="primary" type="button" wire:click="saveTemplate" wire:loading.attr="disabled">
                    {{ $templateId ? __('Save and continue') : __('Create template') }}
                </flux:button>
                <flux:button variant="ghost" :href="route('templates.index')" wire:navigate type="button">{{ __('Cancel') }}</flux:button>
            </div>
        </div>
    </div>

    {{-- Summary --}}
    <aside class="mt-6 lg:col-span-4 lg:mt-0 lg:max-h-[calc(100dvh-8rem)] lg:overflow-y-auto">
        <div class="lg:sticky lg:top-4 rounded-xl border border-zinc-200/90 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/60">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Summary') }}</h3>
            <dl class="mt-4 space-y-4 text-sm">
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Document name') }}</dt>
                    <dd class="mt-1 font-medium text-zinc-900 dark:text-zinc-50">{{ $name !== '' ? $name : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Participants') }}</dt>
                    <dd class="mt-1 font-semibold text-zinc-900 dark:text-zinc-50">{{ count($roles) }}</dd>
                </div>
                <div class="border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    <dt class="mb-2 text-zinc-500 dark:text-zinc-400">{{ __('Roles') }}</dt>
                    <dd class="space-y-1 text-zinc-800 dark:text-zinc-100">
                        <div class="flex justify-between gap-2">
                            <span>{{ __('Signers') }}</span>
                            <span class="font-semibold">{{ collect($roles)->where('role_type', 'signer')->count() }}</span>
                        </div>
                        <div class="flex justify-between gap-2">
                            <span>{{ __('Approvers') }}</span>
                            <span class="font-semibold">{{ collect($roles)->where('role_type', 'approver')->count() }}</span>
                        </div>
                        <div class="flex justify-between gap-2">
                            <span>{{ __('Recipients') }}</span>
                            <span class="font-semibold">{{ collect($roles)->where('role_type', 'recipient')->count() }}</span>
                        </div>
                    </dd>
                </div>
            </dl>
        </div>
    </aside>
</div>
