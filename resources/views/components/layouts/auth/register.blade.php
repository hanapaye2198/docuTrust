<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        <script>
            (function () {
                const savedTheme = localStorage.getItem("theme");
                const prefersDark = window.matchMedia("(prefers-color-scheme: dark)").matches;

                if (savedTheme === "dark" || (!savedTheme && prefersDark)) {
                    document.documentElement.classList.add("dark");
                }
            })();
        </script>
    </head>
    <body class="min-h-screen bg-[#F8FAFC] antialiased transition-colors duration-300 dark:bg-zinc-950">
        <div class="fixed right-4 top-4 z-50 flex items-center gap-2">
            <a
                href="{{ route('session.reset') }}"
                class="inline-flex h-10 items-center justify-center rounded-xl border border-amber-200 bg-amber-50 px-3 text-xs font-semibold text-amber-700 shadow-sm transition hover:bg-amber-100 dark:border-amber-700/50 dark:bg-amber-900/40 dark:text-amber-200"
            >
                {{ __('Reset session') }}
            </a>
            <button
                id="theme-toggle-auth"
                type="button"
                class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-zinc-200/90 bg-white/90 text-zinc-700 shadow-sm backdrop-blur transition duration-300 hover:scale-105 hover:border-teal-400 hover:text-teal-600 hover:shadow-md hover:shadow-teal-500/20 dark:border-zinc-700 dark:bg-zinc-900/90 dark:text-zinc-200 dark:hover:border-teal-500 dark:hover:text-teal-300"
                aria-label="{{ __('Toggle theme') }}"
            >
                <svg class="h-5 w-5 dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M21 12.79A9 9 0 1111.21 3c.5 0 .8.54.53.95A7 7 0 0019.05 12c.42-.27.95.03.95.53v.26z"></path>
                </svg>
                <svg class="hidden h-5 w-5 dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.364 6.364l-1.414-1.414M7.05 7.05 5.636 5.636m12.728 0L16.95 7.05M7.05 16.95l-1.414 1.414M12 16a4 4 0 100-8 4 4 0 000 8z"></path>
                </svg>
            </button>
        </div>
        {{ $slot }}
        @include('partials.idle-session')
        @fluxScripts
        <script>
            const authThemeToggleButton = document.getElementById("theme-toggle-auth");
            const rootElement = document.documentElement;

            const setThemeState = (isDarkMode) => {
                authThemeToggleButton?.setAttribute("aria-pressed", isDarkMode ? "true" : "false");
                authThemeToggleButton?.setAttribute("title", isDarkMode ? "Switch to light mode" : "Switch to dark mode");
            };

            setThemeState(rootElement.classList.contains("dark"));

            authThemeToggleButton?.addEventListener("click", function () {
                const isDark = rootElement.classList.toggle("dark");
                localStorage.setItem("theme", isDark ? "dark" : "light");
                setThemeState(isDark);
            });
        </script>
    </body>
</html>
