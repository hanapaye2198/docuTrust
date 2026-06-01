@php
    $panels = $this->panelVisibility();
    $primaryAction = $this->primaryCaseAction;
    $showRightAside = ($primaryAction && $activeTab !== ($primaryAction['tab'] ?? $activeTab))
        || ($primaryAction && ($primaryAction['type'] ?? '') === 'wire' && $activeTab === 'session')
        || ($paymentRequired && ! $hasSettledPayment && $settlementDueAmount > 0);
    $mainColumnClass = $showRightAside ? 'xl:col-span-7' : 'xl:col-span-9';
@endphp

<x-admin.page class="h-full flex-1" gap="gap-6" wide>
    @if (session('status'))
        <div class="flex items-center gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:text-emerald-200">
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
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ trans_choice(':count document|:count documents', $requestDocuments->count(), ['count' => $requestDocuments->count()]) }}
                ·
                {{ trans_choice(':count party|:count parties', $requestSigners->count(), ['count' => $requestSigners->count()]) }}
                @if ($notaryRequest->submitted_at)
                    · {{ __('Submitted :date', ['date' => $notaryRequest->submitted_at->diffForHumans()]) }}
                @endif
            </p>

            @if ($isNotary && is_array($signingProgress) && ($signingProgress['visible'] ?? false) && $activeTab !== 'documents')
                <div class="max-w-2xl space-y-2 pt-1">
                    <p class="text-sm font-medium text-sky-800 dark:text-sky-200">{{ $signingProgress['summary'] }}</p>
                    <div class="flex items-center gap-3">
                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-sky-100 dark:bg-sky-950/50">
                            <div class="h-full rounded-full bg-sky-500 transition-all duration-500" style="width: {{ max(0, min(100, (int) ($signingProgress['percent'] ?? 0))) }}%"></div>
                        </div>
                        <span class="shrink-0 text-xs font-semibold tabular-nums text-sky-700 dark:text-sky-300">
                            {{ (int) ($signingProgress['completed'] ?? 0) }}/{{ (int) ($signingProgress['total'] ?? 0) }}
                        </span>
                    </div>
                </div>
            @endif
        </div>

        <div class="flex w-full shrink-0 flex-col items-stretch gap-2 sm:flex-row sm:flex-wrap sm:items-center lg:max-w-md lg:justify-end">
            @if ($primaryAction)
                @if ($primaryAction['type'] === 'link')
                    <flux:button
                        :variant="$primaryAction['variant']"
                        :href="$primaryAction['href']"
                        wire:navigate
                        class="w-full sm:w-auto"
                    >
                        {{ $primaryAction['label'] }}
                    </flux:button>
                @elseif ($primaryAction['type'] === 'wire')
                    @php
                        $wireAction = $primaryAction['action'];
                        if (! empty($primaryAction['params'])) {
                            $wireAction .= '('.collect($primaryAction['params'])->map(fn ($p) => is_numeric($p) ? $p : "'{$p}'")->implode(',').')';
                        }
                    @endphp
                    @if (! empty($primaryAction['confirm']))
                        <flux:button
                            :variant="$primaryAction['variant']"
                            type="button"
                            wire:click="{{ $wireAction }}"
                            wire:confirm="{{ $primaryAction['confirm'] }}"
                            class="w-full sm:w-auto"
                        >
                            {{ $primaryAction['label'] }}
                        </flux:button>
                    @else
                        <flux:button
                            :variant="$primaryAction['variant']"
                            type="button"
                            wire:click="{{ $wireAction }}"
                            class="w-full sm:w-auto"
                        >
                            {{ $primaryAction['label'] }}
                        </flux:button>
                    @endif
                @elseif ($primaryAction['type'] === 'tab')
                    <flux:button
                        :variant="$primaryAction['variant']"
                        type="button"
                        wire:click="setActiveTab('{{ $primaryAction['tab'] }}')"
                        class="w-full sm:w-auto"
                    >
                        {{ $primaryAction['label'] }}
                    </flux:button>
                @endif
            @endif

            @if (! in_array($notaryRequest->status->value, ['digitalized', 'notarized', 'rejected', 'failed', 'cancelled'], true))
                <flux:dropdown class="self-end sm:self-auto">
                    <flux:button variant="ghost" icon="ellipsis-horizontal" />
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

    @if ($paymentRequired && ! $hasSettledPayment)
        <flux:callout variant="warning" icon="banknotes">
            <flux:callout.heading>{{ __('Payment required') }}</flux:callout.heading>
            <flux:callout.text>
                @if ($isNotary)
                    {{ __('A notarial fee is outstanding. Create or share the payment link so the client can pay before you finalize the register entry.') }}
                @else
                    {{ __('A notarial fee is due. Open payment below to complete checkout.') }}
                @endif
            </flux:callout.text>
            <div class="mt-2">
                <button
                    type="button"
                    wire:click="setActiveTab('closing')"
                    class="inline-flex items-center justify-center rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
                >
                    {{ __('Open payment') }}
                </button>
            </div>
        </flux:callout>
    @endif

    <div class="grid items-start gap-6 xl:grid-cols-12 xl:gap-8" wire:key="case-workspace-{{ $notaryRequest->id }}">
        <aside class="order-first xl:col-span-3 xl:sticky xl:top-4 xl:self-start">
            @include('livewire.notary-requests.show.partials.case-workflow-sidebar')
        </aside>

        <div @class(['flex min-w-0 flex-col gap-4', $mainColumnClass])>
            <nav class="flex flex-wrap gap-2 border-b border-zinc-200/90 pb-1 dark:border-zinc-800" aria-label="{{ __('Case workspace tabs') }}">
                <button
                    type="button"
                    wire:click="setActiveTab('documents')"
                    @class([
                        'rounded-lg px-4 py-2 text-sm font-semibold transition',
                        'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' => $activeTab === 'documents',
                        'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' => $activeTab !== 'documents',
                    ])
                >
                    {{ __('Documents') }}
                </button>
                <button
                    type="button"
                    wire:click="setActiveTab('parties')"
                    @class([
                        'rounded-lg px-4 py-2 text-sm font-semibold transition',
                        'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' => $activeTab === 'parties',
                        'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' => $activeTab !== 'parties',
                    ])
                >
                    {{ __('Parties') }}
                    @if ($requestSigners->isNotEmpty())
                        <span class="ml-1 text-xs opacity-70">{{ $requestSigners->count() }}</span>
                    @endif
                </button>
                @if ($panels['session'])
                    <button
                        type="button"
                        wire:click="setActiveTab('session')"
                        @class([
                            'rounded-lg px-4 py-2 text-sm font-semibold transition',
                            'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' => $activeTab === 'session',
                            'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' => $activeTab !== 'session',
                        ])
                    >
                        {{ __('Video session') }}
                    </button>
                @endif
                @if ($panels['closing'])
                    <button
                        type="button"
                        wire:click="setActiveTab('closing')"
                        @class([
                            'rounded-lg px-4 py-2 text-sm font-semibold transition',
                            'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' => $activeTab === 'closing',
                            'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' => $activeTab !== 'closing',
                        ])
                    >
                        {{ __('Settlement') }}
                        @if ($settlementPendingCount > 0)
                            <span class="ml-1 inline-flex min-w-5 items-center justify-center rounded-full bg-sky-500 px-1.5 py-0.5 text-[10px] font-bold leading-none text-white">
                                {{ $settlementPendingCount }}
                            </span>
                        @endif
                    </button>
                @endif
                @if ($panels['audit'])
                    <button
                        type="button"
                        wire:click="setActiveTab('audit')"
                        @class([
                            'rounded-lg px-4 py-2 text-sm font-semibold transition',
                            'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' => $activeTab === 'audit',
                            'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' => $activeTab !== 'audit',
                        ])
                    >
                        {{ __('Audit') }}
                    </button>
                @endif
            </nav>

            <div>
                @if ($activeTab === 'documents')
                <div wire:key="case-tab-documents">
                    @if ($notaryRequest->status === \App\Enums\NotaryRequestStatus::Notarized)
                        @include('livewire.notary-requests.show.partials.section-completed')
                    @endif
                    @include('livewire.notary-requests.show.partials.tab-documents')
                </div>
                @endif

                @if ($activeTab === 'parties')
                <div wire:key="case-tab-parties">
                    @include('livewire.notary-requests.show.partials.tab-parties')
                </div>
                @endif

                @if ($panels['session'] && $activeTab === 'session')
                    <div wire:key="case-tab-session">
                        @include('livewire.notary-requests.show.partials.tab-session')
                    </div>
                @endif

                @if ($panels['closing'] && $activeTab === 'closing')
                    <div
                        wire:key="case-tab-closing"
                        x-data
                        x-init="$nextTick(() => {
                            const scrollArea = document.querySelector('.main-scroll-area');
                            if (scrollArea) {
                                scrollArea.scrollTop = 0;
                            }
                        })"
                    >
                        @include('livewire.notary-requests.show.partials.tab-closing')
                    </div>
                @endif

                @if ($panels['audit'] && $activeTab === 'audit')
                    <div wire:key="case-tab-audit">
                        @include('livewire.notary-requests.show.partials.tab-audit')
                    </div>
                @endif
            </div>
        </div>

        @if ($showRightAside)
        <aside class="flex flex-col gap-4 xl:col-span-2 xl:sticky xl:top-4 xl:max-h-[calc(100vh-6rem)] xl:overflow-y-auto">
            @if ($primaryAction && $activeTab !== ($primaryAction['tab'] ?? $activeTab))
                <div class="ui-panel border-sky-200/80 bg-sky-50/50 p-5 dark:border-sky-900/40 dark:bg-sky-950/20">
                    <div class="text-xs font-semibold uppercase tracking-wider text-sky-700 dark:text-sky-300">{{ __('Do this now') }}</div>
                    <p class="mt-2 text-sm font-semibold text-sky-950 dark:text-sky-100">{{ $primaryAction['label'] }}</p>
                    <p class="mt-1 text-xs leading-relaxed text-sky-800 dark:text-sky-200">{{ $primaryAction['description'] }}</p>
                    @if ($primaryAction['type'] === 'wire')
                        @php
                            $sidebarWireAction = $primaryAction['action'];
                            if (! empty($primaryAction['params'])) {
                                $sidebarWireAction .= '('.collect($primaryAction['params'])->map(fn ($p) => is_numeric($p) ? $p : "'{$p}'")->implode(',').')';
                            }
                        @endphp
                        <flux:button class="mt-3 w-full" variant="primary" size="sm" type="button" wire:click="{{ $sidebarWireAction }}">
                            {{ $primaryAction['label'] }}
                        </flux:button>
                    @elseif (($primaryAction['type'] ?? '') === 'tab')
                        <flux:button class="mt-3 w-full" variant="primary" size="sm" type="button" wire:click="setActiveTab('{{ $primaryAction['tab'] }}')">
                            {{ $primaryAction['label'] }}
                        </flux:button>
                    @endif
                </div>
            @elseif ($primaryAction && ($primaryAction['type'] ?? '') === 'wire' && $activeTab === 'session')
                <div class="ui-panel border-sky-200/80 bg-sky-50/50 p-5 dark:border-sky-900/40 dark:bg-sky-950/20">
                    <div class="text-xs font-semibold uppercase tracking-wider text-sky-700 dark:text-sky-300">{{ __('Do this now') }}</div>
                    <p class="mt-1 text-xs leading-relaxed text-sky-800 dark:text-sky-200">{{ $primaryAction['description'] }}</p>
                </div>
            @endif

            @if ($paymentRequired && ! $hasSettledPayment && $settlementDueAmount > 0)
                <div class="ui-panel p-5">
                    <flux:heading size="sm" class="mb-2">{{ __('Payment due') }}</flux:heading>
                    <p class="text-lg font-bold text-zinc-900 dark:text-zinc-100">PHP {{ number_format((float) $settlementDueAmount, 2) }}</p>
                    <button
                        type="button"
                        wire:click="setActiveTab('closing')"
                        class="mt-3 inline-flex w-full items-center justify-center rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
                    >
                        {{ __('View payment') }}
                    </button>
                </div>
            @endif
        </aside>
        @endif
    </div>

    @include('livewire.notary-requests.show.partials.notary-status-poll-config')
    @include('livewire.notary-requests.show.partials.settlement-scroll')
</x-admin.page>
