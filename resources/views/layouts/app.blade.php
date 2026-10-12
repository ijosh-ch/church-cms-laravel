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
    <body class="min-h-screen bg-stone-50 font-sans text-stone-900 antialiased">
        <header class="border-b border-stone-200 bg-white">
            <div class="mx-auto max-w-xl px-4 py-3 text-base font-semibold">{{ config('app.name') }}</div>
        </header>

        <main class="mx-auto max-w-xl px-4 py-6">
            @yield('content')
        </main>
    </body>
</html>
