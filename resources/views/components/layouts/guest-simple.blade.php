<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body
        class="guest-sign-surface min-h-screen text-zinc-900 antialiased dark:text-zinc-100"
    >
        {{ $slot }}
        @stack('scripts')
        @fluxScripts
    </body>
</html>
