@php
    $primaryAction = $primaryAction ?? null;
    $progress = $this->notaryCaseProgress;
@endphp

@if ($primaryAction && ! $this->suppressPrimaryActionProminence)
    <div class="ui-panel border-sky-300/80 bg-sky-50/80 p-5 sm:p-6 dark:border-sky-800/60 dark:bg-sky-950/30">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0 flex-1 space-y-2">
                <div class="text-xs font-semibold uppercase tracking-wider text-sky-800 dark:text-sky-300">
                    {{ __('Step :current of :total', ['current' => $progress['step_number'], 'total' => $progress['total']]) }}
                    @if ($progress['current_label'] !== '')
                        · {{ $progress['current_label'] }}
                    @endif
                </div>
                <flux:heading size="lg" class="!text-sky-950 dark:!text-sky-50">
                    {{ __('Your next step') }}: {{ $primaryAction['label'] }}
                </flux:heading>
                <p class="text-base leading-relaxed text-sky-900/90 dark:text-sky-100/90">
                    {{ $primaryAction['description'] }}
                </p>

                @if ($this->waitingOnClient)
                    <div class="rounded-xl border-2 border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100">
                        <div class="font-semibold">{{ __('Waiting for your client') }}</div>
                        <p class="mt-1">
                            @if ($paymentRequired && ! $hasSettledPayment && $settlementDueAmount > 0)
                                {{ __('Your client still needs to pay PHP :amount. You can resend the payment link from Fees & register.', ['amount' => number_format((float) $settlementDueAmount, 2)]) }}
                            @else
                                {{ __('Your client needs to complete their part before you can continue.') }}
                            @endif
                        </p>
                    </div>
                @endif

                @if ($isNotary && $videoWaitingParties !== [] && $activeTab !== 'session')
                    <div
                        data-video-waiting-banner
                        class="rounded-xl border-2 border-sky-400 bg-sky-100 px-4 py-3 text-sm text-sky-950 dark:border-sky-600 dark:bg-sky-950/50 dark:text-sky-50"
                    >
                        <div class="font-semibold">
                            {{ trans_choice(':count signer is|:count signers are', count($videoWaitingParties), ['count' => count($videoWaitingParties)]) }}
                            {{ __('waiting in the video room') }}
                        </div>
                        <p class="mt-1">
                            <span data-video-waiting-names>{{ collect($videoWaitingParties)->pluck('full_name')->join(', ') }}</span>
                        </p>
                        <p class="mt-1 text-sky-900/90 dark:text-sky-100/90">
                            {{ __('Join the video workspace to start identity verification.') }}
                        </p>
                        <span data-video-waiting-count class="sr-only">{{ count($videoWaitingParties) }}</span>
                    </div>
                @endif

                @if (($primaryAction['inline_form'] ?? null) === 'settlement_fee' && $isNotary)
                    @include('livewire.notary-requests.show.partials.do-this-now-inline-fee')
                @endif
            </div>

            <div class="flex w-full shrink-0 flex-col gap-2 sm:min-w-[220px] lg:max-w-xs">
                @if (($primaryAction['inline_form'] ?? null) !== 'settlement_fee')
                    @include('livewire.notary-requests.show.partials.primary-action-button', [
                        'action' => $primaryAction,
                        'size' => 'base',
                        'class' => 'w-full min-h-11 text-base',
                    ])
                @else
                    <flux:button
                        variant="outline"
                        type="button"
                        wire:click="openSettlementSection('section-settlement-fee')"
                        class="w-full min-h-11 text-base"
                    >
                        {{ __('More payment options') }}
                    </flux:button>
                @endif

                @if ($isNotary && $hasSettlementFeeConfigured && $paymentEmailPreviewUrl && ($paymentRequired && ! $hasSettledPayment))
                    <a
                        href="{{ $paymentEmailPreviewUrl }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-zinc-300 bg-white px-4 py-2.5 text-sm font-medium text-zinc-800 transition hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
                    >
                        {{ __('See what the client will pay') }}
                    </a>
                @endif
            </div>
        </div>
    </div>
@endif
