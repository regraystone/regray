<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }
        </style>

        {{-- Each icon's URL carries its file's modified time, so a browser that keeps
             favicons in a cache of its own (Safari) fetches a changed icon. --}}
        @php($iconVersion = fn (string $path): int => @filemtime(public_path($path)) ?: 0)
        <link rel="icon" href="/favicon.ico?v={{ $iconVersion('favicon.ico') }}" sizes="any">
        <link rel="icon" href="/images/regray_icon.png?v={{ $iconVersion('images/regray_icon.png') }}" type="image/png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png?v={{ $iconVersion('apple-touch-icon.png') }}">

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
