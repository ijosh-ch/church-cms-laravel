@extends('layouts.app')

@section('title', __('Sign in').' — '.config('app.name'))

@section('content')
    <h1 class="mb-1 text-2xl font-semibold">{{ __('Welcome Home') }}</h1>
    <p class="mb-6 text-base text-stone-600">{{ __('Sign in to see your attendance QR, or register if you are new.') }}</p>

    @error('google')
        <div class="mb-6 rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-800" role="alert">
            {{ $message }}
        </div>
    @enderror

    @if (config('services.google.client_id'))
        <a href="{{ route('auth.google') }}"
            class="flex min-h-12 w-full items-center justify-center gap-3 rounded-md border border-stone-400 bg-white px-4 py-3 text-base font-medium text-stone-900 hover:bg-stone-100 focus:outline-2 focus:outline-offset-2 focus:outline-sky-700">
            <svg class="size-5" viewBox="0 0 24 24" aria-hidden="true">
                <path fill="#4285F4" d="M23.5 12.3c0-.8-.1-1.6-.2-2.3H12v4.4h6.5a5.5 5.5 0 0 1-2.4 3.6v3h3.9c2.3-2.1 3.5-5.2 3.5-8.700z"/>
                <path fill="#34A853" d="M12 24c3.200 0 6-1.100 8-2.900l-3.900-3a7.200 7.200 0 0 1-10.800-3.800h-4v3.100A12 12 0 0 0 12 24z"/>
                <path fill="#FBBC05" d="M5.300 14.300a7.200 7.200 0 0 1 0-4.600v-3.100h-4a12 12 0 0 0 0 10.800l4-3.100z"/>
                <path fill="#EA4335" d="M12 4.800c1.800 0 3.300.6 4.600 1.800l3.400-3.400A12 12 0 0 0 1.300 6.600l4 3.100A7.200 7.200 0 0 1 12 4.800z"/>
            </svg>
            {{ __('Continue with Google') }}
        </a>

        <p class="mt-3 text-sm text-stone-600">
            {{ __('Use the Google account whose email you registered with. If you are new, we will take you to the registration form with your email filled in.') }}
        </p>
    @else
        <div class="rounded-md border border-stone-200 bg-white p-4 text-sm text-stone-700">
            {{ __('Google sign-in is not set up on this server.') }}
        </div>
    @endif

    <div class="my-6 flex items-center gap-3 text-sm text-stone-500" aria-hidden="true">
        <span class="h-px flex-1 bg-stone-300"></span>
        {{ __('or') }}
        <span class="h-px flex-1 bg-stone-300"></span>
    </div>

    <a href="{{ route('register', ['manual' => 1]) }}"
        class="flex min-h-12 w-full items-center justify-center rounded-md bg-sky-800 px-4 py-3 text-base font-medium text-white hover:bg-sky-900 focus:outline-2 focus:outline-offset-2 focus:outline-sky-700">
        {{ __('Register without Google') }}
    </a>
@endsection
