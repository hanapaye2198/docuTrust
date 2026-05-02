<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} - {{ __('Error') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <main class="mx-auto flex min-h-screen w-full max-w-xl items-center justify-center px-6">
        <div class="w-full rounded-2xl border border-zinc-200 bg-white p-8 text-center shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <h1 class="text-xl font-semibold">{{ __('Something went wrong') }}</h1>
            <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('We could not process your request right now. Please try again in a moment.') }}
            </p>
        </div>
    </main>
</body>
</html>
