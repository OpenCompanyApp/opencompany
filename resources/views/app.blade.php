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

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.ts', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
