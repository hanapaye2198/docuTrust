<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="app-shell min-h-screen bg-zinc-100 dark:bg-zinc-950">
        <main class="app-workspace flex h-dvh min-h-0 flex-col">
            {{ $slot }}
        </main>

        @include('partials.idle-session')
        @stack('scripts')
        @fluxScripts
    </body>
</html>
