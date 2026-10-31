{{-- PRD FR-9 "Managing iCare groups" — New iCare, for the IT admin or a current iCare leader. --}}
@extends('layouts.app')

@section('title', __('New iCare'))

@section('content')
    <a href="{{ route('icare.index') }}" class="focus-ring inline-flex min-h-11 items-center gap-1 text-label text-primary">
        <x-icon name="arrow-left" /> {{ __('iCare') }}
    </a>
    <h1 class="mt-1 text-title text-ink">{{ __('New iCare') }}</h1>
    <p class="mt-1 text-body text-ink-muted">
        {{ $byAdmin ? __('After saving, assign its leader.') : __('You will lead this iCare. You can add co-leaders and members after saving.') }}
    </p>

    @if ($errors->any())
        <x-alert class="mt-4" variant="danger" :title="__('Please check these fields:')" summary />
    @endif

    <form method="POST" action="{{ route('icare.store') }}" novalidate class="mt-6 grid grid-cols-1 gap-6">
        @csrf
        @include('cell-group.form')

        <div class="flex flex-col gap-3 md:flex-row">
            <x-button>{{ __('Create iCare') }}</x-button>
            <x-button variant="secondary" :href="route('icare.index')">{{ __('Cancel') }}</x-button>
        </div>
    </form>
@endsection
