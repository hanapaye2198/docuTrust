@php
    use App\Enums\EInvoiceStatus;
    use App\Enums\PaymentStatus;
    use App\Models\Payment;

    $paymentDue = (float) $settlementDueAmount;
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

<div id="section-payment" class="ui-panel scroll-mt-24 p-5 sm:p-6">
    <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
        {{ $canPayNotaryFee && ! $canCreatePayment ? __('Pay notarial fee') : __('Notarial fee payment') }}
    </h2>
    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
        @if ($canCreatePayment)
            {{ __('Email the client a no-login payment page after you save the notarial fee. The client will choose a payment method there before you complete the register entry.') }}
        @elseif ($canPayNotaryFee)
            {{ __('Choose a payment method to create your checkout link. Payment must be completed before notarization can finish.') }}
        @else
            {{ __('Payment status for this case.') }}
        @endif
    </p>

    @if ($paymentDue > 0)
        <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-900/40">
            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ __('Amount due') }}</div>
            <div class="mt-1 text-xl font-semibold text-zinc-900 dark:text-zinc-100">PHP {{ number_format($paymentDue, 2) }}</div>
            @if ($notaryRequest->registerEntries->isNotEmpty())
                @php $latestRegisterEntry = $notaryRequest->registerEntries->sortByDesc('created_at')->first(); @endphp
                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Based on register entry :entry', ['entry' => str_pad((string) $latestRegisterEntry->entry_number, 3, '0', STR_PAD_LEFT)]) }}</div>
            @elseif ($attorneyRegistryDraft)
                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Based on saved notarial fee') }}</div>
            @endif
        </div>
    @elseif ($canCreatePayment)
        <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300">
            {{ __('Save the notarial fee on Settlement before creating a payment link.') }}
        </div>
    @endif

    @if ($paymentRequired && ! $hasSettledPayment)
        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-100">
            {{ __('This case is waiting for a successful payment before the register entry and digital notarization can finish.') }}
        </div>
    @elseif ($hasSettledPayment)
        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:text-emerald-100">
            {{ __('Payment received. The attorney can continue with the register entry and final notarization steps.') }}
        </div>
    @endif

    @if ($latestPayment instanceof Payment)
        <div class="mt-4 rounded-xl border px-4 py-4 {{ $paymentBadgeColor }}">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wider">{{ __('Payment status') }}</div>
                    <div class="mt-1 text-sm font-medium">{{ strtoupper($latestPayment->gateway) }} · {{ $latestPayment->reference }}</div>
                </div>
                <span class="rounded-full border border-current/15 px-2.5 py-1 text-xs font-semibold uppercase">{{ $displayPaymentStatus?->value ?? '-' }}</span>
            </div>
            @if ($canCreatePayment)
                <div class="mt-3 space-y-1 text-xs">
                    <div>{{ __('Created') }}: {{ $latestPayment->created_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? '-' }} (PHT)</div>
                    <div>{{ __('Expires') }}: {{ $latestPayment->expires_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? '-' }}{{ $currentPaymentExpired ? ' '.__('(expired)') : '' }}</div>
                    @if ($latestPayment->paid_at)
                        <div>{{ __('Paid') }}: {{ $latestPayment->paid_at->timezone('Asia/Manila')->format('M j, Y g:i A') }} (PHT)</div>
                    @endif
                </div>
            @endif

            @if ($currentPaymentExpired)
                <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-200">
                    {{ __('This payment link has expired.') }}
                    @if ($canCreatePayment)
                        {{ __('You can email a fresh payment link to the client or generate one below.') }}
                    @else
                        {{ __('Ask your attorney to generate a new payment link.') }}
                    @endif
                </div>
                @if ($canCreatePayment)
                    <div class="mt-4">
                        <button
                            type="button"
                            wire:click="resendPaymentLinkToClient"
                            wire:loading.attr="disabled"
                            wire:target="resendPaymentLinkToClient"
                            class="inline-flex items-center justify-center rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
                        >
                            {{ __('Email fresh payment link to client') }}
                        </button>
                        @error('resendPaymentLinkToClient')
                            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                @endif
            @elseif ($latestPayment->status === PaymentStatus::Pending)
                <div class="mt-4 grid gap-4 sm:grid-cols-[minmax(0,1fr)_280px]">
                    <div class="space-y-3">
                        @if ($latestPayment->checkout_url || $latestPayment->redirect_url)
                            <a href="{{ $latestPayment->checkout_url ?? $latestPayment->redirect_url }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="inline-flex items-center justify-center rounded-lg bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-teal-500">
                                {{ __('Open checkout') }}
                            </a>
                        @endif
                        @if ($canManageLifecycle && ($latestPayment->checkout_url || $latestPayment->redirect_url))
                            <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-900/40 dark:bg-sky-950/30 dark:text-sky-100">
                                <div class="font-medium">{{ __('Temporary testing link') }}</div>
                                <p class="mt-1 text-xs">{{ __('Direct checkout access for admin-side testing only.') }}</p>
                                <a href="{{ $latestPayment->checkout_url ?? $latestPayment->redirect_url }}"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="mt-3 inline-flex items-center justify-center rounded-lg border border-sky-300 bg-white px-3 py-2 text-sm font-medium text-sky-700 transition hover:bg-sky-100 dark:border-sky-700 dark:bg-sky-950 dark:text-sky-200 dark:hover:bg-sky-900">
                                    {{ __('Open temporary payment link') }}
                                </a>
                                <div class="mt-2 break-all text-[11px] text-sky-800/80 dark:text-sky-200/80">
                                    {{ $latestPayment->checkout_url ?? $latestPayment->redirect_url }}
                                </div>
                            </div>
                        @endif
                        @if (($canManageLifecycle || $canCreatePayment) && $paymentEmailUrl)
                            <div class="rounded-xl border border-violet-200 bg-violet-50 px-4 py-3 text-sm text-violet-900 dark:border-violet-900/40 dark:bg-violet-950/30 dark:text-violet-100">
                                <div class="font-medium">{{ __('Payment email link preview') }}</div>
                                <p class="mt-1 text-xs">{{ __('This is the same no-login payment page sent by email to the client.') }}</p>
                                <a href="{{ $paymentEmailPreviewUrl }}"
                                   class="mt-3 inline-flex items-center justify-center rounded-lg border border-violet-300 bg-white px-3 py-2 text-sm font-medium text-violet-700 transition hover:bg-violet-100 dark:border-violet-700 dark:bg-violet-950 dark:text-violet-200 dark:hover:bg-violet-900">
                                    {{ __('Open email payment page') }}
                                </a>
                                <div class="mt-2 break-all text-[11px] text-violet-800/80 dark:text-violet-200/80">
                                    {{ $paymentEmailUrl }}
                                </div>
                            </div>
                        @endif
                        @if ($canCreatePayment)
                            <button
                                type="button"
                                wire:click="refreshPaymentStatus({{ $latestPayment->id }})"
                                class="inline-flex items-center justify-center rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
                            >
                                {{ __('Verify payment status') }}
                            </button>
                            <button
                                type="button"
                                wire:click="resendPaymentLinkToClient"
                                wire:loading.attr="disabled"
                                wire:target="resendPaymentLinkToClient"
                                class="inline-flex items-center justify-center rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
                            >
                                {{ __('Email link to client') }}
                            </button>
                            @error('resendPaymentLinkToClient')
                                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        @elseif ($canPayNotaryFee)
                            <button
                                type="button"
                                wire:click="refreshPaymentStatus({{ $latestPayment->id }})"
                                class="inline-flex items-center justify-center rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
                            >
                                {{ __('I already paid — check status') }}
                            </button>
                        @endif
                        @error('refreshPaymentStatus')
                            <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="flex items-start justify-center">
                        @if ($latestPayment->qr_data)
                            <img
                                src="https://api.qrserver.com/v1/create-qr-code/?size=280x280&data={{ rawurlencode($latestPayment->qr_data) }}"
                                alt="{{ __('Payment QR code') }}"
                                class="w-full max-w-[280px] rounded-2xl border border-current/15 bg-white p-3"
                            >
                        @endif
                    </div>
                </div>
            @elseif ($canCreatePayment || $canPayNotaryFee)
                <div class="mt-4">
                    <button
                        type="button"
                        wire:click="refreshPaymentStatus({{ $latestPayment->id }})"
                        class="inline-flex items-center justify-center rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
                    >
                        {{ __('Re-check status') }}
                    </button>
                </div>
            @endif
        </div>
    @endif

    @if (($canCreatePayment || $canPayNotaryFee) && $paymentDue > 0 && (! ($latestPayment instanceof Payment) || $latestPayment->status !== PaymentStatus::Paid))
        <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
            <div class="text-sm font-medium text-zinc-800 dark:text-zinc-200">
                {{ $canPayNotaryFee ? __('Choose a payment method') : ($currentPaymentExpired ? __('Generate a new payment link') : __('Send client to payment page')) }}
            </div>
            @if ($enabledPaymentGateways !== [])
                @if ($canPayNotaryFee)
                <div class="mt-3 space-y-3">
                    <div class="space-y-1.5">
                        <label for="payment-gateway" class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('Payment method') }}</label>
                        <select id="payment-gateway" wire:model="paymentGateway" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/30 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                            @foreach ($enabledPaymentGateways as $gatewayOption)
                                <option value="{{ $gatewayOption['code'] }}">{{ $gatewayOption['name'] }}</option>
                            @endforeach
                        </select>
                        @error('paymentGateway')
                            <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    @if ($canPayNotaryFee)
                        <button
                            type="button"
                            wire:click="createGatewayPaymentForClient"
                            wire:loading.attr="disabled"
                            wire:target="createGatewayPaymentForClient"
                            class="inline-flex items-center justify-center rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-teal-500 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <span wire:loading.remove wire:target="createGatewayPaymentForClient">
                                {{ __('Continue to payment') }}
                            </span>
                            <span wire:loading wire:target="createGatewayPaymentForClient">
                                {{ __('Processing...') }}
                            </span>
                        </button>
                    @else
                        <form method="POST" action="{{ route('notary.requests.payment-link', $notaryRequest) }}">
                            @csrf
                            <input type="hidden" name="payment_gateway" value="{{ $paymentGateway }}">
                            <button
                                type="submit"
                                class="inline-flex items-center justify-center rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-teal-500 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {{ $currentPaymentExpired ? __('Generate new payment link') : __('Email payment page to client') }}
                            </button>
                        </form>
                    @endif
                    @error('createGatewayPayment')
                        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                    @error('createGatewayPaymentForClient')
                        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                @else
                <form method="POST" action="{{ route('notary.requests.payment-link', $notaryRequest) }}" class="mt-3 space-y-3">
                    @csrf
                    <div class="space-y-1.5">
                        <label for="payment-gateway" class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('Payment method') }}</label>
                        <select id="payment-gateway" name="payment_gateway" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/30 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                            @foreach ($enabledPaymentGateways as $gatewayOption)
                                <option value="{{ $gatewayOption['code'] }}" @selected($paymentGateway === $gatewayOption['code'])>{{ $gatewayOption['name'] }}</option>
                            @endforeach
                        </select>
                        @error('payment_gateway')
                            <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-teal-500 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{ $currentPaymentExpired ? __('Generate new payment link') : __('Email payment page to client') }}
                    </button>
                </form>
                @endif
            @else
                <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                    {{ __('Online payment is not configured. Contact your administrator.') }}
                </div>
            @endif
        </div>
    @endif

    @if ($canCreatePayment && $paymentHistory->count() > 1)
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

    @if ($latestEInvoice && ($canCreatePayment || $canManageLifecycle))
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
