<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {}; ?>

<div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
    <header>
        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50 sm:text-3xl">{{ __('Notary workspace') }}</h1>
        <p class="mt-2 text-zinc-600 dark:text-zinc-400">
            {{ __('Welcome to your DocuTrust notary dashboard. Use the tools your organization assigns to you from here.') }}
        </p>
    </header>

    <div class="ui-panel p-6 sm:p-8">
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('This area is reserved for notary-specific workflows. Connect document queues, verification tasks, or integrations as your team rolls them out.') }}
        </p>
    </div>
</div>
