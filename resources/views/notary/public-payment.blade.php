<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Pay notarial fee') }} | {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-100 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100">
    @php
        $currentPaymentExpired = $latestPayment instanceof \App\Models\Payment
            && $latestPayment->status === \App\Enums\PaymentStatus::Pending
            && $latestPayment->expires_at?->isPast();
        $displayPaymentStatus = $currentPaymentExpired ? \App\Enums\PaymentStatus::Expired : ($latestPayment?->status ?? null);
        $checkoutUrl = $latestPayment?->checkout_url ?? $latestPayment?->redirect_url;
        $qrSource = $latestPayment?->qr_data ?: $checkoutUrl;
    @endphp

    <main class="mx-auto flex min-h-screen w-full max-w-3xl items-center px-4 py-10 sm:px-6">
        <section class="w-full rounded-[28px] border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 sm:p-8">
            <div class="space-y-2">
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-teal-600 dark:text-teal-400">{{ config('app.name') }}</p>
                <h1 class="text-3xl font-semibold tracking-tight">{{ __('Pay notarial fee') }}</h1>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('This secure payment page lets you choose your payment method and continue the notarization process without signing in.') }}
                </p>
            </div>

            <div class="mt-6 grid gap-4 rounded-2xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-800 dark:bg-zinc-950/50 sm:grid-cols-2">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Case') }}</div>
                    <div class="mt-1 text-lg font-semibold">{{ $notaryRequest->title }}</div>
                    <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Notary') }}: {{ $notaryRequest->notary?->name ?? __('Notary Public') }}</div>
                </div>
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Amount due') }}</div>
                    <div class="mt-1 text-3xl font-semibold">PHP {{ number_format((float) $settlementDueAmount, 2) }}</div>
                    @if ($latestPayment instanceof \App\Models\Payment)
                        <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Reference') }}: {{ $latestPayment->reference }}</div>
                    @endif
                </div>
            </div>

            @if (session('status'))
                <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-200">
                    {{ session('status') }}
                </div>
            @endif

            @if (! $paymentRequired)
                <div class="mt-6 rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-700 dark:border-zinc-800 dark:bg-zinc-950/40 dark:text-zinc-300">
                    {{ __('No payment is currently required for this notarization.') }}
                </div>
            @elseif ($hasSettledPayment)
                <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-200">
                    {{ __('Payment has already been received for this case. Your attorney can continue with the remaining notarization steps.') }}
                </div>
            @else
                @if ($latestPayment instanceof \App\Models\Payment)
                    <div class="mt-6 rounded-2xl border border-zinc-200 px-4 py-4 dark:border-zinc-800">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Latest payment') }}</div>
                                <div class="mt-1 text-sm font-semibold">{{ strtoupper((string) $latestPayment->gateway) }} · {{ $latestPayment->reference }}</div>
                            </div>
                            <span class="rounded-full border border-zinc-300 px-3 py-1 text-xs font-semibold uppercase text-zinc-700 dark:border-zinc-700 dark:text-zinc-200">
                                {{ $displayPaymentStatus?->value ?? '-' }}
                            </span>
                        </div>

                        @if (($checkoutUrl || $qrSource) && ! $currentPaymentExpired && $latestPayment->status === \App\Enums\PaymentStatus::Pending)
                            <div class="mt-4 grid gap-4 sm:grid-cols-[minmax(0,1fr)_240px] sm:items-start">
                                <div class="space-y-3">
                                    <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-900/40 dark:bg-sky-950/30 dark:text-sky-100">
                                        {{ __('Your payment link is ready. Scan the QR code or open checkout to continue.') }}
                                    </div>
                                    @if ($checkoutUrl)
                                        <a
                                            href="{{ $checkoutUrl }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center justify-center rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-teal-500"
                                        >
                                            {{ __('Open checkout') }}
                                        </a>
                                    @endif
                                    @if ($latestPayment->expires_at)
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ __('Expires') }}: {{ $latestPayment->expires_at->timezone('Asia/Manila')->format('M j, Y g:i A') }} (PHT)
                                        </div>
                                    @endif
                                </div>
                                @if ($qrSource)
                                    <div class="flex justify-center sm:justify-end">
                                        <img
                                            src="https://api.qrserver.com/v1/create-qr-code/?size=240x240&data={{ rawurlencode($qrSource) }}"
                                            alt="{{ __('Payment QR code') }}"
                                            class="w-full max-w-[240px] rounded-2xl border border-zinc-200 bg-white p-3 dark:border-zinc-700"
                                        >
                                    </div>
                                @else
                                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                                        {{ __('QR code is unavailable for this payment method. Use the checkout button instead.') }}
                                    </div>
                                @endif
                            </div>
                        @elseif ($currentPaymentExpired)
                            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                                {{ __('Your previous payment link expired. Choose a payment method below to generate a fresh one.') }}
                            </div>
                        @endif
                    </div>
                @endif

                <form method="POST" action="{{ $postUrl }}" class="mt-6 space-y-4">
                    @csrf
                    <div class="space-y-2">
                        <label for="payment-gateway" class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Choose payment method') }}</label>
                        <select
                            id="payment-gateway"
                            name="payment_gateway"
                            class="w-full rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm text-zinc-900 shadow-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/30 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                        >
                            @foreach ($enabledGateways as $gateway)
                                <option value="{{ $gateway['code'] }}">{{ $gateway['name'] }}</option>
                            @endforeach
                        </select>
                        @error('payment_gateway')
                            <p class="text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    @if ($enabledGateways === [])
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                            {{ __('Online payment is not configured right now. Please contact your attorney.') }}
                        </div>
                    @else
                        <button
                            type="submit"
                            class="inline-flex w-full items-center justify-center rounded-xl bg-zinc-900 px-5 py-3 text-base font-semibold text-white transition hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200"
                        >
                            {{ __('Continue to payment') }}
                        </button>
                    @endif
                </form>
            @endif
        </section>
    </main>
</body>
</html>
