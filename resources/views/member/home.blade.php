@extends('layouts.app')

@section('title', __('My QR').' — '.config('app.name'))

@section('content')
    @if (session('registered'))
        <div class="mb-6 rounded-md border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-900" role="status">
            {{ __('Registration complete. This is your attendance QR — show it to the usher on Sunday.') }}
        </div>
    @endif

    <h1 class="text-2xl font-semibold">{{ $member->full_name }}</h1>
    <p class="mb-6 text-sm text-stone-500">
        {{ $member->branch->name }}@if ($member->icareTeam) · iCare {{ $member->icareTeam->name }}@endif
    </p>

    @if ($qrSvg)
        <div class="rounded-md border border-stone-200 bg-white p-6 text-center">
            {{-- A QR generated on this server from the member's own token: the one allowed raw output. --}}
            <div class="mx-auto inline-block" data-testid="qr">{!! $qrSvg !!}</div>

            <p class="mt-4 text-sm text-stone-500">{{ __('If the QR cannot be scanned, give the usher this code:') }}</p>
            <p class="mt-1 font-mono text-2xl tracking-widest" data-testid="short-code">{{ $member->short_code }}</p>
        </div>
    @else
        <p class="text-base text-stone-700">{{ __('You do not have a QR yet.') }}</p>
    @endif

    @if ($member->needsProfile())
        {{-- Under the QR, which is what the page is for. Stays until the rest of the profile is filled in. --}}
        <x-card class="mt-6" :title="__('Complete your profile')" data-testid="complete-profile">
            <p>{{ __('Two minutes: where you live in Taiwan, your work or study, and your Chinese name and Line ID if you have them. It helps your iCare leader get to know you.') }}</p>
            <div class="mt-4">
                <x-button :href="route('profile.edit')">{{ __('Complete my profile') }}</x-button>
            </div>
        </x-card>
    @endif

    <p class="mt-6 text-center text-sm">
        <a href="{{ route('profile.edit') }}" class="text-sky-800 underline">{{ __('Edit my profile') }}</a>
    </p>
@endsection
