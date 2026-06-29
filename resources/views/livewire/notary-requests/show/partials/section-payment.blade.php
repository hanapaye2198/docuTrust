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

<div
    id="section-payment"
    class="ui-panel scroll-mt-24 p-5 sm:p-6"
    @if ($paymentRequired && ! $hasSettledPayment) wire:poll.5s="refreshPaymentUpdates" @endif
>
    <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
        {{ $canPayNotaryFee && ! $canCreatePayment ? __('Pay notarial fee') : __('Notarial fee payment') }}
    </h2>
    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
        @if ($canCreatePayment)
            {{ __('Set the recipient and payment method here. The client will only receive the checkout prepared by the attorney.') }}
        @elseif ($canPayNotaryFee)
            {{ __('Use the attorney-generated checkout below. The payment method is already selected for this case.') }}
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

    @if ($canCreatePayment && $paymentDue > 0)
        <form method="POST" action="{{ route('notary.requests.payment-link', $notaryRequest) }}" class="mt-4 rounded-2xl border border-teal-200 bg-teal-50/40 p-4 dark:border-teal-900/40 dark:bg-teal-950/20 sm:p-5">
            @csrf

            <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="text-sm font-semibold text-teal-950 dark:text-teal-100">{{ __('Payment setup wizard') }}</div>
                    <p class="mt-1 text-xs text-teal-800/90 dark:text-teal-200/90">{{ __('Complete these three steps, then send one prepared checkout link to the client.') }}</p>
                </div>
                <span class="inline-flex w-fit rounded-full border border-teal-200 bg-white px-3 py-1 text-xs font-semibold text-teal-700 dark:border-teal-800 dark:bg-teal-950 dark:text-teal-200">
                    {{ __('Attorney chooses gateway') }}
                </span>
            </div>

            <div class="grid gap-3 lg:grid-cols-3">
                <div class="rounded-xl border border-white/80 bg-white px-4 py-3 shadow-sm dark:border-teal-900/30 dark:bg-zinc-950/50">
                    <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-teal-700 dark:text-teal-300">
                        <span class="grid size-6 place-items-center rounded-full bg-teal-600 text-white">1</span>
                        {{ __('Fee') }}
                    </div>
                    <div class="mt-3 text-2xl font-semibold text-zinc-950 dark:text-zinc-100">PHP {{ number_format($paymentDue, 2) }}</div>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Based on the saved settlement fee.') }}</p>
                </div>

                <div class="rounded-xl border border-white/80 bg-white px-4 py-3 shadow-sm dark:border-teal-900/30 dark:bg-zinc-950/50">
                    <label for="payment-recipient-email" class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-teal-700 dark:text-teal-300">
                        <span class="grid size-6 place-items-center rounded-full bg-teal-600 text-white">2</span>
                        {{ __('Recipient') }}
                    </label>
                    <input
                        id="payment-recipient-email"
                        name="recipient_email"
                        type="email"
                        wire:model.live="paymentRecipientEmail"
                        value="{{ old('recipient_email', $paymentRecipientEmail) }}"
                        placeholder="client@example.com"
                        class="mt-3 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/30 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                    >
                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                        @if ($notaryRequest->requester?->email)
                            {{ __('Requester on file: :email', ['email' => $notaryRequest->requester->email]) }}
                        @else
                            {{ __('Use the email address that should receive the payment link.') }}
                        @endif
                    </p>
                    @error('recipient_email')
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="rounded-xl border border-white/80 bg-white px-4 py-3 shadow-sm dark:border-teal-900/30 dark:bg-zinc-950/50">
                    <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-teal-700 dark:text-teal-300">
                        <span class="grid size-6 place-items-center rounded-full bg-teal-600 text-white">3</span>
                        {{ __('Method') }}
                    </div>
                    @if ($enabledPaymentGateways !== [])
                        <select name="payment_gateway" class="mt-3 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/30 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                            @foreach ($enabledPaymentGateways as $gatewayOption)
                                <option value="{{ $gatewayOption['code'] }}" @selected($paymentGateway === $gatewayOption['code'])>{{ $gatewayOption['name'] }}</option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('The client will not be asked to choose another method.') }}</p>
                    @else
                        <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                            {{ __('Online payment is not configured. Set GATEWAYHUB_API_KEY or enable GATEWAYHUB_DEMO_MODE=true.') }}
                        </div>
                    @endif
                    @error('payment_gateway')
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ __('Generating a new link creates the checkout using the selected gateway and emails it to the recipient.') }}</p>
                <button
                    type="submit"
                    @disabled($enabledPaymentGateways === [])
                    class="inline-flex items-center justify-center rounded-lg bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-teal-500 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{ $currentPaymentExpired ? __('Generate and send fresh link') : __('Generate and send payment link') }}
                </button>
            </div>
        </form>
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

    @if ($canPayNotaryFee && $paymentDue > 0 && ! ($latestPayment instanceof Payment))
        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
            {{ __('Waiting for the attorney to generate your payment link. The attorney will choose the payment method for this case.') }}
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
