{{-- A member's own photo (owner, 2026-10-28): optional, with consent, removable at any time.
     resources/js/app.js shrinks the picture in the browser before it is sent. --}}
@extends('layouts.app')

@section('title', __('My photo').' — '.config('app.name'))

@section('content')
    <h1 class="text-title text-ink">{{ __('My photo') }}</h1>
    <p class="mt-1 text-body text-ink-muted">{{ __('Optional. You can add it now, later, or never.') }}</p>

    @if (session('status'))
        <x-alert class="mt-4" variant="success">{{ session('status') }}</x-alert>
    @endif

    @if ($errors->any())
        <x-alert class="mt-4" variant="danger" :title="__('Please check these fields:')" summary />
    @endif

    <x-alert class="mt-4" :title="__('What we keep, and why')">
        {{ __('One photo of your face. It is kept privately and seen only by you. Later it may help your iCare leader recognise you in the photo of a meeting, so they can tick your attendance faster. It is never shown on a public page, and you can delete it here at any time.') }}
    </x-alert>

    @if ($member->photo_path)
        <div class="mt-6 flex flex-col items-center gap-4 rounded-card border border-line bg-surface p-4">
            <img src="{{ route('profile.photo.image') }}" alt="{{ __('Your photo') }}"
                class="size-40 rounded-full border border-line object-cover">
            <p class="text-caption text-ink-muted">
                {{ __('Saved with your consent on :date.', ['date' => $member->photo_consent_at?->timezone('Asia/Taipei')->translatedFormat('j F Y')]) }}
            </p>
            <form method="POST" action="{{ route('profile.photo.destroy') }}" class="w-full"
                data-confirm="{{ __('Delete your photo? Your consent is withdrawn with it.') }}">
                @csrf
                @method('DELETE')
                <x-button variant="secondary">{{ __('Delete my photo') }}</x-button>
            </form>
        </div>
    @endif

    <form method="POST" action="{{ route('profile.photo.store') }}" enctype="multipart/form-data" novalidate
        class="mt-6 grid grid-cols-1 gap-5" data-self-photo>
        @csrf

        <div>
            <label for="photo" @class([
                'focus-ring flex min-h-40 cursor-pointer flex-col items-center justify-center gap-1 rounded-card border-2 border-dashed border-line-strong bg-surface p-4 text-center text-body text-ink-muted',
                'border-danger' => $errors->has('photo'),
            ])>
                <x-icon name="camera" size="24" />
                <span data-photo-label>{{ $member->photo_path ? __('Choose a new photo') : __('Take or choose a photo of yourself') }}</span>
                <span class="text-caption" data-photo-note>{{ __('Face the camera, in good light, alone in the picture.') }}</span>
            </label>
            <input id="photo" name="photo" type="file" accept="image/*" capture="user" class="sr-only" data-photo
                data-max-bytes="1000000" data-max-side="1600"
                @error('photo') aria-invalid="true" aria-describedby="photo-error" @enderror>
            @error('photo')
                <p id="photo-error" class="mt-2 flex items-start gap-1.5 text-label text-danger"><x-icon name="exclamation-circle" /><span>{{ $message }}</span></p>
            @enderror
        </div>

        <div>
            <label class="flex items-start gap-3 text-body text-ink">
                <input id="consent" type="checkbox" name="consent" value="1" class="mt-1 size-5 shrink-0 accent-primary" @checked(old('consent'))>
                <span>{{ __('I agree that IFGF Taipei–Zhongli keeps this photo of me and may use it to recognise me in iCare meeting photos. I can delete it at any time.') }}</span>
            </label>
            @error('consent')
                <p class="mt-2 flex items-start gap-1.5 text-label text-danger"><x-icon name="exclamation-circle" /><span>{{ $message }}</span></p>
            @enderror
        </div>

        <x-button>{{ __('Save my photo') }}</x-button>
        <x-button variant="quiet" :href="route('home')">{{ __('Not now') }}</x-button>
    </form>
@endsection
