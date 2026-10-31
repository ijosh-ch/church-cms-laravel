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
            <div class="mx-auto flex max-w-xl flex-wrap items-center justify-between gap-x-4 px-4 py-3">
                <span class="text-base font-semibold">{{ config('app.name') }}</span>

                @auth
                    {{-- The menu is a native <details>: it opens and closes without any script. --}}
                    <details class="group relative">
                        <summary
                            class="flex size-11 cursor-pointer list-none items-center justify-center rounded-md border border-stone-300 bg-white hover:bg-stone-100 focus:outline-2 focus:outline-offset-2 focus:outline-sky-700 [&::-webkit-details-marker]:hidden">
                            <span class="sr-only">{{ __('Menu') }}</span>
                            <svg class="size-6 group-open:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
                                <path d="M4 7h16M4 12h16M4 17h16" />
                            </svg>
                            <svg class="hidden size-6 group-open:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
                                <path d="M6 6l12 12M18 6L6 18" />
                            </svg>
                        </summary>

                        <nav aria-label="{{ __('Main') }}"
                            class="absolute right-0 z-10 mt-2 w-56 rounded-md border border-stone-200 bg-white py-1 shadow-lg">
                            <a href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif
                                class="flex min-h-11 items-center px-4 text-base text-stone-900 hover:bg-stone-100 aria-[current=page]:font-semibold">{{ __('My QR') }}</a>
                            <a href="{{ route('profile.edit') }}" @if (request()->routeIs('profile.*')) aria-current="page" @endif
                                class="flex min-h-11 items-center px-4 text-base text-stone-900 hover:bg-stone-100 aria-[current=page]:font-semibold">{{ __('Profile') }}</a>
                            @can('scan')
                                <a href="{{ route('usher.scan') }}" @if (request()->routeIs('usher.scan', 'usher.branch', 'usher.roster')) aria-current="page" @endif
                                    class="flex min-h-11 items-center border-t border-stone-200 px-4 text-base text-stone-900 hover:bg-stone-100 aria-[current=page]:font-semibold">{{ __('Scan attendance') }}</a>
                                <a href="{{ route('usher.attendance') }}" @if (request()->routeIs('usher.attendance')) aria-current="page" @endif
                                    class="flex min-h-11 items-center px-4 text-base text-stone-900 hover:bg-stone-100 aria-[current=page]:font-semibold">{{ __('Attendance') }}</a>
                            @endcan
                            @if (auth()->user()->can('admin') || auth()->user()->leadsAnyCellGroup())
                                <a href="{{ route('icare.index') }}" @if (request()->routeIs('icare.*')) aria-current="page" @endif
                                    class="flex min-h-11 items-center border-t border-stone-200 px-4 text-base text-stone-900 hover:bg-stone-100 aria-[current=page]:font-semibold">{{ __('iCare') }}</a>
                            @endif
                            @can('admin')
                                <a href="{{ route('admin.icare.index') }}" @if (request()->routeIs('admin.icare.*')) aria-current="page" @endif
                                    class="flex min-h-11 items-center border-t border-stone-200 px-4 text-base text-stone-900 hover:bg-stone-100 aria-[current=page]:font-semibold">{{ __('Manage iCare groups') }}</a>
                                <a href="{{ route('admin.members.index') }}" @if (request()->routeIs('admin.members.*')) aria-current="page" @endif
                                    class="flex min-h-11 items-center border-t border-stone-200 px-4 text-base text-stone-900 hover:bg-stone-100 aria-[current=page]:font-semibold">{{ __('Members') }}</a>
                            @endcan
                            <form method="POST" action="{{ route('sign-out') }}" class="border-t border-stone-200">
                                @csrf
                                <button type="submit"
                                    class="flex min-h-11 w-full items-center px-4 text-left text-base text-stone-900 hover:bg-stone-100">{{ __('Sign out') }}</button>
                            </form>
                        </nav>
                    </details>
                @endauth
            </div>
        </header>

        <main class="mx-auto max-w-xl px-4 py-6">
            @yield('content')
        </main>
    </body>
</html>
