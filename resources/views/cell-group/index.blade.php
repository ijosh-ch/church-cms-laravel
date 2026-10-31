{{-- PRD FR-9: the iCare groups this person leads (all of them for the IT admin): attendance,
     Edit iCare for the group's leader, and New iCare. --}}
@extends('layouts.app')

@section('title', __('iCare'))

@section('content')
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-title text-ink">{{ __('iCare') }}</h1>
            <p class="mt-1 text-body text-ink-muted">{{ __('Choose a group.') }}</p>
        </div>
        @if ($isAdmin)
            <a href="{{ route('admin.icare.index') }}" class="focus-ring inline-flex min-h-11 items-center text-label text-primary">{{ __('Manage iCare groups') }}</a>
        @endif
    </div>

    @if ($groups->isEmpty())
        <x-empty class="mt-4" icon="inbox">{{ __('There is no active iCare yet.') }}</x-empty>
    @else
        <ul class="mt-4 divide-y divide-line rounded-card border border-line bg-surface">
            @foreach ($groups as $group)
                <li class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 px-4 py-3">
                    <span class="min-w-0 text-body font-medium text-ink">{{ $group->label() }}</span>
                    <span class="flex items-center gap-1">
                        <a href="{{ route('icare.attendance.edit', $group) }}"
                            class="focus-ring inline-flex min-h-11 items-center rounded-control px-3 text-label text-primary hover:bg-primary-soft">{{ __('Attendance') }}</a>
                        @can('manage', $group)
                            <a href="{{ route('icare.edit', $group) }}"
                                class="focus-ring inline-flex min-h-11 items-center rounded-control px-3 text-label text-primary hover:bg-primary-soft">{{ __('Edit iCare') }}</a>
                        @endcan
                    </span>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($canCreate)
        <x-button class="mt-6" variant="secondary" :href="route('icare.create')">
            <x-icon name="plus" /> {{ __('New iCare') }}
        </x-button>
    @endif
@endsection
