<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} - {{ __('Wrong Account') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <main class="mx-auto flex min-h-screen w-full max-w-xl items-center justify-center px-6">
        <div class="w-full rounded-2xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">

            {{-- Icon --}}
            <div class="mb-5 flex justify-center">
                <span class="flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/30">
                    <svg class="h-7 w-7 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                    </svg>
                </span>
            </div>

            <h1 class="text-center text-xl font-semibold text-zinc-900 dark:text-zinc-50">
                {{ __('Wrong account') }}
            </h1>

            <p class="mt-3 text-center text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('You are signed in as') }}
                <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $currentEmail }}</span>,
                {{ __('but this document requires you to sign in as') }}
                <span class="font-medium text-teal-600 dark:text-teal-400">{{ $signerEmail }}</span>.
            </p>

            <div class="mt-6 space-y-3">
                {{-- Switch account: logs out + stores intended URL + redirects to login --}}
                <a href="{{ route('sign.account.switch', ['signerId' => $signerId]) }}"
                   class="flex w-full items-center justify-center gap-2 rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-teal-700 dark:bg-teal-500 dark:hover:bg-teal-600">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75"/>
                    </svg>
                    {{ __('Sign out and use the correct account') }}
                </a>

                {{-- Go to dashboard --}}
                <a href="{{ route('documents.index') }}"
                   class="flex w-full items-center justify-center rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-sm font-medium text-zinc-600 transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800">
                    {{ __('Go to my dashboard') }}
                </a>
            </div>

        </div>
    </main>
</body>
</html>
