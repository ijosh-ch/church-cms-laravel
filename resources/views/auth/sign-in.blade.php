@extends('layouts.guest')

@section('title', __('Sign in').' — '.config('app.name'))

@section('content')
    <h1 class="text-title text-ink">{{ __('Welcome Home') }}</h1>
    <p class="mt-1 text-body text-ink-muted">{{ __('Sign in to see your attendance QR, or register if you are new.') }}</p>

    @error('google')
        <x-alert class="mt-6" variant="danger">{{ $message }}</x-alert>
    @enderror

    {{-- Two ways in, each saying who it is for and what happens next. --}}
    <div class="mt-6 grid gap-4">
        @if (config('services.google.client_id'))
            <x-card :title="__('With your Google account')">
                <ul class="grid gap-2">
                    <li>
                        <span class="font-medium text-ink">{{ __('Already registered?') }}</span>
                        {{ __('You go straight to your attendance QR.') }}
                    </li>
                    <li>
                        <span class="font-medium text-ink">{{ __('New here?') }}</span>
                        {{ __('We open the registration form with your email filled in.') }}
                    </li>
                </ul>

                <div class="mt-4">
                    <x-button variant="secondary" :href="route('auth.google')">
                        <x-icon name="google" />
                        {{ __('Continue with Google') }}
                    </x-button>
                </div>

                <p class="mt-3 text-caption text-ink-muted">
                    {{ __('Use the Google account whose email you registered with.') }}
                    @if (config('services.google.birthday'))
                        {{ __('Google will ask to share your birthday. We use it only to fill in the registration form, and you can say no.') }}
                    @endif
                </p>
            </x-card>
        @else
            <x-alert>{{ __('Google sign-in is not set up on this server.') }}</x-alert>
        @endif

        <x-card :title="__('Without a Google account')">
            <p>{{ __('Register with any email address. We send your attendance QR to that email.') }}</p>

            <div class="mt-4">
                <x-button :href="route('register', ['manual' => 1])">{{ __('Register without Google') }}</x-button>
            </div>
        </x-card>
    </div>
@endsection
