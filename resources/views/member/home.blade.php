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

    <form method="POST" action="{{ route('sign-out') }}" class="mt-8">
        @csrf
        <button type="submit"
            class="min-h-12 w-full rounded-md border border-stone-400 bg-white px-4 py-3 text-base font-medium text-stone-900 hover:bg-stone-100 focus:outline-2 focus:outline-offset-2 focus:outline-sky-700">
            {{ __('Sign out') }}
        </button>
    </form>
@endsection
