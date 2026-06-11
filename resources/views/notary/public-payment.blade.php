<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Pay notarial fee') }} | {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(20,184,166,0.12),_transparent_32%),linear-gradient(180deg,_#f7f7f5_0%,_#f1f1ee_100%)] text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100">
    @php
        $currentPaymentExpired = $latestPayment instanceof \App\Models\Payment
            && $latestPayment->status === \App\Enums\PaymentStatus::Pending
            && $latestPayment->expires_at?->isPast();
        $displayPaymentStatus = $currentPaymentExpired ? \App\Enums\PaymentStatus::Expired : ($latestPayment?->status ?? null);
        $checkoutUrl = $latestPayment?->checkout_url ?? $latestPayment?->redirect_url;
        $qrSource = $latestPayment?->qr_data ?: $checkoutUrl;

        $hasActiveLink = ($checkoutUrl || $qrSource)
            && ! $currentPaymentExpired
            && $latestPayment instanceof \App\Models\Payment
            && $latestPayment->status === \App\Enums\PaymentStatus::Pending;

        // Progress stage: 1 = choose method, 2 = pay via active link, 3 = settled
        $stage = $hasSettledPayment ? 3 : ($hasActiveLink ? 2 : 1);

        $statusValue = $displayPaymentStatus?->value ?? null;
        $statusTone = match ($statusValue) {
            'paid', 'settled', 'succeeded', 'success' => 'emerald',
            'pending', 'processing' => 'amber',
            'expired', 'failed', 'cancelled', 'canceled' => 'rose',
            default => 'zinc',
        };

        $avatarPalette = [
            ['teal', '14b8a6'], ['sky', '0ea5e9'], ['violet', '8b5cf6'],
            ['amber', 'f59e0b'], ['rose', 'f43f5e'], ['indigo', '6366f1'],
        ];
    @endphp

    <main class="mx-auto flex min-h-screen w-full max-w-4xl flex-col justify-center px-4 py-10 sm:px-6">
        <section class="w-full overflow-hidden rounded-[32px] border border-white/70 bg-white/90 shadow-[0_24px_80px_-32px_rgba(15,23,42,0.35)] backdrop-blur dark:border-zinc-800 dark:bg-zinc-900">
            {{-- Header --}}
            <div class="flex flex-col gap-6 border-b border-zinc-200/80 p-6 dark:border-zinc-800 sm:flex-row sm:items-start sm:justify-between sm:p-8">
                <div class="max-w-2xl space-y-3">
                    <p class="inline-flex items-center gap-2 text-sm font-semibold uppercase tracking-[0.28em] text-teal-600 dark:text-teal-400">
                        <span class="grid h-7 w-7 place-items-center rounded-lg bg-teal-600 text-white shadow-sm">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 4 5v6c0 5 3.4 8.4 8 10 4.6-1.6 8-5 8-10V5l-8-3Z"/><path d="m9 12 2 2 4-4"/></svg>
                        </span>
                        {{ config('app.name') }}
                    </p>
                    <h1 class="text-3xl font-semibold tracking-tight text-zinc-950 dark:text-white sm:text-4xl">{{ __('Pay notarial fee') }}</h1>
                    <p class="max-w-xl text-base leading-7 text-zinc-600 dark:text-zinc-400">
                        {{ __('This secure payment page lets you choose your payment method and continue the notarization process without signing in.') }}
                    </p>
                </div>
                <div class="shrink-0 rounded-2xl border border-teal-100 bg-teal-50/80 px-4 py-3 text-sm text-teal-900 dark:border-teal-900/40 dark:bg-teal-950/30 dark:text-teal-100">
                    <div class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-[0.2em] text-teal-700/80 dark:text-teal-300">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        {{ __('Secure checkout') }}
                    </div>
                    <div class="mt-1 font-medium">{{ __('Protected payment link for this notarization case.') }}</div>
                </div>
            </div>

            {{-- Progress steps --}}
            @php
                $steps = [
                    1 => __('Choose method'),
                    2 => __('Complete payment'),
                    3 => __('Confirmed'),
                ];
            @endphp
            <div class="border-b border-zinc-200/80 px-6 py-5 dark:border-zinc-800 sm:px-8">
                <ol class="flex items-center gap-2 sm:gap-4">
                    @foreach ($steps as $index => $label)
                        @php
                            $isDone = $index < $stage;
                            $isActive = $index === $stage;
                        @endphp
                        <li class="flex flex-1 items-center gap-2 sm:gap-3">
                            <span @class([
                                'grid h-8 w-8 shrink-0 place-items-center rounded-full text-sm font-semibold transition',
                                'bg-teal-600 text-white shadow-sm' => $isDone || $isActive,
                                'bg-zinc-100 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500' => ! $isDone && ! $isActive,
                                'ring-4 ring-teal-500/15' => $isActive,
                            ])>
                                @if ($isDone)
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                @else
                                    {{ $index }}
                                @endif
                            </span>
                            <span @class([
                                'hidden text-sm font-medium sm:block',
                                'text-zinc-900 dark:text-white' => $isActive,
                                'text-zinc-500 dark:text-zinc-400' => ! $isActive,
                            ])>{{ $label }}</span>
                            @unless ($loop->last)
                                <span @class([
                                    'h-px flex-1 transition',
                                    'bg-teal-500/60' => $isDone,
                                    'bg-zinc-200 dark:bg-zinc-800' => ! $isDone,
                                ])></span>
                            @endunless
                        </li>
                    @endforeach
                </ol>
            </div>

            <div class="p-6 sm:p-8">
                {{-- Case + amount summary --}}
                <div class="grid gap-4 rounded-[28px] border border-zinc-200/80 bg-[linear-gradient(135deg,_rgba(255,255,255,0.95),_rgba(244,244,245,0.92))] p-5 shadow-inner dark:border-zinc-800 dark:bg-zinc-950/50 sm:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)] sm:p-6">
                    <div class="space-y-4">
                        <div>
                            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-zinc-500 dark:text-zinc-400">{{ __('Case') }}</div>
                            <div class="mt-2 text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white sm:text-3xl">{{ $notaryRequest->title }}</div>
                        </div>
                        <div class="flex flex-wrap gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-white px-3 py-1.5 dark:border-zinc-700 dark:bg-zinc-900">
                                <svg class="h-3.5 w-3.5 text-teal-600 dark:text-teal-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                {{ $notaryRequest->notary?->name ?? __('Notary Public') }}
                            </span>
                            @if ($latestPayment instanceof \App\Models\Payment)
                                <button type="button" data-copy="{{ $latestPayment->reference }}"
                                    class="group inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-white px-3 py-1.5 transition hover:border-teal-300 hover:text-teal-700 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-teal-700 dark:hover:text-teal-300">
                                    <span class="text-zinc-500 dark:text-zinc-500">{{ __('Reference') }}:</span>
                                    <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $latestPayment->reference }}</span>
                                    <svg data-copy-icon class="h-3.5 w-3.5 opacity-50 transition group-hover:opacity-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                                </button>
                            @endif
                        </div>
                    </div>
                    <div class="rounded-3xl bg-zinc-950 px-5 py-5 text-white shadow-lg dark:bg-black/40 dark:ring-1 dark:ring-white/10">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-teal-300/90">{{ __('Amount due') }}</div>
                        <div class="mt-3 flex items-baseline gap-1.5">
                            <span class="text-sm font-medium text-zinc-400">PHP</span>
                            <span class="text-4xl font-semibold tracking-tight">{{ number_format((float) $settlementDueAmount, 2) }}</span>
                        </div>
                        <div class="mt-3 text-sm leading-6 text-zinc-300">
                            {{ __('Use the active payment link below to complete settlement and unlock the remaining notarization steps.') }}
                        </div>
                    </div>
                </div>

                @if (session('status'))
                    <div class="mt-6 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 shadow-sm dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-200">
                        <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                @if (! $paymentRequired)
                    <div class="mt-6 rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-700 shadow-sm dark:border-zinc-800 dark:bg-zinc-950/40 dark:text-zinc-300">
                        {{ __('No payment is currently required for this notarization.') }}
                    </div>
                @elseif ($hasSettledPayment)
                    <div class="mt-6 flex items-start gap-4 rounded-[24px] border border-emerald-200 bg-[linear-gradient(135deg,_rgba(236,253,245,0.95),_rgba(209,250,229,0.9))] px-5 py-5 text-emerald-900 shadow-sm dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-200">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-emerald-600 text-white shadow-sm">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                        </span>
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-700 dark:text-emerald-300">{{ __('Payment complete') }}</div>
                            <div class="mt-1 text-lg font-semibold">{{ __('Payment has already been received for this case.') }}</div>
                            <div class="mt-1 text-sm">{{ __('Your attorney can continue with the remaining notarization steps.') }}</div>
                        </div>
                    </div>
                @else
                    @if ($latestPayment instanceof \App\Models\Payment)
                        <div class="mt-6 rounded-[26px] border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-zinc-500 dark:text-zinc-400">{{ __('Latest payment') }}</div>
                                    <div class="mt-2 text-base font-semibold text-zinc-950 dark:text-white">{{ strtoupper((string) $latestPayment->gateway) }} &middot; {{ $latestPayment->reference }}</div>
                                </div>
                                <span @class([
                                    'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold uppercase tracking-wide',
                                    'border border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300' => $statusTone === 'emerald',
                                    'border border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300' => $statusTone === 'amber',
                                    'border border-rose-300 bg-rose-50 text-rose-700 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300' => $statusTone === 'rose',
                                    'border border-zinc-300 bg-zinc-50 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200' => $statusTone === 'zinc',
                                ])>
                                    <span @class([
                                        'h-1.5 w-1.5 rounded-full',
                                        'bg-emerald-500' => $statusTone === 'emerald',
                                        'bg-amber-500' => $statusTone === 'amber',
                                        'bg-rose-500' => $statusTone === 'rose',
                                        'bg-zinc-400' => $statusTone === 'zinc',
                                    ])></span>
                                    {{ $statusValue ?? '-' }}
                                </span>
                            </div>

                            @if ($hasActiveLink)
                                <div class="mt-5 grid gap-5 sm:grid-cols-[minmax(0,1fr)_240px] sm:items-start">
                                    <div class="space-y-4">
                                        <div class="flex items-start gap-2.5 rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-900/40 dark:bg-sky-950/30 dark:text-sky-100">
                                            <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                                            <span>{{ __('Your payment link is ready. Scan the QR code or open checkout to continue.') }}</span>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-3">
                                            @if ($checkoutUrl)
                                                <a
                                                    href="{{ $checkoutUrl }}"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-teal-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40"
                                                >
                                                    {{ __('Open checkout') }}
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
                                                </a>
                                            @endif
                                            <span class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                                <span class="relative flex h-2 w-2">
                                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-75"></span>
                                                    <span class="relative inline-flex h-2 w-2 rounded-full bg-amber-500"></span>
                                                </span>
                                                {{ __('Awaiting payment confirmation') }}
                                            </span>
                                        </div>
                                        @if ($latestPayment->expires_at)
                                            <div class="flex items-center gap-2 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-xs leading-6 text-zinc-500 dark:border-zinc-800 dark:bg-zinc-950/40 dark:text-zinc-400">
                                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                                                {{ __('Expires') }}: {{ $latestPayment->expires_at->timezone('Asia/Manila')->format('M j, Y g:i A') }} (PHT)
                                            </div>
                                        @endif
                                    </div>
                                    @if ($qrSource)
                                        <div class="flex flex-col items-center gap-2 sm:items-end">
                                            <div class="rounded-[28px] border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-950">
                                                <img
                                                    src="https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=0&data={{ rawurlencode($qrSource) }}"
                                                    alt="{{ __('Payment QR code') }}"
                                                    width="240" height="240"
                                                    class="h-auto w-full max-w-[208px] rounded-2xl"
                                                >
                                            </div>
                                            <p class="text-center text-xs text-zinc-500 dark:text-zinc-400 sm:text-right">{{ __('Scan with your banking or e-wallet app') }}</p>
                                        </div>
                                    @else
                                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                                            {{ __('QR code is unavailable for this payment method. Use the checkout button instead.') }}
                                        </div>
                                    @endif
                                </div>
                            @elseif ($currentPaymentExpired)
                                <div class="mt-4 flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.46 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                                    <span>{{ __('Your previous payment link expired. Choose a payment method below to generate a fresh one.') }}</span>
                                </div>
                            @endif
                        </div>
                    @endif

                    <form id="payment-form" method="POST" action="{{ $postUrl }}" class="mt-6 rounded-[26px] border border-zinc-200 bg-zinc-50/70 p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-950/30 sm:p-6">
                        @csrf

                        <div class="flex items-center justify-between">
                            <label class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">
                                {{ $hasActiveLink ? __('Switch payment method') : __('Choose payment method') }}
                            </label>
                            @if ($enabledGateways !== [])
                                <span class="hidden text-xs text-zinc-500 dark:text-zinc-400 sm:block">{{ __('Tap to select') }}</span>
                            @endif
                        </div>

                        @if ($enabledGateways !== [])
                            <div class="mt-3 grid gap-2.5 sm:grid-cols-2">
                                @foreach ($enabledGateways as $gateway)
                                    @php
                                        $code = (string) ($gateway['code'] ?? '');
                                        $name = (string) ($gateway['name'] ?? $code);
                                        $tint = $avatarPalette[abs(crc32($code)) % count($avatarPalette)];
                                        $initial = strtoupper(mb_substr(trim($name) !== '' ? $name : $code, 0, 1));
                                    @endphp
                                    <label class="group relative block cursor-pointer">
                                        <input type="radio" name="payment_gateway" value="{{ $code }}" class="peer sr-only" @checked($loop->first)>
                                        <div class="flex items-center gap-3 rounded-2xl border border-zinc-200 bg-white px-4 py-3.5 shadow-sm transition group-hover:border-zinc-300 peer-checked:border-teal-500 peer-checked:ring-2 peer-checked:ring-teal-500/25 dark:border-zinc-700 dark:bg-zinc-900 dark:group-hover:border-zinc-600 dark:peer-checked:border-teal-500">
                                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-sm font-bold text-white shadow-sm" style="background:linear-gradient(135deg,#{{ $tint[1] }},#{{ $tint[1] }}cc)">
                                                {{ $initial }}
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $name }}</span>
                                                <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400">{{ __('Pay securely via :gateway', ['gateway' => $name]) }}</span>
                                            </span>
                                            <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full border border-zinc-300 text-transparent transition peer-checked:border-teal-500 peer-checked:bg-teal-500 peer-checked:text-white dark:border-zinc-600">
                                                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                            </span>
                                        </div>
                                    </label>
                                @endforeach
                            </div>

                            @error('payment_gateway')
                                <p class="mt-2 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror

                            <button
                                id="payment-submit"
                                type="submit"
                                class="group mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-zinc-900 px-5 py-3.5 text-base font-semibold text-white shadow-sm transition hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-900/30 disabled:cursor-not-allowed disabled:opacity-70 dark:bg-teal-600 dark:hover:bg-teal-500 dark:focus:ring-teal-500/40"
                            >
                                <svg data-submit-spinner class="hidden h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.4 0 0 5.4 0 12h4Z"/></svg>
                                <span data-submit-label>{{ $hasActiveLink ? __('Generate new payment link') : __('Continue to payment') }}</span>
                                <svg data-submit-arrow class="h-4 w-4 transition group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                            </button>
                        @else
                            <div class="mt-3 flex items-start gap-2.5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                                <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.46 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                                <span>{{ __('Online payment is not configured right now. Please contact your attorney.') }}</span>
                            </div>
                        @endif
                    </form>
                @endif
            </div>

            {{-- Trust footer --}}
            <div class="flex flex-col items-center gap-2 border-t border-zinc-200/80 px-6 py-5 text-center dark:border-zinc-800 sm:flex-row sm:justify-center sm:gap-6 sm:px-8">
                <span class="inline-flex items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                    <svg class="h-3.5 w-3.5 text-teal-600 dark:text-teal-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    {{ __('256-bit encrypted connection') }}
                </span>
                <span class="hidden h-3 w-px bg-zinc-300 dark:bg-zinc-700 sm:block"></span>
                <span class="inline-flex items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                    <svg class="h-3.5 w-3.5 text-teal-600 dark:text-teal-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 4 5v6c0 5 3.4 8.4 8 10 4.6-1.6 8-5 8-10V5l-8-3Z"/></svg>
                    {{ __('Payments processed by trusted gateways') }}
                </span>
            </div>
        </section>

        <p class="mt-5 text-center text-xs text-zinc-400 dark:text-zinc-600">
            &copy; {{ now()->year }} {{ config('app.name') }}. {{ __('All rights reserved.') }}
        </p>
    </main>

    <script>
        // Copy-to-clipboard for the payment reference.
        document.querySelectorAll('[data-copy]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var text = btn.getAttribute('data-copy');
                var icon = btn.querySelector('[data-copy-icon]');
                var done = function () {
                    if (!icon) return;
                    var prev = icon.innerHTML;
                    icon.innerHTML = '<path d="M20 6 9 17l-5-5"/>';
                    icon.classList.add('text-teal-600', 'opacity-100');
                    setTimeout(function () {
                        icon.innerHTML = prev;
                        icon.classList.remove('text-teal-600', 'opacity-100');
                    }, 1500);
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(done).catch(function () {});
                }
            });
        });

        // Loading state on payment submit to prevent double-submission.
        var form = document.getElementById('payment-form');
        if (form) {
            form.addEventListener('submit', function () {
                var btn = document.getElementById('payment-submit');
                if (!btn) return;
                btn.disabled = true;
                var spinner = btn.querySelector('[data-submit-spinner]');
                var arrow = btn.querySelector('[data-submit-arrow]');
                var label = btn.querySelector('[data-submit-label]');
                if (spinner) spinner.classList.remove('hidden');
                if (arrow) arrow.classList.add('hidden');
                if (label) label.textContent = @json(__('Preparing your payment…'));
            });
        }
    </script>
</body>
</html>
