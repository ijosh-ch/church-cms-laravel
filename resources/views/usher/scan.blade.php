@extends('layouts.app')

@section('title', __('Scan attendance').' — '.config('app.name'))

@section('content')
    <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
        <div>
            <h1 class="text-2xl font-semibold">{{ $branch->name }}</h1>
            <p class="text-sm text-stone-600">{{ __('Sunday Service') }} · {{ $date->format('D j M Y') }} ·
                <a href="{{ route('usher.branch') }}" class="text-sky-800 underline">{{ __('change') }}</a>
            </p>
        </div>
        <p class="text-base font-medium"><span data-scan-count class="text-2xl font-semibold tabular-nums">{{ $count }}</span> {{ __('today') }}</p>
    </div>

    @php
        $styles = [
            'recorded' => 'border-emerald-400 bg-emerald-50 text-emerald-950',
            'already' => 'border-amber-400 bg-amber-50 text-amber-950',
            'not_found' => 'border-red-400 bg-red-50 text-red-950',
            'no_branch' => 'border-red-400 bg-red-50 text-red-950',
        ];
        $titles = [
            'recorded' => __('Recorded'),
            'already' => __('Already recorded'),
            'not_found' => __('Not recognised'),
            'no_branch' => __('Choose a branch first'),
        ];
    @endphp

    {{-- The result of the last scan or typed code. The scanner script rewrites it in place. --}}
    <div data-scan-result role="status" aria-live="assertive"
        class="mb-4 min-h-24 rounded-md border-2 p-4 {{ $result ? $styles[$result['status']] : 'border-stone-200 bg-white text-stone-600' }}">
        <p data-scan-title class="text-xl font-semibold">{{ $result ? $titles[$result['status']] : __('Ready to scan') }}</p>
        <p data-scan-detail class="text-lg">
            @if ($result && isset($result['name']))
                {{ $result['name'] }}@if ($result['icare']) · iCare {{ $result['icare'] }}@endif
                @if ($result['status'] === 'already') · {{ $result['time'] }}@endif
            @elseif (! $result)
                {{ __('Point the camera at a member\'s QR.') }}
            @endif
        </p>
    </div>

    <div data-scanner data-endpoint="{{ route('usher.scan.store') }}"
        data-labels='@json($titles)' data-styles='@json($styles)'
        class="relative mb-4 aspect-square w-full overflow-hidden rounded-md bg-stone-900">
        <video data-scanner-video class="size-full object-cover" playsinline muted></video>
        <p data-scanner-message class="absolute inset-0 flex items-center justify-center p-6 text-center text-base text-white">
            {{ __('Starting the camera…') }}
        </p>
    </div>

    <form method="POST" action="{{ route('usher.code') }}" class="mb-6 rounded-md border border-stone-200 bg-white p-4">
        @csrf
        <label for="code" class="mb-1 block text-sm font-medium text-stone-800">{{ __('Or type the 8-character backup code') }}</label>
        <div class="flex gap-3">
            <input id="code" name="code" type="text" required maxlength="16" autocomplete="off" autocapitalize="characters" spellcheck="false"
                class="block min-h-12 w-full rounded-md border border-stone-400 bg-white px-3 font-mono text-xl uppercase tracking-widest focus:outline-2 focus:outline-offset-1 focus:outline-sky-700">
            <button type="submit"
                class="min-h-12 rounded-md bg-sky-800 px-5 text-base font-medium text-white hover:bg-sky-900 focus:outline-2 focus:outline-offset-2 focus:outline-sky-700">
                {{ __('Record') }}
            </button>
        </div>
        @error('code')
            <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
        @enderror
    </form>

    <div class="grid gap-3 sm:grid-cols-2">
        <a href="{{ route('usher.roster') }}"
            class="flex min-h-12 items-center justify-center rounded-md border border-stone-400 bg-white px-4 text-base font-medium text-stone-900 hover:bg-stone-100">
            {{ __('Find by name') }}
        </a>
        <a href="{{ route('usher.attendance', ['date' => $date->format('Y-m-d'), 'branch' => $branch->code]) }}"
            class="flex min-h-12 items-center justify-center rounded-md border border-stone-400 bg-white px-4 text-base font-medium text-stone-900 hover:bg-stone-100">
            {{ __('Today\'s attendance') }}
        </a>
    </div>

    @vite('resources/js/scanner.js')
@endsection
