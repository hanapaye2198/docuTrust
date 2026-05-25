@php
    use App\Enums\EInvoiceStatus;
    use App\Enums\NotaryRequestStatus;
    use App\Enums\PaymentStatus;
    use App\Models\Payment;
@endphp

<x-admin.page class="h-full flex-1" gap="gap-6" wide>
    @if (session('status'))
        <div class="flex items-center gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:text-emerald-200">
            <svg class="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
            {{ session('status') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-col gap-4 border-b border-zinc-200/90 pb-5 dark:border-zinc-800 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0 space-y-3">
            <div class="flex flex-wrap items-center gap-2">
                <flux:button
                    variant="ghost"
                    size="sm"
                    :href="Auth::user()?->role->value === 'notary' ? route('notary.requests.index') : (Auth::user()?->isEnotaryPortalSigner() ? route('settings.trust-profile') : route('notary-requests.index'))"
                    wire:navigate
                    icon="arrow-left"
                >
                    {{ __('Requests') }}
                </flux:button>
            </div>
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
            <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-zinc-500 dark:text-zinc-400">
                <span>{{ __('Requester') }}: <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $notaryRequest->requester?->name ?? '-' }}</span></span>
                <span>{{ __('Attorney') }}: <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $notaryRequest->notary?->name ?? __('Unassigned') }}</span></span>
                <span>{{ __('Documents') }}: <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $requestDocuments->count() }}</span></span>
                <span>{{ __('Parties') }}: <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $requestSigners->count() }}</span></span>
            </div>
            @if ($this->currentWorkflowStep)
                <p class="text-sm text-sky-700 dark:text-sky-300">
                    <span class="font-semibold">{{ __('Current step:') }}</span>
                    {{ $this->currentWorkflowStep['label'] }}
                    — {{ $this->currentWorkflowStep['description'] }}
                </p>
            @endif
        </div>
        <div class="flex w-full shrink-0 flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center lg:max-w-xl lg:justify-end">
            @if ($canManageLifecycle && $notaryRequest->status === NotaryRequestStatus::Draft)
                <flux:button variant="primary" wire:click="submitRequest">{{ __('Submit request') }}</flux:button>
            @endif
            @if ($canManageLifecycle && $notaryRequest->status === NotaryRequestStatus::Digitalized)
                @if (Auth::user()?->role->value === 'notary_admin')
                    <flux:button variant="primary" wire:click="finalizeRequest" :disabled="! $finalizationReadiness['ready']">{{ __('Finalize notarization') }}</flux:button>
                @endif
            @endif
            @if ($isNotary)
                @php
                    $firstDraftDocument = $requestDocuments->first(fn ($doc) => $doc->status->value === 'draft');
                    $attorneyHasSignedDoc = $requestDocuments->every(fn ($doc) =>
                        $doc->documentSigners->contains(fn ($s) => (int) $s->user_id === (int) auth()->id() && $s->status->value === 'signed')
                    );
                    $hasRegisterEntry = $notaryRequest->registerEntries->isNotEmpty();
                @endphp
                @if ($firstDraftDocument)
                    <flux:button variant="outline" :href="route('notary.documents.prepare', $firstDraftDocument)" wire:navigate>{{ __('Prepare signature fields') }}</flux:button>
                @endif
                @if ($canAttorneySign)
                    @php
                        $unsignedDoc = $requestDocuments->first(fn ($doc) => ! $doc->documentSigners->contains(fn ($s) => (int) $s->user_id === (int) auth()->id() && $s->status->value === 'signed'));
                    @endphp
                    @if ($unsignedDoc)
                        <flux:button variant="primary" wire:click="signAsAttorney({{ $unsignedDoc->id }})">{{ __('Sign as Attorney') }}</flux:button>
                    @endif
                @endif
                {{-- Create Register Entry: Only after attorney signs, before digital seal --}}
                @if ($attorneyHasSignedDoc && !$hasRegisterEntry)
                    <flux:button variant="primary" :href="route('notary.register-entry', $notaryRequest)" wire:navigate>{{ __('Create Register Entry') }}</flux:button>
                @endif
                {{-- Apply Digital Notarization: After attorney signs + register entry + client payment --}}
                @if ($canDigitalizeRequest)
                    <flux:button variant="primary" wire:click="digitalizeRequest">{{ __('Apply Digital Notarization') }}</flux:button>
                @endif
            @endif
            @if ($canManageLifecycle && ! in_array($notaryRequest->status->value, ['digitalized', 'notarized', 'rejected', 'failed', 'cancelled'], true))
                <flux:button variant="outline" wire:click="cancelNotaryRequest" wire:confirm="{{ __('Cancel this notary request? This cannot be undone.') }}">{{ __('Cancel request') }}</flux:button>
            @endif
            {{-- Client/Admin actions when notarized --}}
            @if ($notaryRequest->status === NotaryRequestStatus::Notarized && !$isNotary)
                @php
                    $firstNotarizedDoc = $requestDocuments->first();
                @endphp
                @if ($firstNotarizedDoc)
                    <flux:button variant="primary" icon="arrow-down-tray" :href="route('documents.download', $firstNotarizedDoc)">{{ __('Download') }}</flux:button>
                @endif
                @if ($notaryRequest->registerEntries->first()?->qr_verification_token)
                    <flux:button variant="outline" icon="shield-check" :href="route('notary.verify', ['token' => $notaryRequest->registerEntries->first()->qr_verification_token])" target="_blank">{{ __('Verify') }}</flux:button>
                @endif
                @if ($firstNotarizedDoc)
                    <flux:button variant="outline" icon="archive-box" :href="route('documents.certificate.show', $firstNotarizedDoc)" wire:navigate>{{ __('Certificate') }}</flux:button>
                @endif
            @endif
        </div>
        @if ($errors->has('submitRequest') || $errors->has('digitalizeRequest') || $errors->has('finalizeRequest') || $errors->has('cancelNotaryRequest'))
            <div class="w-full space-y-1">
                <flux:error name="submitRequest" />
                <flux:error name="digitalizeRequest" />
                <flux:error name="finalizeRequest" />
                <flux:error name="cancelNotaryRequest" />
            </div>
        @endif
    </div>

    <div class="grid items-start gap-6 lg:grid-cols-12">
        <div class="flex min-w-0 flex-col gap-6 lg:col-span-9 xl:col-span-10">
            @if ($paymentRequired && ! $hasSettledPayment)
                <div id="section-payment-alert" class="order-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900 shadow-sm dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-100">
                    <div class="font-semibold">{{ __('Payment required before notarization can continue') }}</div>
                    <div class="mt-1">
                        @if ($isNotary)
                            {{ __('The notarial register entry has been created. The client must complete payment before attorney review can finish or digital notarization can begin.') }}
                        @else
                            {{ __('The attorney has created the notarial register entry. Complete the payment in the sidebar to continue.') }}
                        @endif
                    </div>
                    <div class="mt-3">
                        <a href="#section-payment" class="text-sm font-semibold text-amber-800 underline underline-offset-2 hover:text-amber-950 dark:text-amber-200 dark:hover:text-amber-100">{{ __('Go to payment') }}</a>
                    </div>
                </div>
            @endif

            <section id="section-workflow" class="ui-panel order-1 scroll-mt-6 p-6 sm:p-8">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:heading size="lg" class="!mb-0">{{ __('Workflow progress') }}</flux:heading>
                    <flux:badge size="sm" color="zinc">{{ trans_choice(':count stage|:count stages', count($workflowSteps), ['count' => count($workflowSteps)]) }}</flux:badge>
                </div>
                <div class="mt-5 flex gap-2 overflow-x-auto pb-2">
                    @foreach ($workflowSteps as $index => $step)
                        @php
                            $stepStyles = match ($step['state']) {
                                'complete' => 'border-emerald-200 bg-emerald-50 dark:border-emerald-900/40 dark:bg-emerald-950/30',
                                'current' => 'border-sky-200 bg-sky-50 dark:border-sky-900/40 dark:bg-sky-950/30',
                                default => 'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900',
                            };
                            $badgeStyles = match ($step['state']) {
                                'complete' => 'bg-emerald-600 text-white dark:bg-emerald-500',
                                'current' => 'bg-sky-600 text-white dark:bg-sky-500',
                                default => 'bg-zinc-200 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-100',
                            };
                            $stateLabel = match ($step['state']) {
                                'complete' => __('Complete'),
                                'current' => __('Current'),
                                default => __('Upcoming'),
                            };
                        @endphp
                        <div class="flex min-w-[8.5rem] flex-1 flex-col rounded-xl border p-3.5 {{ $stepStyles }}" title="{{ $step['description'] }}">
                            <div class="flex items-center justify-between gap-1.5">
                                <span class="inline-flex size-6 items-center justify-center rounded-full text-[10px] font-bold {{ $badgeStyles }}">{{ $index + 1 }}</span>
                                <span class="text-[9px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ $stateLabel }}</span>
                            </div>
                            <div class="mt-2 text-xs font-semibold leading-snug text-zinc-900 dark:text-zinc-100">{{ $step['label'] }}</div>
                            <p class="mt-1 line-clamp-2 text-[10px] leading-tight text-zinc-500 dark:text-zinc-400">{{ $step['description'] }}</p>
                        </div>
                    @endforeach
                </div>

                @if ($this->currentWorkflowStep && $notaryRequest->status !== NotaryRequestStatus::Notarized)
                    <flux:callout variant="info" class="mt-5" icon="information-circle">
                        <flux:callout.heading>{{ __('Focus on this step') }}</flux:callout.heading>
                        <flux:callout.text>{{ $this->currentWorkflowStep['description'] }}</flux:callout.text>
                    </flux:callout>
                @endif
            </section>

            {{-- Notarized Document & Certificate (shown after finalization) --}}
            @if ($notaryRequest->status === NotaryRequestStatus::Notarized)
                <section id="section-completed" class="order-2 rounded-xl border border-emerald-200 bg-emerald-50/50 p-6 shadow-sm dark:border-emerald-900/40 dark:bg-emerald-950/20">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40">
                            <svg class="h-5 w-5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-emerald-900 dark:text-emerald-100">{{ __('Notarized Document & Certificate') }}</h2>
                            <p class="text-sm text-emerald-700 dark:text-emerald-300">{{ __('Notarization completed on :date', ['date' => $notaryRequest->completed_at?->timezone('Asia/Manila')->format('F j, Y g:i A') ?? '-']) }}</p>
                        </div>
                    </div>

                    {{-- Generated Artifacts --}}
                    <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {{-- Notarized PDF --}}
                        @foreach ($requestDocuments as $document)
                            <div class="rounded-xl border border-emerald-200 bg-white p-4 dark:border-emerald-900/30 dark:bg-zinc-900">
                                <div class="flex items-center gap-2">
                                    <svg class="h-4 w-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                                    <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">{{ __('Notarized PDF') }}</span>
                                </div>
                                <div class="mt-2 truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $document->title }}</div>
                                <div class="mt-3 flex gap-2">
                                    <a href="{{ route('documents.download', $document) }}" class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-white px-2.5 py-1.5 text-xs font-medium text-emerald-700 transition hover:bg-emerald-50 dark:border-emerald-800 dark:bg-zinc-800 dark:text-emerald-300 dark:hover:bg-emerald-900/20">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                                        {{ __('Download') }}
                                    </a>
                                </div>
                            </div>
                        @endforeach

                        {{-- Notarial Certificate --}}
                        @foreach ($notaryRequest->registerEntries as $entry)
                            <div class="rounded-xl border border-emerald-200 bg-white p-4 dark:border-emerald-900/30 dark:bg-zinc-900">
                                <div class="flex items-center gap-2">
                                    <svg class="h-4 w-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" /></svg>
                                    <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">{{ __('Notarial Certificate') }}</span>
                                </div>
                                <div class="mt-2 truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $entry->document_title }}</div>
                                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Entry') }} #{{ str_pad($entry->entry_number, 3, '0', STR_PAD_LEFT) }} · {{ ucfirst(str_replace('_', ' ', $entry->notarial_act_type)) }}</div>
                                @if ($entry->certificate_path)
                                    <div class="mt-3">
                                        <a href="{{ route('documents.certificate.download', $entry->document_id ?? $requestDocuments->first()?->id) }}" class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-white px-2.5 py-1.5 text-xs font-medium text-emerald-700 transition hover:bg-emerald-50 dark:border-emerald-800 dark:bg-zinc-800 dark:text-emerald-300 dark:hover:bg-emerald-900/20">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                                            {{ __('Download') }}
                                        </a>
                                    </div>
                                @endif
                            </div>

                            {{-- QR Verification --}}
                            @if ($entry->qr_code_path || $entry->qr_verification_token)
                                <div class="rounded-xl border border-emerald-200 bg-white p-4 dark:border-emerald-900/30 dark:bg-zinc-900">
                                    <div class="flex items-center gap-2">
                                        <svg class="h-4 w-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75v-.75ZM6.75 16.5h.75v.75h-.75v-.75ZM16.5 6.75h.75v.75h-.75v-.75ZM13.5 13.5h.75v.75h-.75v-.75ZM13.5 19.5h.75v.75h-.75v-.75ZM19.5 13.5h.75v.75h-.75v-.75ZM19.5 19.5h.75v.75h-.75v-.75ZM16.5 16.5h.75v.75h-.75v-.75Z" /></svg>
                                        <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">{{ __('QR Verification') }}</span>
                                    </div>
                                    <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Scan to verify authenticity') }}</div>
                                    @if ($entry->qr_verification_token)
                                        <div class="mt-2">
                                            <a href="{{ route('notary.verify', ['token' => $entry->qr_verification_token]) }}" target="_blank" class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-white px-2.5 py-1.5 text-xs font-medium text-emerald-700 transition hover:bg-emerald-50 dark:border-emerald-800 dark:bg-zinc-800 dark:text-emerald-300 dark:hover:bg-emerald-900/20">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                                                {{ __('Verify') }}
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        @endforeach
                    </div>

                    {{-- Audit & Blockchain --}}
                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        {{-- Audit Logs --}}
                        <div class="rounded-xl border border-emerald-200 bg-white p-4 dark:border-emerald-900/30 dark:bg-zinc-900">
                            <div class="flex items-center gap-2">
                                <svg class="h-4 w-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" /></svg>
                                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">{{ __('Audit Logs') }}</span>
                            </div>
                            <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ trans_choice(':count journal entry|:count journal entries', $journalEntries->count(), ['count' => $journalEntries->count()]) }}</div>
                            <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-500">{{ __('Complete notarization trail recorded') }}</div>
                        </div>

                        {{-- Blockchain Reference --}}
                        <div class="rounded-xl border border-emerald-200 bg-white p-4 dark:border-emerald-900/30 dark:bg-zinc-900">
                            <div class="flex items-center gap-2">
                                <svg class="h-4 w-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" /></svg>
                                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">{{ __('Blockchain Reference') }}</span>
                            </div>
                            @php
                                $anchoredDocs = $requestDocuments->filter(fn ($doc) => $doc->documentHash?->transaction_id);
                                $totalDocs = $requestDocuments->count();
                            @endphp
                            <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                {{ trans_choice(':count of :total document anchored|:count of :total documents anchored', $anchoredDocs->count(), ['count' => $anchoredDocs->count(), 'total' => $totalDocs]) }}
                            </div>
                            @if ($anchoredDocs->isNotEmpty())
                                <div class="mt-2 space-y-1">
                                    @foreach ($anchoredDocs as $doc)
                                        <div class="truncate text-xs font-mono text-zinc-500 dark:text-zinc-400" title="{{ $doc->documentHash->transaction_id }}">
                                            {{ __('TX:') }} {{ \Illuminate\Support\Str::limit($doc->documentHash->transaction_id, 24) }}
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="mt-1 text-xs text-amber-600 dark:text-amber-400">{{ __('Blockchain service was unavailable — can be retried.') }}</div>
                            @endif
                        </div>
                    </div>

                    {{-- Ready for actions --}}
                    <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-emerald-200 pt-4 dark:border-emerald-800">
                        <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">{{ __('Ready for:') }}</span>
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                            {{ __('Download') }}
                        </span>
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                            {{ __('Verification') }}
                        </span>
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" /></svg>
                            {{ __('Archive') }}
                        </span>
                    </div>
                </section>
            @endif

            <section id="section-summary" class="ui-panel order-6 scroll-mt-6 p-6 sm:p-8">
                <flux:heading size="lg" class="!mb-4">{{ __('Case summary') }}</flux:heading>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg border border-zinc-100 bg-zinc-50/50 p-3.5 dark:border-zinc-800 dark:bg-zinc-800/30">
                        <div class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Submitted') }}</div>
                        <div class="mt-1.5 text-sm font-semibold text-zinc-800 dark:text-zinc-200">{{ $notaryRequest->submitted_at?->toDateTimeString() ?? __('Not yet') }}</div>
                    </div>
                    <div class="rounded-lg border border-zinc-100 bg-zinc-50/50 p-3.5 dark:border-zinc-800 dark:bg-zinc-800/30">
                        <div class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Documents linked') }}</div>
                        <div class="mt-1.5 text-sm font-semibold text-zinc-800 dark:text-zinc-200">{{ $requestDocuments->count() }}</div>
                    </div>
                </div>
                @if (($notaryRequest->metadata['notes'] ?? '') !== '')
                    <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300">
                        {{ $notaryRequest->metadata['notes'] }}
                    </div>
                @endif

                @if ($nextDocumentAction !== null)
                    <div class="mt-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-4 dark:border-sky-900/40 dark:bg-sky-950/30">
                        <div class="text-xs font-semibold uppercase tracking-wider text-sky-700 dark:text-sky-300">{{ __('Next recommended action') }}</div>
                        <div class="mt-2 text-sm font-medium text-sky-900 dark:text-sky-100">{{ $nextDocumentAction['description'] }}</div>
                        <div class="mt-3">
                            <flux:button variant="outline" :href="$nextDocumentAction['href']" wire:navigate>{{ $nextDocumentAction['label'] }}</flux:button>
                        </div>
                    </div>
                @endif
            </section>

            <section id="section-session" class="ui-panel order-5 scroll-mt-6 p-6 sm:p-8">
                <flux:heading size="lg" class="!mb-4">{{ __('Video session') }}</flux:heading>
                @if ($canScheduleSession)
                    <div class="mt-4 space-y-4">
                        <flux:field>
                            <flux:label>{{ __('Scheduled for') }}</flux:label>
                            <flux:input wire:model="scheduleAt" type="datetime-local" />
                            <flux:error name="scheduleAt" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('Provider') }}</flux:label>
                            <select wire:model="providerName" class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                                <option value="jitsi">{{ __('Jitsi Meet (auto-generated room)') }}</option>
                                <option value="manual">{{ __('Manual (paste URL below)') }}</option>
                            </select>
                            <flux:error name="providerName" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('Meeting URL') }}</flux:label>
                            <flux:input wire:model="meetingUrl" type="url" placeholder="https://..." />
                            <flux:error name="meetingUrl" />
                        </flux:field>
                        <flux:button variant="outline" type="button" wire:click="scheduleSession">{{ __('Schedule session') }}</flux:button>
                        <flux:error name="scheduleSession" />
                    </div>
                @else
                    <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300">
                        @if (!$isNotary)
                            {{ __('Only the assigned notary can schedule video sessions.') }}
                        @else
                            {{ __('Video session scheduling becomes available after all signers have completed signing.') }}
                        @endif
                    </div>
                @endif
                @if ($recentSessions->isNotEmpty())
                    <div class="mt-5 space-y-3 border-t border-zinc-100 pt-5 dark:border-zinc-800">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Sessions') }}</h3>
                        @foreach ($recentSessions as $session)
                            <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800/40">
                                {{-- Session header --}}
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex min-w-0 items-center gap-2.5">
                                        @if ($session->status === 'in_progress')
                                            <span class="flex h-2 w-2 rounded-full bg-red-500 animate-pulse"></span>
                                        @elseif ($session->status === 'completed')
                                            <span class="flex h-2 w-2 rounded-full bg-emerald-500"></span>
                                        @else
                                            <span class="flex h-2 w-2 rounded-full bg-zinc-300 dark:bg-zinc-600"></span>
                                        @endif
                                        <div class="min-w-0">
                                            <span class="block truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ ucfirst($session->provider_name) }}</span>
                                            <span class="mt-0.5 block text-xs text-zinc-400 dark:text-zinc-500 sm:mt-0">{{ $session->scheduled_for?->format('M j, g:i A') ?? '-' }}</span>
                                        </div>
                                    </div>
                                    <span class="inline-flex w-fit rounded-md border border-zinc-200 bg-zinc-50 px-2 py-0.5 text-[11px] font-medium text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">{{ $session->status }}</span>
                                </div>

                                {{-- Scheduled: Start button (notary only) --}}
                                @if ($session->status === 'scheduled' && $isNotary)
                                    <div class="mt-3">
                                        <flux:button variant="primary" size="sm" type="button" wire:click="startSession({{ $session->id }})">{{ __('Start session') }}</flux:button>
                                        <flux:error name="startSession" />
                                    </div>
                                @endif

                                {{-- In progress: Video room link (everyone) --}}
                                @if ($session->status === 'in_progress')
                                    @if (is_string($session->meeting_url) && $session->meeting_url !== '')
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <a href="{{ auth()->user()?->role->value === 'notary' ? route('notary.requests.session.live', [$notaryRequest, $session]) : route('notary-requests.session.live', [$notaryRequest, $session]) }}"
                                               target="_blank"
                                               class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold text-white shadow-sm" style="background-color: #18181b;">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
                                                {{ __('Join video room') }}
                                            </a>
                                            <a href="{{ $session->meeting_url }}" target="_blank"
                                               class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-xs font-medium text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">
                                                {{ __('Open in new tab') }}
                                            </a>
                                        </div>
                                    @endif

                                    {{-- Attorney checklist (NOTARY ROLE ONLY) --}}
                                    @if ($isNotary)
                                        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50/50 p-4 dark:border-amber-900/40 dark:bg-amber-950/20">
                                            <div class="flex items-center gap-2">
                                                <svg class="h-4 w-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                                <span class="text-xs font-semibold text-amber-800 dark:text-amber-300">{{ __('Attorney verification checklist') }}</span>
                                            </div>
                                            <p class="mt-1 text-[11px] text-amber-700 dark:text-amber-400">{{ __('Complete all items before ending the session. Only the assigned notary can perform this step.') }}</p>
                                            <div class="mt-3 space-y-2">
                                                @foreach (config('docutrust.notary.verification_checklist', []) as $key)
                                                    <label class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 transition-colors hover:bg-amber-100/50 dark:hover:bg-amber-950/30">
                                                        <input type="checkbox" class="h-4 w-4 rounded border-amber-300 text-amber-600 focus:ring-amber-500 dark:border-amber-700" wire:model.live="sessionChecklist.{{ $key }}" />
                                                        <span class="text-xs text-zinc-700 dark:text-zinc-300">{{ __(ucfirst(str_replace('_', ' ', $key))) }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                            <flux:error name="sessionChecklist" />
                                            <div class="mt-4">
                                                <flux:button variant="primary" size="sm" type="button" wire:click="completeSession({{ $session->id }})">{{ __('Complete session') }}</flux:button>
                                                <flux:error name="completeSession" />
                                            </div>
                                        </div>
                                    @else
                                        {{-- Non-notary sees a waiting message --}}
                                        <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-xs text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800/40 dark:text-zinc-400">
                                            <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ __('Session in progress') }}</span> — {{ __('The notary is verifying your identity. Please stay on the video call.') }}
                                        </div>
                                    @endif
                                @endif

                                {{-- Completed session info --}}
                                @if ($session->status === 'completed')
                                    <div class="mt-2 text-xs text-emerald-600 dark:text-emerald-400">
                                        {{ __('Completed') }} {{ $session->ended_at?->diffForHumans() }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section id="section-parties" class="ui-panel order-4 scroll-mt-6 p-6 sm:p-8">
                <flux:heading size="lg" class="!mb-1">{{ __('Parties & identity') }}</flux:heading>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Principal signers attached to this case. Identity documents require email OTP confirmation before upload.') }}</p>
                <div class="mt-4 space-y-3">
                    @forelse ($requestSigners as $signer)
                        <div class="rounded-xl border border-zinc-200 bg-white p-4 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $signer->full_name }}</div>
                                    <div class="mt-1 text-zinc-500 dark:text-zinc-400">{{ $signer->email }} @if ($signer->phone) • {{ $signer->phone }} @endif</div>
                                    @if ($signer->role && $signer->role !== 'signer')
                                        <span class="mt-1 inline-block rounded-md border border-zinc-200 bg-zinc-50 px-2 py-0.5 text-[11px] font-medium text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">{{ ucfirst($signer->role) }}</span>
                                    @endif
                                    @php
                                        $invitation = $signerInvitations[$signer->id] ?? null;
                                        $hasPortalAccess = isset($enotaryPortalEmails[strtolower($signer->email)]);
                                    @endphp
                                    <div class="mt-2 flex flex-wrap items-center gap-2">
                                        @if ($hasPortalAccess)
                                            <span class="inline-flex rounded-md border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                                                {{ __('Portal active') }}
                                            </span>
                                        @elseif ($invitation instanceof \App\Models\EnotaryInvitation && $invitation->isPending())
                                            <span class="inline-flex rounded-md border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-200">
                                                {{ __('Invitation pending') }}
                                            </span>
                                        @elseif ($invitation instanceof \App\Models\EnotaryInvitation && $invitation->isAccepted())
                                            <span class="inline-flex rounded-md border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                                                {{ __('Invitation accepted') }}
                                            </span>
                                        @else
                                            <span class="inline-flex rounded-md border border-zinc-200 bg-zinc-50 px-2 py-0.5 text-[11px] font-medium text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">
                                                {{ __('No portal invite yet') }}
                                            </span>
                                        @endif
                                        @if ($isNotary && ! $hasPortalAccess && ! in_array($notaryRequest->status->value, ['digitalized', 'notarized', 'rejected', 'failed', 'cancelled'], true))
                                            <flux:button size="xs" variant="outline" type="button" wire:click="resendSignerPortalInvite({{ $signer->id }})">
                                                {{ $invitation instanceof \App\Models\EnotaryInvitation && $invitation->isPending() ? __('Resend invite') : __('Send portal invite') }}
                                            </flux:button>
                                        @endif
                                    </div>
                                </div>
                                @if ($isNotary && ! in_array($notaryRequest->status->value, ['digitalized', 'notarized', 'rejected', 'failed', 'cancelled'], true))
                                    <button type="button" wire:click="removeSigner({{ $signer->id }})" wire:confirm="{{ __('Remove this signer?') }}"
                                        class="rounded-lg p-1.5 text-zinc-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/20 dark:hover:text-red-400">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                                    </button>
                                @endif
                            </div>
                            @if ($notaryRequest->status === NotaryRequestStatus::Submitted && $canManageLifecycle)
                                <div class="mt-4 space-y-3 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                                    <flux:button size="sm" variant="outline" type="button" wire:click="sendSignerIdentityOtp({{ $signer->id }})">{{ __('Send email OTP') }}</flux:button>
                                    <flux:field>
                                        <flux:label>{{ __('OTP code') }}</flux:label>
                                        <div class="flex flex-wrap items-end gap-2">
                                            <flux:input class="max-w-xs" type="text" wire:model="identityOtpCode" placeholder="{{ __('Enter code') }}" />
                                            <flux:button size="sm" variant="primary" type="button" wire:click="verifySignerIdentityOtp({{ $signer->id }})">{{ __('Verify OTP') }}</flux:button>
                                        </div>
                                        <flux:error name="identityOtp" />
                                        <flux:error name="identityOtpCode" />
                                    </flux:field>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <flux:field>
                                            <flux:label>{{ __('ID type') }}</flux:label>
                                            <select wire:model="pendingIdType" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                                                <option value="passport">{{ __('Passport') }}</option>
                                                <option value="drivers_license">{{ __('Driver license') }}</option>
                                                <option value="national_id">{{ __('National ID') }}</option>
                                                <option value="other">{{ __('Other') }}</option>
                                            </select>
                                        </flux:field>
                                        <flux:field>
                                            <flux:label>{{ __('ID number') }}</flux:label>
                                            <flux:input type="text" wire:model="pendingIdNumber" />
                                            <flux:error name="pendingIdNumber" />
                                        </flux:field>
                                    </div>
                                    <flux:field>
                                        <flux:label>{{ __('Government ID scan') }}</flux:label>
                                        <input type="file" wire:model="idImageFile" accept=".pdf,.jpg,.jpeg,.png" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                                        <flux:error name="idImageFile" />
                                    </flux:field>
                                    <flux:field>
                                        <flux:label>{{ __('Selfie (optional)') }}</flux:label>
                                        <input type="file" wire:model="selfieImageFile" accept=".jpg,.jpeg,.png" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                                        <flux:error name="selfieImageFile" />
                                    </flux:field>
                                    <flux:button size="sm" variant="primary" type="button" wire:click="saveSignerIdentityDocuments({{ $signer->id }})">{{ __('Submit ID for review') }}</flux:button>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-zinc-300 px-4 py-6 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">{{ __('No signers added yet. Add signers below to proceed.') }}</div>
                    @endforelse
                </div>

                {{-- Add Signer Form (Attorney only) --}}
                @if ($isNotary && ! in_array($notaryRequest->status->value, ['digitalized', 'notarized', 'rejected', 'failed', 'cancelled'], true))
                    <div class="mt-5 rounded-xl border border-zinc-200 bg-zinc-50/50 p-4 dark:border-zinc-700 dark:bg-zinc-800/30">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Add a signer') }}</h3>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <flux:field class="sm:col-span-2">
                                <flux:label>{{ __('Full name') }} <span class="text-rose-500">*</span></flux:label>
                                <flux:input type="text" wire:model="newSignerName" placeholder="{{ __('Juan Dela Cruz') }}" />
                                <flux:error name="newSignerName" />
                            </flux:field>
                            <flux:field>
                                <flux:label>{{ __('Email') }} <span class="text-rose-500">*</span></flux:label>
                                <flux:input type="email" wire:model="newSignerEmail" placeholder="{{ __('juan@example.com') }}" />
                                <flux:error name="newSignerEmail" />
                            </flux:field>
                            <flux:field>
                                <flux:label>{{ __('Phone') }}</flux:label>
                                <flux:input type="text" wire:model="newSignerPhone" placeholder="{{ __('+63 9XX XXX XXXX') }}" />
                                <flux:error name="newSignerPhone" />
                            </flux:field>
                            <flux:field class="sm:col-span-2">
                                <flux:label>{{ __('Address') }}</flux:label>
                                <flux:input type="text" wire:model="newSignerAddress" placeholder="{{ __('Complete address') }}" />
                                <flux:error name="newSignerAddress" />
                            </flux:field>
                            <flux:field>
                                <flux:label>{{ __('Role in document') }}</flux:label>
                                <select wire:model="newSignerRole" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                                    <option value="signer">{{ __('Signer') }}</option>
                                    <option value="witness">{{ __('Witness') }}</option>
                                    <option value="affiant">{{ __('Affiant') }}</option>
                                    <option value="principal">{{ __('Principal') }}</option>
                                </select>
                                <flux:error name="newSignerRole" />
                            </flux:field>
                        </div>
                        <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('An e-Notary portal invitation email is sent automatically so the signer can create their account and access this case.') }}
                        </p>
                        <div class="mt-4">
                            <flux:button variant="outline" type="button" wire:click="addSigner">{{ __('Add signer & send invite') }}</flux:button>
                        </div>
                        <flux:error name="resendInvite" />
                    </div>
                @endif

                @if ($isNotary && $pendingIdentityReviews->isNotEmpty())
                    <div class="mt-6 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Pending identity review') }}</h3>
                        <div class="mt-3 space-y-3">
                            @foreach ($pendingIdentityReviews as $review)
                                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm dark:border-amber-900/40 dark:bg-amber-950/20">
                                    <div class="font-medium text-amber-950 dark:text-amber-100">{{ $review->signer?->full_name }}</div>
                                    <div class="mt-1 text-amber-900/80 dark:text-amber-200/80">{{ __('ID type') }}: {{ $review->id_type }} • {{ __('Number') }}: {{ $review->id_number }}</div>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <flux:button size="sm" variant="primary" type="button" wire:click="approveIdentityRecord({{ $review->id }})">{{ __('Approve') }}</flux:button>
                                        <flux:button size="sm" variant="outline" type="button" wire:click="$set('identityRejectId', {{ $review->id }})">{{ __('Reject') }}</flux:button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($identityRejectId)
                    <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900/40 dark:bg-red-950/20">
                        <flux:field>
                            <flux:label>{{ __('Rejection reason') }}</flux:label>
                            <flux:textarea wire:model="identityRejectReason" rows="3" />
                            <flux:error name="identityRejectReason" />
                        </flux:field>
                        <flux:button class="mt-3" variant="outline" type="button" wire:click="rejectIdentityRecord">{{ __('Confirm rejection') }}</flux:button>
                        <flux:error name="rejectIdentity" />
                    </div>
                @endif

                <div class="mt-6 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                    @if (! $isNotary)
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Philippines location check') }}</h3>
                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Uses your browser coordinates together with server-side IP intelligence. Failed checks flag the request automatically.') }}</p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>{{ __('Latitude (optional)') }}</flux:label>
                            <flux:input type="text" wire:model="geoLatitude" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('Longitude (optional)') }}</flux:label>
                            <flux:input type="text" wire:model="geoLongitude" />
                        </flux:field>
                        <flux:field class="sm:col-span-2">
                            <flux:label>{{ __('Signer (optional)') }}</flux:label>
                            <select wire:model="geoSignerId" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                                <option value="">{{ __('Entire request') }}</option>
                                @foreach ($requestSigners as $signer)
                                    <option value="{{ $signer->id }}">{{ $signer->full_name }}</option>
                                @endforeach
                            </select>
                        </flux:field>
                    </div>
                    <flux:button class="mt-3" variant="outline" type="button" wire:click="runBrowserGeoVerification">{{ __('Run location verification') }}</flux:button>
                    <flux:error name="runBrowserGeoVerification" />
                </div>

                @if ($geoHistory->isNotEmpty())
                    <div class="mt-6 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Recent geo checks') }}</h3>
                        <div class="mt-3 space-y-2 text-xs text-zinc-600 dark:text-zinc-300">
                            @foreach ($geoHistory as $log)
                                <div class="flex flex-wrap justify-between gap-2 rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700">
                                    <span>{{ $log->verified_at?->toDateTimeString() ?? '-' }}</span>
                                    <span class="font-medium">{{ $log->verification_status->value }}</span>
                                    <span>{{ $log->country ?? '—' }} @if ($log->city) • {{ $log->city }} @endif</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
                @endif
            </section>

            @unless ($isEnotaryPortalSigner)
            <section id="section-documents" class="ui-panel order-3 scroll-mt-6 p-6 sm:p-8">
                <flux:heading size="lg" class="!mb-1">{{ __('Documents') }}</flux:heading>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Upload instruments, prepare fields, and send for signing.') }}</p>
                <div class="mt-4 space-y-3">
                    @forelse ($requestDocuments as $document)
                        @php
                            $artifactState = collect($finalizationReadiness['documents'])->firstWhere('document_id', $document->id);
                            $workflowState = $documentWorkflowStates[$document->id] ?? null;
                        @endphp
                        <div class="rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <a href="{{ route('documents.show', $document) }}" wire:navigate class="min-w-0 flex-1">
                                    <div class="truncate font-medium text-zinc-800 dark:text-zinc-200">{{ $document->title }}</div>
                                    <div class="mt-1 text-zinc-500 dark:text-zinc-400">{{ $document->status->value }}</div>
                                    @if (is_array($workflowState))
                                        <div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ trans_choice(':count signer|:count signers', $workflowState['participant_counts']['signers'], ['count' => $workflowState['participant_counts']['signers']]) }}
                                            <span class="mx-1 text-zinc-300 dark:text-zinc-600">•</span>
                                            {{ trans_choice(':count approver|:count approvers', $workflowState['participant_counts']['approvers'], ['count' => $workflowState['participant_counts']['approvers']]) }}
                                            <span class="mx-1 text-zinc-300 dark:text-zinc-600">•</span>
                                            {{ trans_choice(':count recipient|:count recipients', $workflowState['participant_counts']['recipients'], ['count' => $workflowState['participant_counts']['recipients']]) }}
                                            <span class="mx-1 text-zinc-300 dark:text-zinc-600">•</span>
                                            {{ trans_choice(':count field|:count fields', $workflowState['field_count'], ['count' => $workflowState['field_count']]) }}
                                        </div>
                                        <div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ trans_choice(':count pending|:count pending', $workflowState['participant_counts']['pending'], ['count' => $workflowState['participant_counts']['pending']]) }}
                                            <span class="mx-1 text-zinc-300 dark:text-zinc-600">•</span>
                                            {{ trans_choice(':count signed|:count signed', $workflowState['participant_counts']['signed'], ['count' => $workflowState['participant_counts']['signed']]) }}
                                            <span class="mx-1 text-zinc-300 dark:text-zinc-600">•</span>
                                            {{ trans_choice(':count approved|:count approved', $workflowState['participant_counts']['approved'], ['count' => $workflowState['participant_counts']['approved']]) }}
                                        </div>
                                    @endif
                                    @if (is_array($artifactState))
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <span class="rounded-full px-2.5 py-1 text-[11px] font-medium {{ $artifactState['has_final_pdf'] ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' }}">{{ __('Final PDF') }}</span>
                                            <span class="rounded-full px-2.5 py-1 text-[11px] font-medium {{ $artifactState['has_certificate'] ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' }}">{{ __('Certificate') }}</span>
                                            <span class="rounded-full px-2.5 py-1 text-[11px] font-medium {{ $artifactState['has_document_hash'] ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' }}">{{ __('Hash') }}</span>
                                            <span class="rounded-full px-2.5 py-1 text-[11px] font-medium {{ $artifactState['has_blockchain_transaction'] ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' }}">{{ __('Blockchain') }}</span>
                                        </div>
                                        @if ($artifactState['issues'] !== [])
                                            <div class="mt-2 text-xs text-amber-700 dark:text-amber-300">
                                                {{ implode(' ', $artifactState['issues']) }}
                                            </div>
                                        @endif
                                    @endif
                                </a>
                                @if ($canManageLifecycle || $isNotary)
                                    <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:justify-end">
                                        @if ($document->status->value === 'draft' && $isNotary)
                                            <flux:button class="w-full sm:w-auto" variant="outline" :href="route('notary.documents.prepare', $document)" wire:navigate>{{ __('Prepare fields') }}</flux:button>
                                            @if (is_array($workflowState) && $workflowState['can_send'])
                                                <flux:button class="w-full sm:w-auto" variant="primary" type="button" wire:click="sendLinkedDocument({{ $document->id }})" wire:confirm="{{ __('Send this document to signers for signing?') }}">{{ __('Send to signers') }}</flux:button>
                                            @endif
                                        @elseif ($document->status->value === 'pending')
                                            @php
                                                $isAttorneySigningPhase = $isNotary && $document->documentSigners->contains(fn ($s) => (int) $s->user_id === (int) auth()->id() && $s->status->value === 'pending');
                                            @endphp
                                            @if ($isAttorneySigningPhase)
                                                {{-- Attorney signing phase: show prepare/sign links --}}
                                                <flux:button class="w-full sm:w-auto" variant="outline" :href="route('notary.documents.prepare', $document)" wire:navigate>{{ __('Prepare Attorney Fields') }}</flux:button>
                                                @php
                                                    $attorneySigner = $document->documentSigners->first(fn ($s) => (int) $s->user_id === (int) auth()->id());
                                                @endphp
                                                @if ($attorneySigner && $document->signatureFields->where('signer_id', $attorneySigner->id)->isNotEmpty())
                                                    <a href="{{ route('notary.sign.account.show', $attorneySigner->id) }}"
                                                       class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-700 sm:w-auto">
                                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg>
                                                        {{ __('Sign Document') }}
                                                    </a>
                                                @endif
                                            @else
                                                {{-- Normal pending: awaiting client signatures --}}
                                                <span class="inline-flex items-center gap-1.5 rounded-md border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-medium text-sky-700 dark:border-sky-900/40 dark:bg-sky-950/30 dark:text-sky-300">
                                                    <svg class="h-3 w-3 animate-pulse" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                                                    {{ __('Awaiting signer signatures') }}
                                                </span>
                                                @if ($isNotary)
                                                    @foreach ($document->documentSigners->filter(fn ($s) => $s->requiresAction()) as $pendingSigner)
                                                        <flux:button class="w-full sm:w-auto" size="sm" variant="ghost" type="button"
                                                            wire:click="resendSignerEmail({{ $document->id }}, {{ $pendingSigner->id }})"
                                                            wire:confirm="{{ __('Resend signing email to :name?', ['name' => $pendingSigner->name]) }}">
                                                            {{ __('Resend to :name', ['name' => $pendingSigner->name]) }}
                                                        </flux:button>
                                                    @endforeach
                                                @endif
                                            @endif
                                        @elseif ($document->status->value === 'completed')
                                            @if ($isNotary && $canAttorneySign)
                                                @php
                                                    $attorneySigner = $document->documentSigners->first(fn ($s) => (int) $s->user_id === (int) auth()->id());
                                                    $attorneyHasSigned = $attorneySigner && $attorneySigner->status->value === 'signed';
                                                @endphp
                                                @if (! $attorneyHasSigned)
                                                    <flux:button class="w-full sm:w-auto" variant="primary" type="button" wire:click="signAsAttorney({{ $document->id }})">{{ __('Prepare Attorney Fields') }}</flux:button>
                                                    @if ($attorneySigner)
                                                        <a href="{{ route('notary.sign.account.show', $attorneySigner->id) }}"
                                                           class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-medium text-indigo-700 transition hover:bg-indigo-100 dark:border-indigo-900/40 dark:bg-indigo-950/30 dark:text-indigo-300 dark:hover:bg-indigo-950/50 sm:w-auto">
                                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg>
                                                            {{ __('Open signing page') }}
                                                        </a>
                                                    @endif
                                                @else
                                                    <span class="inline-flex items-center gap-1.5 rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                                        {{ __('Attorney signed') }}
                                                    </span>
                                                @endif
                                            @endif
                                            @if (! ($artifactState['has_certificate'] ?? false))
                                                <flux:button class="w-full sm:w-auto" variant="outline" type="button" wire:click="generateDocumentCertificate({{ $document->id }})">{{ __('Generate certificate') }}</flux:button>
                                            @endif
                                            @if (! ($artifactState['has_blockchain_transaction'] ?? false))
                                                <flux:button class="w-full sm:w-auto" variant="outline" type="button" wire:click="refreshBlockchainProof({{ $document->id }})">{{ __('Refresh blockchain') }}</flux:button>
                                            @endif
                                        @endif
                                    </div>
                                @endif
                            </div>
                            @if (app()->environment('local') && in_array($document->status->value, ['pending', 'completed'], true))
                                @php
                                    $localTestingSigners = $document->documentSigners->filter(
                                        fn ($signer) => $signer->requiresAction() && is_string($signer->access_token) && $signer->access_token !== ''
                                    );
                                @endphp
                                @if ($localTestingSigners->isNotEmpty())
                                    <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-xs text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                                        <div class="font-semibold">{{ __('Local testing signer links') }}</div>
                                        <div class="mt-1 text-[11px] opacity-80">{{ __('Temporary shortcut for local development after sending documents for signing.') }}</div>
                                        <div class="mt-2 flex flex-col gap-2">
                                            @foreach ($localTestingSigners as $signer)
                                                <a href="{{ app(\App\Services\SigningMethodService::class)->signerEntryUrl($signer) }}"
                                                   target="_blank"
                                                   class="inline-flex items-center justify-between gap-3 rounded-md border border-amber-300 bg-white px-3 py-2 text-left text-[11px] font-medium text-amber-900 transition hover:bg-amber-100 dark:border-amber-800 dark:bg-zinc-900 dark:text-amber-100 dark:hover:bg-amber-950/40">
                                                    <span>{{ $signer->name }}</span>
                                                    <span class="truncate text-[10px] opacity-70">{{ $signer->signingMethod()->value }}</span>
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endif
                            @if ($canManageLifecycle)
                                @error('sendDocument'.$document->id)
                                    <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-300">
                                        {{ $message }}
                                    </div>
                                @enderror
                            @endif
                            @if ($canManageLifecycle)
                                @error('artifactDocument'.$document->id)
                                    <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-300">
                                        {{ $message }}
                                    </div>
                                @enderror
                            @endif
                            @if (is_array($workflowState) && $document->status->value === 'draft' && $workflowState['missing_signers'] !== [])
                                <div class="mt-3 text-xs text-amber-700 dark:text-amber-300">
                                    {{ __('Missing fields for: :signers', ['signers' => implode(', ', $workflowState['missing_signers'])]) }}
                                </div>
                            @endif
                            @if (is_array($workflowState) && is_string($workflowState['blocking_reason']) && $workflowState['blocking_reason'] !== '' && $document->status->value === 'draft')
                                <div class="mt-2 text-xs text-amber-700 dark:text-amber-300">
                                    {{ $workflowState['blocking_reason'] }}
                                </div>
                            @endif
                            @if (is_array($workflowState) && $workflowState['account_link_blockers'] !== [])
                                <div class="mt-2 text-xs text-amber-700 dark:text-amber-300">
                                    {{ __('Account-linked signer setup is incomplete for: :signers', ['signers' => implode(', ', $workflowState['account_link_blockers'])]) }}
                                </div>
                            @endif
                            @if ($canManageLifecycle && $notaryRequest->status === NotaryRequestStatus::Draft && $document->status->value === 'draft')
                                <div class="mt-3 flex flex-col gap-3 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 sm:flex-row sm:items-center dark:border-zinc-700 dark:bg-zinc-800/40">
                                    <input
                                        type="file"
                                        wire:model="replaceDocumentFile"
                                        accept="application/pdf,.pdf"
                                        class="w-full flex-1 text-xs text-zinc-600 dark:text-zinc-400"
                                    />
                                    <flux:button class="w-full sm:w-auto" variant="outline" size="sm" type="button" wire:click="replaceDocument({{ $document->id }})" wire:loading.attr="disabled" wire:target="replaceDocumentFile">{{ __('Replace') }}</flux:button>
                                </div>
                                <div wire:loading wire:target="replaceDocumentFile" class="mt-1 text-xs text-teal-600 dark:text-teal-400">{{ __('Uploading...') }}</div>
                                <flux:error name="replaceDocumentFile" />
                            @endif
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-zinc-300 px-4 py-6 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">{{ __('No documents linked yet.') }}</div>
                    @endforelse
                </div>

                {{-- Upload Document Form (Attorney / Admin) --}}
                @if (($isNotary || $canManageLifecycle) && ! in_array($notaryRequest->status->value, ['digitalized', 'notarized', 'rejected', 'failed', 'cancelled'], true))
                    <div class="mt-5 rounded-xl border border-zinc-200 bg-zinc-50/50 p-4 dark:border-zinc-700 dark:bg-zinc-800/30">
                        <div class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Upload a new document for this request') }}</div>
                        <div class="mt-3 space-y-3">
                            <flux:field>
                                <flux:label>{{ __('Document title') }} <span class="text-rose-500">*</span></flux:label>
                                <flux:input wire:model="newDocumentTitle" type="text" placeholder="{{ __('e.g. Affidavit of support') }}" />
                                <flux:error name="newDocumentTitle" />
                            </flux:field>
                            <flux:field>
                                <flux:label>{{ __('PDF file') }} <span class="text-rose-500">*</span></flux:label>
                                <input type="file" wire:model="newDocumentFile" accept="application/pdf,.pdf" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                                <flux:error name="newDocumentFile" />
                            </flux:field>
                            <div wire:loading wire:target="newDocumentFile" class="text-xs text-teal-600 dark:text-teal-400">{{ __('Uploading...') }}</div>
                            <flux:button variant="primary" type="button" wire:click="createDocument" wire:loading.attr="disabled" wire:target="newDocumentFile,createDocument">{{ __('Upload document') }}</flux:button>
                        </div>
                    </div>
                @endif
            </section>
            @endunless

            @unless ($isEnotaryPortalSigner)
            <section id="section-readiness" class="ui-panel order-8 scroll-mt-6 p-6 sm:p-8">
                <flux:heading size="lg" class="!mb-4">{{ __('Finalization readiness') }}</flux:heading>
                @if ($finalizationReadiness['ready'])
                    <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-100">
                        {{ __('All linked documents have the required notarization artifacts.') }}
                    </div>
                @else
                    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                        <div class="font-medium">{{ __('This request is not ready to finalize yet.') }}</div>
                        <ul class="mt-2 list-disc pl-5">
                            @foreach ($finalizationReadiness['issues'] as $issue)
                                <li>{{ $issue }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>

            <section id="section-journal" class="ui-panel order-9 scroll-mt-6 p-6 sm:p-8">
                <flux:heading size="lg" class="!mb-4">{{ __('Journal') }}</flux:heading>
                <div class="mt-4 space-y-4">
                    @forelse ($journalEntries as $entry)
                        <div class="rounded-xl border border-zinc-200 bg-white p-4 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="flex items-center justify-between gap-4">
                                <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ str_replace('_', ' ', $entry->entry_type) }}</div>
                                <div class="text-xs text-zinc-400 dark:text-zinc-500">{{ $entry->recorded_at?->toDateTimeString() ?? '-' }}</div>
                            </div>
                            <div class="mt-2 text-zinc-600 dark:text-zinc-300">{{ $entry->summary }}</div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-zinc-300 px-4 py-6 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">{{ __('No journal entries yet.') }}</div>
                    @endforelse
                </div>
            </section>
            @endunless
        </div>

        <aside class="flex flex-col gap-4 lg:col-span-3 xl:col-span-2 lg:sticky lg:top-4 lg:max-h-[calc(100vh-6rem)] lg:overflow-y-auto">
            <nav class="ui-panel p-5 sm:p-6">
                <flux:heading size="lg" class="mb-3">{{ __('On this page') }}</flux:heading>
                <ul class="space-y-1.5 text-sm">
                    <li><a href="#section-workflow" class="font-medium text-zinc-600 hover:text-teal-700 dark:text-zinc-400 dark:hover:text-teal-400">{{ __('Workflow') }}</a></li>
                    @unless ($isEnotaryPortalSigner)
                        <li><a href="#section-documents" class="font-medium text-zinc-600 hover:text-teal-700 dark:text-zinc-400 dark:hover:text-teal-400">{{ __('Documents') }}</a></li>
                    @endunless
                    <li><a href="#section-parties" class="font-medium text-zinc-600 hover:text-teal-700 dark:text-zinc-400 dark:hover:text-teal-400">{{ __('Parties') }}</a></li>
                    <li><a href="#section-session" class="font-medium text-zinc-600 hover:text-teal-700 dark:text-zinc-400 dark:hover:text-teal-400">{{ __('Video session') }}</a></li>
                    @if ($isNotary || $canManageLifecycle || $isEnotaryPortalSigner || $isRequester)
                        <li><a href="#section-payment" class="font-medium text-zinc-600 hover:text-teal-700 dark:text-zinc-400 dark:hover:text-teal-400">{{ __('Payment') }}</a></li>
                    @endif
                    @if ($isNotary || $canManageLifecycle)
                        <li><a href="#section-register" class="font-medium text-zinc-600 hover:text-teal-700 dark:text-zinc-400 dark:hover:text-teal-400">{{ __('Register') }}</a></li>
                    @endif
                    <li><a href="#section-summary" class="font-medium text-zinc-600 hover:text-teal-700 dark:text-zinc-400 dark:hover:text-teal-400">{{ __('Summary') }}</a></li>
                </ul>
            </nav>

            @if ($this->currentWorkflowStep && $notaryRequest->status !== NotaryRequestStatus::Notarized)
                <div class="ui-panel border-sky-200/80 bg-sky-50/50 p-5 dark:border-sky-900/40 dark:bg-sky-950/20">
                    <div class="text-xs font-semibold uppercase tracking-wider text-sky-700 dark:text-sky-300">{{ __('Current step') }}</div>
                    <div class="mt-1 text-sm font-semibold text-sky-950 dark:text-sky-100">{{ $this->currentWorkflowStep['label'] }}</div>
                    <p class="mt-2 text-xs leading-relaxed text-sky-800 dark:text-sky-200">{{ $this->currentWorkflowStep['description'] }}</p>
                </div>
            @endif

            <div id="section-verification" class="ui-panel scroll-mt-6 p-5 sm:p-6">
                <flux:heading size="lg" class="!mb-4">{{ __('Verification') }}</flux:heading>
                <div class="mt-4 space-y-3">
                    @if ($canVerifyIdentity)
                        <flux:button variant="outline" type="button" wire:click="markIdentityVerified">{{ __('Mark identity verified') }}</flux:button>
                        <flux:error name="markIdentityVerified" />
                    @endif
                    @if ($canVerifyLocation)
                        <flux:button variant="outline" type="button" wire:click="markLocationVerified">{{ __('Mark location verified') }}</flux:button>
                        <flux:error name="markLocationVerified" />
                    @endif
                    @if (! $canVerifyIdentity && ! $canVerifyLocation)
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300">
                            {{ __('Verification actions appear when the request is submitted or when manual review is required.') }}
                        </div>
                    @endif
                </div>
            </div>

            @if ($isNotary)
                <div id="section-register" class="ui-panel scroll-mt-6 p-5 sm:p-6">
                    <flux:heading size="lg" class="!mb-2">{{ __('Notarial register') }}</flux:heading>
                    @if ($canCreateRegisterEntry)
                        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Create the official notarial register entry with all 9 required fields.') }}</p>
                        <div class="mt-4">
                            <flux:button variant="primary" :href="route('notary.register-entry', $notaryRequest)" wire:navigate>{{ __('Create register entry') }}</flux:button>
                        </div>
                    @else
                        <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300">
                            {{ __('Register entry creation becomes available after the attorney has signed the linked documents.') }}
                        </div>
                    @endif
                    @if ($notaryRequest->registerEntries->isNotEmpty())
                        <div class="mt-4 space-y-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                            @foreach ($notaryRequest->registerEntries as $entry)
                                <div class="rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                                    <div class="font-medium text-zinc-800 dark:text-zinc-200">{{ __('Entry') }} {{ str_pad($entry->entry_number, 3, '0', STR_PAD_LEFT) }} — {{ ucfirst(str_replace('_', ' ', $entry->notarial_act_type)) }}</div>
                                    <div class="text-zinc-500 dark:text-zinc-400">{{ $entry->document_title }}</div>
                                    <div class="text-xs text-zinc-400 dark:text-zinc-500">{{ $entry->notarized_at?->timezone('Asia/Manila')->format('M j, Y g:i:s A') }} (PHT)</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            @if ($isNotary || $canManageLifecycle || $isEnotaryPortalSigner || $isRequester)
                <div id="section-payment" class="ui-panel scroll-mt-6 p-5 sm:p-6">
                    <flux:heading size="lg" class="!mb-2">{{ __('Payment') }}</flux:heading>
                    @php
                        $latestRegisterEntry = $notaryRequest->registerEntries->sortByDesc('created_at')->first();
                        $paymentDue = $latestRegisterEntry ? (float) $latestRegisterEntry->fees : 0.0;
                        $currentPaymentExpired = $latestPayment instanceof Payment
                            && $latestPayment->status === PaymentStatus::Pending
                            && $latestPayment->expires_at?->isPast();
                        $displayPaymentStatus = $currentPaymentExpired ? PaymentStatus::Expired : ($latestPayment?->status ?? null);
                        $paymentBadgeColor = match ($displayPaymentStatus) {
                            PaymentStatus::Paid => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300',
                            PaymentStatus::Pending => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300',
                            PaymentStatus::Failed, PaymentStatus::Expired, PaymentStatus::Cancelled => 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-300',
                            default => 'border-zinc-200 bg-zinc-50 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300',
                        };
                    @endphp

                    @if ($latestRegisterEntry)
                        <div class="mt-3 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-900/40">
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ __('Amount due') }}</div>
                            <div class="mt-1 text-xl font-semibold text-zinc-900 dark:text-zinc-100">PHP {{ number_format($paymentDue, 2) }}</div>
                            <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Based on register entry :entry', ['entry' => str_pad((string) $latestRegisterEntry->entry_number, 3, '0', STR_PAD_LEFT)]) }}</div>
                        </div>
                    @else
                        <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300">
                            {{ __('Create a notarial register entry with fees before generating a GatewayHub payment.') }}
                        </div>
                    @endif

                    @if ($paymentRequired && ! $hasSettledPayment)
                        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-100">
                            {{ __('This request is blocked until a successful payment is recorded.') }}
                        </div>
                    @endif

                    @if ($latestPayment instanceof Payment)
                        <div class="mt-4 rounded-xl border px-4 py-4 {{ $paymentBadgeColor }}">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wider">{{ __('Latest payment') }}</div>
                                    <div class="mt-1 text-sm font-medium">{{ strtoupper($latestPayment->gateway) }} · {{ $latestPayment->reference }}</div>
                                </div>
                                <span class="rounded-full border border-current/15 px-2.5 py-1 text-xs font-semibold uppercase">{{ $displayPaymentStatus?->value ?? '-' }}</span>
                            </div>
                            <div class="mt-3 space-y-1 text-xs">
                                <div>{{ __('GatewayHub Payment ID') }}: <span class="font-mono">{{ $latestPayment->provider_payment_id ?? '-' }}</span></div>
                                <div>{{ __('Created') }}: {{ $latestPayment->created_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? '-' }} (PHT)</div>
                                <div>{{ __('Expires') }}: {{ $latestPayment->expires_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? '-' }}{{ $currentPaymentExpired ? ' '.__('(expired)') : '' }}</div>
                                @if ($latestPayment->paid_at)
                                    <div>{{ __('Paid') }}: {{ $latestPayment->paid_at->timezone('Asia/Manila')->format('M j, Y g:i A') }} (PHT)</div>
                                @endif
                            </div>

                            @if ($currentPaymentExpired)
                                <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-200">
                                    {{ __('This payment link has expired. Generate a new payment to continue.') }}
                                </div>
                                <div class="mt-4">
                                    <flux:button variant="outline" type="button" wire:click="refreshPaymentStatus({{ $latestPayment->id }})">{{ __('Re-check status') }}</flux:button>
                                </div>
                            @elseif ($latestPayment->status === PaymentStatus::Pending)
                                <div class="mt-4 grid gap-4 sm:grid-cols-[minmax(0,1fr)_280px]">
                                    <div class="space-y-3">
                                        @if ($latestPayment->checkout_url || $latestPayment->redirect_url)
                                            <a href="{{ $latestPayment->checkout_url ?? $latestPayment->redirect_url }}"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="inline-flex items-center justify-center rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-teal-500">
                                                {{ __('Open checkout') }}
                                            </a>
                                        @endif
                                        <div>
                                            <div class="text-xs font-semibold uppercase tracking-wider">{{ __('QR payload') }}</div>
                                            <textarea readonly rows="5" class="mt-2 w-full rounded-xl border border-current/15 bg-white/70 px-3 py-2 text-xs font-mono text-zinc-700 dark:bg-zinc-900/70 dark:text-zinc-100">{{ $latestPayment->qr_data }}</textarea>
                                        </div>
                                        <flux:button variant="outline" type="button" wire:click="refreshPaymentStatus({{ $latestPayment->id }})">{{ __('Verify status from GatewayHub') }}</flux:button>
                                        <flux:error name="refreshPaymentStatus" />
                                    </div>
                                    <div class="flex items-start justify-center">
                                        @if ($latestPayment->qr_data)
                                            <img
                                                src="https://api.qrserver.com/v1/create-qr-code/?size=280x280&data={{ rawurlencode($latestPayment->qr_data) }}"
                                                alt="{{ __('GatewayHub payment QR') }}"
                                                class="w-full max-w-[280px] rounded-2xl border border-current/15 bg-white p-3"
                                            >
                                        @endif
                                    </div>
                                </div>
                            @else
                                <div class="mt-4">
                                    <flux:button variant="outline" type="button" wire:click="refreshPaymentStatus({{ $latestPayment->id }})">{{ __('Re-check status') }}</flux:button>
                                </div>
                            @endif
                        </div>
                    @endif

                    @if ($latestRegisterEntry && $paymentDue > 0 && (! ($latestPayment instanceof Payment) || $latestPayment->status !== PaymentStatus::Paid))
                        <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                            <div class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $currentPaymentExpired ? __('Generate a new GatewayHub payment') : __('Create GatewayHub payment') }}</div>
                            @if ($enabledPaymentGateways !== [])
                                <div class="mt-3 space-y-3">
                                    <flux:field>
                                        <flux:label>{{ __('Gateway') }}</flux:label>
                                        <select wire:model="paymentGateway" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                                            @foreach ($enabledPaymentGateways as $gatewayOption)
                                                <option value="{{ $gatewayOption['code'] }}">{{ $gatewayOption['name'] }}</option>
                                            @endforeach
                                        </select>
                                        <flux:error name="paymentGateway" />
                                    </flux:field>
                                    <flux:button variant="primary" type="button" wire:click="createGatewayPayment">{{ $currentPaymentExpired ? __('Generate new payment') : __('Create payment') }}</flux:button>
                                    <flux:error name="createGatewayPayment" />
                                </div>
                            @else
                                <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                                    {{ __('GatewayHub is not fully configured or enabled gateways could not be loaded.') }}
                                </div>
                            @endif
                        </div>
                    @endif

                    @if ($paymentHistory->count() > 1)
                        <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                            <div class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Payment history') }}</div>
                            <div class="mt-3 space-y-2">
                                @foreach ($paymentHistory->slice(1) as $historicPayment)
                                    <div class="rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                                        <div class="font-medium text-zinc-800 dark:text-zinc-200">{{ strtoupper($historicPayment->gateway) }} · {{ $historicPayment->reference }}</div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ strtoupper($historicPayment->status->value) }} · {{ $historicPayment->created_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? '-' }} (PHT)</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($latestEInvoice)
                        @php
                            $invoiceBadgeColor = match ($latestEInvoice->status) {
                                EInvoiceStatus::Accepted => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300',
                                EInvoiceStatus::Rejected, EInvoiceStatus::NeedsCorrection => 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-300',
                                EInvoiceStatus::Queued, EInvoiceStatus::Submitted, EInvoiceStatus::Processing => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/40 dark:bg-sky-950/30 dark:text-sky-300',
                                default => 'border-zinc-200 bg-zinc-50 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300',
                            };
                        @endphp

                        <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                            <div class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('E-invoice') }}</div>
                            <div class="mt-3 rounded-xl border px-4 py-4 {{ $invoiceBadgeColor }}">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="text-xs font-semibold uppercase tracking-wider">{{ __('Latest invoice') }}</div>
                                        <div class="mt-1 text-sm font-medium">{{ $latestEInvoice->invoice_number }}</div>
                                    </div>
                                    <span class="rounded-full border border-current/15 px-2.5 py-1 text-xs font-semibold uppercase">{{ $latestEInvoice->status->value }}</span>
                                </div>
                                <div class="mt-3 space-y-1 text-xs">
                                    <div>{{ __('Amount') }}: PHP {{ number_format((float) $latestEInvoice->total_amount, 2) }}</div>
                                    <div>{{ __('Issue date') }}: {{ $latestEInvoice->issue_date?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? '-' }} (PHT)</div>
                                    <div>{{ __('Document') }}: {{ $latestEInvoice->document_title ?? '-' }}</div>
                                    <div>{{ __('O.R. number') }}: {{ $latestEInvoice->official_receipt_number ?? '-' }}</div>
                                </div>

                                @include('livewire.notary-requests.show.partials.e-invoice-status-actions')
                            </div>
                        </div>
                    @endif
                </div>
            @endif

                @if ($isNotary)
                <div id="section-review" class="ui-panel scroll-mt-6 p-5 sm:p-6">
                    <flux:heading size="lg" class="!mb-4">{{ __('Attorney review') }}</flux:heading>
                    @if ($canReviewNotary)
                        <div class="mt-4 space-y-4">
                            <flux:field>
                                <flux:label>{{ __('Review summary') }}</flux:label>
                                <flux:textarea wire:model="approvalSummary" rows="4" placeholder="{{ __('Observed signer awareness, reviewed identity, and validated voluntary signing.') }}" />
                            </flux:field>
                            <flux:button variant="primary" type="button" wire:click="approveRequest">{{ __('Complete attorney review') }}</flux:button>
                            <flux:error name="approveRequest" />

                            <div class="border-t border-zinc-200 pt-4 dark:border-zinc-700">
                                <flux:field>
                                    <flux:label>{{ __('Rejection reason') }}</flux:label>
                                    <flux:textarea wire:model="rejectionReason" rows="4" placeholder="{{ __('Explain why this request cannot proceed.') }}" />
                                    <flux:error name="rejectionReason" />
                                </flux:field>
                                <div class="mt-3">
                                    <flux:button variant="outline" type="button" wire:click="rejectRequest">{{ __('Reject request') }}</flux:button>
                                    <flux:error name="rejectRequest" />
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300">
                            {{ __('Attorney review becomes available after the video session is complete, the attorney has signed, the register entry exists, and the client payment has been completed.') }}
                        </div>
                    @endif
                </div>
                @endif
        </aside>
    </div>

    @include('livewire.notary-requests.show.partials.notary-status-poll-config')
</x-admin.page>
