@php
    $panels = $this->panelVisibility();
    $primaryAction = $this->primaryCaseAction;
@endphp

<x-admin.page class="h-full flex-1 pb-24 xl:pb-0" gap="gap-6" wide wire:key="notary-case-page-{{ $notaryRequest->id }}">
    @if (session('status'))
        <div class="flex items-center gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-base font-medium text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:text-emerald-200">
            <flux:icon.check class="size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
            {{ session('status') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-col gap-4 border-b border-zinc-200/90 pb-5 dark:border-zinc-800 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0 space-y-2">
            <flux:button
                variant="ghost"
                size="sm"
                :href="route('notary.requests.index')"
                wire:navigate
                icon="arrow-left"
            >
                {{ __('Notarizations') }}
            </flux:button>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-3xl">{{ $notaryRequest->title }}</h1>
            <div class="flex flex-wrap items-center gap-2">
                @include('livewire.notary-requests.show.partials.status-badge')
                <flux:badge size="sm" color="zinc">{{ str_replace('_', ' ', $notaryRequest->request_type) }}</flux:badge>
            </div>
            <p class="text-base text-zinc-600 dark:text-zinc-400">
                {{ trans_choice(':count document|:count documents', $requestDocuments->count(), ['count' => $requestDocuments->count()]) }}
                ·
                {{ trans_choice(':count signer|:count signers', $requestSigners->count(), ['count' => $requestSigners->count()]) }}
                @if ($notaryRequest->submitted_at)
                    · {{ __('Submitted :date', ['date' => $notaryRequest->submitted_at->diffForHumans()]) }}
                @endif
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($isNotary)
                <flux:button variant="primary" :href="route('notary.requests.workflow', $notaryRequest)" wire:navigate icon="arrow-path-rounded-square">
                    {{ __('Workflow') }}
                </flux:button>
            @endif

            @if (! in_array($notaryRequest->status->value, ['digitalized', 'notarized', 'rejected', 'failed', 'cancelled'], true))
                <flux:dropdown class="self-start">
                    <flux:button variant="ghost" icon="ellipsis-horizontal">{{ __('More actions') }}</flux:button>
                    <flux:menu>
                        @if ($canManageLifecycle && $notaryRequest->status === \App\Enums\NotaryRequestStatus::Draft)
                            <flux:menu.item wire:click="submitRequest">{{ __('Submit notarization') }}</flux:menu.item>
                        @endif
                        <flux:menu.item wire:click="cancelNotaryRequest" wire:confirm="{{ __('Cancel this notarization? This cannot be undone.') }}">
                            {{ __('Cancel case') }}
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            @endif
        </div>
    </div>

    @if ($errors->has('submitRequest') || $errors->has('digitalizeRequest') || $errors->has('finalizeRequest') || $errors->has('cancelNotaryRequest'))
        <div class="space-y-1">
            <flux:error name="submitRequest" />
            <flux:error name="digitalizeRequest" />
            <flux:error name="finalizeRequest" />
            <flux:error name="cancelNotaryRequest" />
        </div>
    @endif

    @include('livewire.notary-requests.show.partials.workflow-wizard')

    <div class="flex w-full min-w-0 max-w-none flex-col gap-5" wire:key="case-workspace-{{ $notaryRequest->id }}-{{ $page }}">
        <div>
            @if ($page === 'document')
                @include('livewire.notary-requests.show.pages.document')
            @elseif ($page === 'signers')
                @include('livewire.notary-requests.show.pages.signers')
            @elseif ($page === 'fees')
                @include('livewire.notary-requests.show.pages.fees')
            @elseif ($page === 'history')
                @include('livewire.notary-requests.show.pages.history')
            @endif
        </div>
    </div>

    @include('livewire.notary-requests.show.partials.mobile-action-bar', ['primaryAction' => $primaryAction])
    @include('livewire.notary-requests.show.partials.notary-status-poll-config')
    @include('livewire.notary-requests.show.partials.settlement-scroll')
</x-admin.page>
