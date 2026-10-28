{{-- PRD FR-9: someone who leads several iCare groups (or the IT admin) picks one. --}}
@extends('layouts.app')

@section('title', __('iCare attendance'))

@section('content')
    <h1 class="text-title text-ink">{{ __('iCare attendance') }}</h1>
    <p class="mt-1 text-body text-ink-muted">{{ __('Choose a group.') }}</p>

    <ul class="mt-4 divide-y divide-line rounded-card border border-line bg-surface">
        @foreach ($groups as $group)
            <li>
                <a href="{{ route('icare.attendance.edit', $group) }}"
                    class="focus-ring flex min-h-12 items-center justify-between gap-3 px-4 py-3 text-body text-ink hover:bg-canvas">
                    <span>{{ $group->label() }}</span>
                    <x-icon name="chevron-right" />
                </a>
            </li>
        @endforeach
    </ul>
@endsection
