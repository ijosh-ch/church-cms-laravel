<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>@yield('title', config('app.name'))</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    {{-- DESIGN.md §4, signed out: one centred column, the church name at the top, no navigation. --}}
    <body class="min-h-dvh bg-canvas font-sans text-body text-ink-body antialiased">
        <header class="border-b border-line bg-surface">
            <div class="mx-auto flex h-14 max-w-140 items-center px-4 md:px-6">
                <span class="text-heading text-ink">{{ config('app.name') }}</span>
            </div>
        </header>

        <main class="mx-auto max-w-140 px-4 py-6 md:px-6 md:py-8">
            @yield('content')
        </main>
    </body>
</html>
