{{-- PRD FR-9 "Managing iCare groups" — /admin/icare: every group with its leader, co-leaders,
     active members and status. Each row opens Edit iCare, where the leader is changed. --}}
@extends('layouts.app')

@section('title', __('Manage iCare groups'))

@section('content')
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-title text-ink">{{ __('Manage iCare groups') }}</h1>
            <p class="mt-1 text-body text-ink-muted">{{ trans_choice(':count iCare group|:count iCare groups', $groups->count(), ['count' => $groups->count()]) }}</p>
        </div>
        <x-button variant="secondary" :href="route('icare.create')"><x-icon name="plus" /> {{ __('New iCare') }}</x-button>
    </div>

    @if ($groups->isEmpty())
        <x-empty class="mt-4" icon="inbox">{{ __('There is no iCare yet.') }}</x-empty>
    @else
        <x-list class="mt-4">
            @foreach ($groups as $group)
                @php
                    $leader = $group->currentLeader();
                    $coLeaders = $group->currentCoLeaders();
                @endphp
                <x-list.item :title="$group->label()" :href="route('icare.edit', $group)">
                    <span class="block">
                        {{ __('Leader') }}: {{ $leader?->full_name ?? __('none yet') }}
                    </span>
                    @if ($coLeaders->isNotEmpty())
                        <span class="block">{{ __('Co-leaders') }}: {{ $coLeaders->pluck('full_name')->join(', ') }}</span>
                    @endif
                    <span class="mt-1 flex flex-wrap items-center gap-2">
                        @if ($group->is_active)
                            <x-badge variant="success" icon="check-circle">{{ __('Active') }}</x-badge>
                        @else
                            <x-badge variant="neutral">{{ __('Inactive') }}</x-badge>
                        @endif
                        @unless ($leader)
                            <x-badge variant="warning" icon="exclamation-triangle">{{ __('Needs a leader') }}</x-badge>
                        @endunless
                        <span>{{ collect([$group->branch?->name, trans_choice(':count active member|:count active members', $group->active_members_count, ['count' => $group->active_members_count])])->filter()->join(' · ') }}</span>
                    </span>
                </x-list.item>
            @endforeach
        </x-list>
    @endif
@endsection
