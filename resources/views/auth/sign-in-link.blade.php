@extends('layouts.guest')

@section('title', __('Sign in').' — '.config('app.name'))

@section('content')
    {{-- Opening the emailed link lands here; only the button uses it up (Auth\EmailLinkController). --}}
    <div class="mx-auto max-w-sm">
        <h1 class="text-center text-title text-ink">{{ __('Sign in to :app', ['app' => config('app.name')]) }}</h1>

        <x-card class="mt-6">
            <p class="text-body text-ink-body">{{ __('Press the button to finish signing in on this device.') }}</p>

            <form method="POST" action="{{ route('sign-in.link.use', ['token' => $token]) }}" class="mt-4">
                @csrf
                <x-button full>{{ __('Sign in') }}</x-button>
            </form>
        </x-card>
    </div>
@endsection
