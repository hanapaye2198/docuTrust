@php
    $panels = $this->panelVisibility();
    $primaryAction = $this->primaryCaseAction;
@endphp

<x-admin.page class="h-full flex-1" gap="gap-6">
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
                {{ __('Requests') }}
            </flux:button>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-3xl">{{ $notaryRequest->title }}</h1>
            <div class="flex flex-wrap items-center gap-2">
                @php
                    $statusFluxColor = match ($notaryRequest->status->value) {
                        'notarized' => 'emerald',
                        'rejected', 'failed' => 'red',
                        'submitted', 'session_scheduled', 'session_in_progress' => 'sky',
                        'identity_verified', 'location_verified', 'attorney_approved', 'digitalized' => 'teal',
                        default => 'zinc',
                    };
                @endphp
                <flux:badge size="sm" :color="$statusFluxColor" class="capitalize" data-notary-status-badge="{{ $notaryRequest->status->value }}">{{ str_replace('_', ' ', $notaryRequest->status->value) }}</flux:badge>
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
        </div>

        <div class="flex w-full shrink-0 flex-wrap items-center gap-2 lg:max-w-md lg:justify-end">
            @if ($primaryAction)
                @if ($primaryAction['type'] === 'link')
                    <flux:button
                        :variant="$primaryAction['variant']"
                        :href="$primaryAction['href']"
                        wire:navigate
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
                        >
                            {{ $primaryAction['label'] }}
                        </flux:button>
                    @else
                        <flux:button
                            :variant="$primaryAction['variant']"
                            type="button"
                            wire:click="{{ $wireAction }}"
                        >
                            {{ $primaryAction['label'] }}
                        </flux:button>
                    @endif
                @elseif ($primaryAction['type'] === 'tab')
                    <flux:button
                        :variant="$primaryAction['variant']"
                        type="button"
                        wire:click="setActiveTab('{{ $primaryAction['tab'] }}')"
                    >
                        {{ $primaryAction['label'] }}
                    </flux:button>
                @endif
            @endif

            @if (! in_array($notaryRequest->status->value, ['digitalized', 'notarized', 'rejected', 'failed', 'cancelled'], true))
                <flux:dropdown>
                    <flux:button variant="ghost" icon="ellipsis-horizontal" />
                    <flux:menu>
                        @if ($canManageLifecycle && $notaryRequest->status === \App\Enums\NotaryRequestStatus::Draft)
                            <flux:menu.item wire:click="submitRequest">{{ __('Submit request') }}</flux:menu.item>
                        @endif
                        <flux:menu.item wire:click="cancelNotaryRequest" wire:confirm="{{ __('Cancel this notary request? This cannot be undone.') }}">
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
                {{ __('The register entry is recorded. Client payment must be completed before review and digital notarization can finish.') }}
            </flux:callout.text>
            <div class="mt-2">
                <flux:button size="sm" variant="outline" type="button" wire:click="setActiveTab('closing')">{{ __('Open payment') }}</flux:button>
            </div>
        </flux:callout>
    @endif

    <div class="grid items-start gap-6 lg:grid-cols-12">
        <div class="flex min-w-0 flex-col gap-4 lg:col-span-8 xl:col-span-9">
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
                        {{ __('Closing') }}
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
                <div @class(['hidden' => $activeTab !== 'documents'])>
                    @if ($notaryRequest->status === \App\Enums\NotaryRequestStatus::Notarized)
                        @include('livewire.notary-requests.show.partials.section-completed')
                    @endif
                    @include('livewire.notary-requests.show.partials.tab-documents')
                </div>

                <div @class(['hidden' => $activeTab !== 'parties'])>
                    @include('livewire.notary-requests.show.partials.tab-parties')
                </div>

                @if ($panels['session'])
                    <div @class(['hidden' => $activeTab !== 'session'])>
                        @include('livewire.notary-requests.show.partials.tab-session')
                    </div>
                @endif

                @if ($panels['closing'])
                    <div @class(['hidden' => $activeTab !== 'closing'])>
                        @include('livewire.notary-requests.show.partials.tab-closing')
                    </div>
                @endif

                @if ($panels['audit'])
                    <div @class(['hidden' => $activeTab !== 'audit'])>
                        @include('livewire.notary-requests.show.partials.tab-audit')
                    </div>
                @endif
            </div>
        </div>

        <aside class="flex flex-col gap-4 lg:col-span-4 xl:col-span-3 lg:sticky lg:top-4 lg:max-h-[calc(100vh-6rem)] lg:overflow-y-auto">
            @if ($primaryAction)
                <div class="ui-panel border-sky-200/80 bg-sky-50/50 p-5 dark:border-sky-900/40 dark:bg-sky-950/20">
                    <div class="text-xs font-semibold uppercase tracking-wider text-sky-700 dark:text-sky-300">{{ __('Do this now') }}</div>
                    <p class="mt-2 text-sm font-semibold text-sky-950 dark:text-sky-100">{{ $primaryAction['label'] }}</p>
                    <p class="mt-1 text-xs leading-relaxed text-sky-800 dark:text-sky-200">{{ $primaryAction['description'] }}</p>
                </div>
            @elseif ($this->currentWorkflowStep)
                <div class="ui-panel border-zinc-200/80 p-5 dark:border-zinc-700">
                    <div class="text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Status') }}</div>
                    <p class="mt-2 text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $this->currentWorkflowStep['label'] }}</p>
                    <p class="mt-1 text-xs text-zinc-500">{{ $this->currentWorkflowStep['description'] }}</p>
                </div>
            @endif

            <div class="ui-panel p-5">
                <flux:heading size="lg" class="mb-3">{{ __('Workflow') }}</flux:heading>
                <ol class="space-y-2">
                    @foreach ($workflowSteps as $step)
                        <li class="flex items-center gap-2 text-sm">
                            <span @class([
                                'size-2 shrink-0 rounded-full',
                                'bg-emerald-500' => ($step['state'] ?? '') === 'complete',
                                'bg-sky-500 ring-2 ring-sky-200 dark:ring-sky-900' => ($step['state'] ?? '') === 'current',
                                'bg-zinc-300 dark:bg-zinc-600' => ($step['state'] ?? '') === 'upcoming',
                            ])></span>
                            <span @class([
                                'font-medium text-zinc-900 dark:text-zinc-100' => ($step['state'] ?? '') === 'current',
                                'text-zinc-500 dark:text-zinc-400' => ($step['state'] ?? '') !== 'current',
                            ])>{{ $step['label'] }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>

            @if ($paymentRequired && ! $hasSettledPayment)
                @php $sidebarRegisterEntry = $notaryRequest->registerEntries->sortByDesc('created_at')->first(); @endphp
                @if ($sidebarRegisterEntry)
                    <div class="ui-panel p-5">
                        <flux:heading size="sm" class="mb-2">{{ __('Payment due') }}</flux:heading>
                        <p class="text-lg font-bold text-zinc-900 dark:text-zinc-100">PHP {{ number_format((float) $sidebarRegisterEntry->fees, 2) }}</p>
                        <flux:button class="mt-3 w-full" size="sm" variant="outline" type="button" wire:click="setActiveTab('closing')">{{ __('View payment') }}</flux:button>
                    </div>
                @endif
            @endif
        </aside>
    </div>

    @include('livewire.notary-requests.show.partials.notary-status-poll-config')
</x-admin.page>
