<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <script>
            // Apply theme immediately to prevent flash
            (function() {
                const stored = localStorage.getItem('color-mode');
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                let isDark;

                if (stored === 'dark') {
                    isDark = true;
                } else if (stored === 'light') {
                    isDark = false;
                } else {
                    // 'system' or no preference - follow system
                    isDark = prefersDark;
                }

                document.documentElement.classList.toggle('dark', isDark);
                document.documentElement.classList.toggle('light', !isDark && stored === 'light');
            })();
        </script>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <meta property="og:image" content="{{ url('/og-image.svg') }}">
        <meta property="og:type" content="website">
        <meta name="twitter:card" content="summary_large_image">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Reverb config (runtime values, not build-time Vite env) -->
        <script>
            window.__reverb = {
                key: @json(config('broadcasting.connections.reverb.key')),
                host: @json(config('broadcasting.connections.reverb.options.host')),
                port: @json((int) config('broadcasting.connections.reverb.options.port')),
                scheme: @json(config('broadcasting.connections.reverb.options.scheme')),
            };
        </script>

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
