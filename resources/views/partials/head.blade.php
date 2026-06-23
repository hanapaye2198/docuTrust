<script>
    (function () {
        try {
            var theme = localStorage.getItem('flux.appearance')
                || localStorage.getItem('docutrust-theme')
                || localStorage.getItem('theme');

            if (theme === 'dark' || theme === 'light') {
                localStorage.setItem('flux.appearance', theme);
                localStorage.setItem('docutrust-theme', theme);
                localStorage.setItem('theme', theme);
                document.documentElement.classList.toggle('dark', theme === 'dark');
            }
        } catch (error) {
            // Ignore storage errors in restricted browsing contexts.
        }
    })();
</script>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}" />

<title>{{ $title ?? config('app.name') }}</title>

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
@stack('head')
