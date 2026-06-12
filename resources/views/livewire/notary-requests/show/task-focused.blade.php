@php
    $panels = $this->panelVisibility();
    $primaryAction = $this->primaryCaseAction;
@endphp

<x-admin.page class="h-full flex-1 pb-24 xl:pb-0" gap="gap-6" wide>
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

    @if ($errors->has('submitRequest') || $errors->has('digitalizeRequest') || $errors->has('finalizeRequest') || $errors->has('cancelNotaryRequest'))
        <div class="space-y-1">
            <flux:error name="submitRequest" />
            <flux:error name="digitalizeRequest" />
            <flux:error name="finalizeRequest" />
            <flux:error name="cancelNotaryRequest" />
        </div>
    @endif

    @include('livewire.notary-requests.show.partials.do-this-now-card', ['primaryAction' => $primaryAction])

    <div class="grid items-start gap-6 xl:grid-cols-12 xl:gap-8" wire:key="case-workspace-{{ $notaryRequest->id }}">
        <aside class="hidden xl:col-span-3 xl:block xl:sticky xl:top-4 xl:self-start">
            @include('livewire.notary-requests.show.partials.case-workflow-sidebar')
        </aside>

        <div class="flex min-w-0 flex-col gap-4 xl:col-span-9">
            <nav
                class="-mx-1 flex gap-2 overflow-x-auto border-b border-zinc-200/90 px-1 pb-2 dark:border-zinc-800"
                aria-label="{{ __('Case sections') }}"
            >
                <button
                    type="button"
                    wire:click="setActiveTab('documents')"
                    @class([
                        'inline-flex shrink-0 items-center rounded-lg px-4 py-2.5 text-sm font-semibold transition min-h-11',
                        'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' => $activeTab === 'documents',
                        'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' => $activeTab !== 'documents',
                    ])
                >
                    {{ __('Document') }}
                </button>
                <button
                    type="button"
                    wire:click="setActiveTab('parties')"
                    @class([
                        'inline-flex shrink-0 items-center rounded-lg px-4 py-2.5 text-sm font-semibold transition min-h-11',
                        'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' => $activeTab === 'parties',
                        'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' => $activeTab !== 'parties',
                    ])
                >
                    {{ __('Signers') }}
                    @if ($requestSigners->isNotEmpty())
                        <span class="ml-1 text-xs opacity-70">{{ $requestSigners->count() }}</span>
                    @endif
                </button>
                @if ($panels['session'])
                    <button
                        type="button"
                        wire:click="setActiveTab('session')"
                        @class([
                            'inline-flex shrink-0 items-center rounded-lg px-4 py-2.5 text-sm font-semibold transition min-h-11',
                            'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' => $activeTab === 'session',
                            'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' => $activeTab !== 'session',
                        ])
                    >
                        {{ __('Verify on video') }}
                    </button>
                @endif
                @if ($panels['closing'])
                    <button
                        type="button"
                        wire:click="setActiveTab('closing')"
                        @class([
                            'inline-flex shrink-0 items-center rounded-lg px-4 py-2.5 text-sm font-semibold transition min-h-11',
                            'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' => $activeTab === 'closing',
                            'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' => $activeTab !== 'closing',
                        ])
                    >
                        {{ __('Fees & register') }}
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
                            'inline-flex shrink-0 items-center rounded-lg px-4 py-2.5 text-sm font-semibold transition min-h-11',
                            'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' => $activeTab === 'audit',
                            'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' => $activeTab !== 'audit',
                        ])
                    >
                        {{ __('Case history') }}
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
    </div>

    @include('livewire.notary-requests.show.partials.mobile-action-bar', ['primaryAction' => $primaryAction])
    @include('livewire.notary-requests.show.partials.notary-status-poll-config')
    @include('livewire.notary-requests.show.partials.settlement-scroll')
</x-admin.page>
