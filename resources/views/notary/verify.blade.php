<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Notarization Verification') }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-zinc-50 dark:bg-zinc-950">
    <div class="mx-auto max-w-2xl px-4 py-12">
        <div class="rounded-2xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="text-center">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-950/40">
                    <svg class="h-8 w-8 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                    </svg>
                </div>
                <h1 class="mt-4 text-2xl font-bold text-zinc-900 dark:text-zinc-50">{{ __('Document Verified') }}</h1>
                <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('This document has been notarized and recorded in the notarial register.') }}</p>
            </div>

            <div class="mt-8 space-y-6">
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Notarial Register Entry') }}</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Entry Number') }}</dt>
                            <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ str_pad($entry->entry_number, 3, '0', STR_PAD_LEFT) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Series of') }}</dt>
                            <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $entry->entry_year }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Document Title') }}</dt>
                            <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $entry->document_title }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Notarial Act') }}</dt>
                            <dd class="font-medium capitalize text-zinc-900 dark:text-zinc-100">{{ str_replace('_', ' ', $entry->notarial_act_type) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Date & Time (PHT)') }}</dt>
                            <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $entry->notarized_at?->timezone('Asia/Manila')->format('M j, Y g:i:s A') }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Notary Public') }}</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Name') }}</dt>
                            <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $entry->notaryCredential?->user?->name ?? '-' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Commission No.') }}</dt>
                            <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $entry->notaryCredential?->commission_number ?? '-' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Valid Until') }}</dt>
                            <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $entry->notaryCredential?->commission_expires_at?->format('M j, Y') ?? '-' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Parties') }}</h2>
                    <ul class="mt-4 space-y-2 text-sm">
                        @foreach ($entry->parties ?? [] as $party)
                            <li class="text-zinc-700 dark:text-zinc-300">
                                <span class="font-medium">{{ $party['name'] ?? '-' }}</span>
                                @if (($party['address'] ?? '') !== '')
                                    — {{ $party['address'] }}
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>

                @if ($entry->document?->documentHash)
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Blockchain Proof') }}</h2>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div>
                                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('SHA-256 Hash') }}</dt>
                                <dd class="mt-1 break-all font-mono text-xs text-zinc-700 dark:text-zinc-300">{{ $entry->document->documentHash->hash }}</dd>
                            </div>
                            @if ($entry->document->documentHash->transaction_id)
                                <div>
                                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Transaction ID') }}</dt>
                                    <dd class="mt-1 break-all font-mono text-xs text-zinc-700 dark:text-zinc-300">{{ $entry->document->documentHash->transaction_id }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                @endif
            </div>

            <div class="mt-8 text-center text-xs text-zinc-400 dark:text-zinc-500">
                {{ __('Verified by :app on :date', ['app' => config('app.name'), 'date' => now()->format('M j, Y g:i A')]) }}
            </div>
        </div>
    </div>
</body>
</html>
