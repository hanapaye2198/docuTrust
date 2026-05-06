<?php

use App\Enums\TemplateRoleType;
use App\Models\Template;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Template $template;

    public function mount(Template $template): void
    {
        $this->authorize('view', $template);
        $this->template = $template->load([
            'templateSigners' => fn ($q) => $q->where('role_type', TemplateRoleType::Signer)->orderBy('signing_order'),
        ]);
    }
}; ?>

<div class="mx-auto flex w-full max-w-xl flex-col gap-0">
    <header class="border-b border-zinc-200/90 pb-4 dark:border-zinc-800">
        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50 md:text-3xl">{{ __('Assign people') }}</h1>
        <p class="mt-1 text-base text-zinc-500 dark:text-zinc-400">{{ __('Map each role to a real person. We will create a new document from this template.') }}</p>
    </header>

    <div class="ui-panel mt-6 p-5 sm:p-6">
        <form method="POST" action="{{ route('templates.documents.store', $template) }}" class="flex flex-col gap-5">
            @csrf

            <flux:field>
                <flux:label>{{ __('Document title') }}</flux:label>
                <flux:input name="document_title" type="text" required value="{{ old('document_title', $template->name) }}" placeholder="{{ __('e.g. Acme Corp — Employment agreement') }}" />
                <flux:error name="document_title" />
            </flux:field>

            <div class="space-y-4 rounded-xl border border-zinc-200/90 bg-zinc-50/80 p-4 dark:border-zinc-700 dark:bg-zinc-900/50">
                <div>
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">{{ __('Document password') }}</h2>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Optional shared password for all signers on this document.') }}</p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Password') }}</flux:label>
                        <flux:input
                            name="access_password"
                            type="password"
                            autocomplete="new-password"
                            placeholder="{{ __('Leave blank for no password') }}"
                        />
                        <flux:error name="access_password" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Confirm password') }}</flux:label>
                        <flux:input
                            name="access_password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            placeholder="{{ __('Repeat the password') }}"
                        />
                        <flux:error name="access_password_confirmation" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>{{ __('Password hint') }}</flux:label>
                    <flux:input
                        name="access_password_hint"
                        type="text"
                        value="{{ old('access_password_hint') }}"
                        placeholder="{{ __('Optional hint for recipients') }}"
                    />
                    <flux:error name="access_password_hint" />
                </flux:field>
            </div>

            <div class="space-y-4">
                <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Signers') }}</h2>

                @foreach ($template->templateSigners as $signer)
                    <div class="rounded-lg border border-zinc-200/90 p-3.5 dark:border-zinc-700">
                        <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">{{ $signer->role_name }}</p>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>{{ __('Name') }}</flux:label>
                                <flux:input
                                    name="assignees[{{ $signer->role_name }}][name]"
                                    type="text"
                                    required
                                    value="{{ old('assignees.'.$signer->role_name.'.name') }}"
                                    autocomplete="name"
                                />
                                <flux:error name="assignees.{{ $signer->role_name }}.name" />
                            </flux:field>
                            <flux:field>
                                <flux:label>{{ __('Email') }}</flux:label>
                                <flux:input
                                    name="assignees[{{ $signer->role_name }}][email]"
                                    type="email"
                                    required
                                    value="{{ old('assignees.'.$signer->role_name.'.email') }}"
                                    autocomplete="email"
                                />
                                <flux:error name="assignees.{{ $signer->role_name }}.email" />
                            </flux:field>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($errors->has('assignees'))
                <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-100">
                    {{ $errors->first('assignees') }}
                </div>
            @endif

            <div class="flex flex-wrap gap-3 pt-1">
                <flux:button variant="primary" type="submit">{{ __('Create document') }}</flux:button>
                <flux:button variant="ghost" :href="route('templates.index')" wire:navigate type="button">{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    </div>
</div>
