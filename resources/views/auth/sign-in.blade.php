@extends('layouts.guest')

@section('title', __('Sign in').' — '.config('app.name'))

@section('content')
    {{-- One narrow column, as GitHub's sign-in (owner, 2026-10-31): an email field, Google, then registration. --}}
    <div class="mx-auto max-w-sm">
        <h1 class="text-center text-title text-ink">{{ __('Sign in to :app', ['app' => config('app.name')]) }}</h1>

        @if (session('link_sent'))
            <x-alert class="mt-6" variant="success">
                {{ __('If this email belongs to a member, a sign-in link is on its way. It works once, for 15 minutes. Check your spam folder too.') }}
            </x-alert>
        @endif

        @foreach (['link', 'google'] as $problem)
            @error($problem)
                <x-alert class="mt-6" variant="danger">{{ $message }}</x-alert>
            @enderror
        @endforeach

        <x-card class="mt-6">
            <form method="POST" action="{{ route('sign-in.send') }}" novalidate>
                @csrf

                <x-field name="email" :label="__('Email address')">
                    <x-input type="email" autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false" placeholder="you@example.com" required />
                </x-field>

                <x-button class="mt-4" full>{{ __('Email me a sign-in link') }}</x-button>

                <p class="mt-2 text-caption text-ink-muted">{{ __('We send a link that works once, for 15 minutes. No password needed.') }}</p>
            </form>

            @if (config('services.google.client_id'))
                <div class="my-5 flex items-center gap-3 text-caption text-ink-muted">
                    <span class="h-px flex-1 bg-line" aria-hidden="true"></span>
                    {{ __('or') }}
                    <span class="h-px flex-1 bg-line" aria-hidden="true"></span>
                </div>

                <x-button variant="secondary" :href="route('auth.google')" full>
                    <x-icon name="google" />
                    {{ __('Continue with Google') }}
                </x-button>

                @if (config('services.google.birthday'))
                    <p class="mt-2 text-caption text-ink-muted">
                        {{ __('Google will ask to share your birthday. We use it only to fill in the registration form, and you can say no.') }}
                    </p>
                @endif
            @endif
        </x-card>

        <p class="mt-4 rounded-card border border-line p-4 text-center text-body text-ink-body">
            {{ __('New here?') }}
            <a href="{{ route('register', ['manual' => 1]) }}" class="focus-ring rounded-sm font-medium text-primary underline-offset-2 hover:underline">{{ __('Register') }}</a>
        </p>
    </div>
@endsection
