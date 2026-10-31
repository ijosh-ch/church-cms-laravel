{{-- PRD FR-9 "Managing iCare groups" — Edit iCare, for the group's current leader and the IT
     admin: its information, the leader (changed by the admin only), co-leaders, and members with
     add, remove and the active/inactive switch. Works without script. --}}
@extends('layouts.app')

@section('title', __('Edit iCare').' — '.$group->label())

@section('content')
    <a href="{{ $isAdmin ? route('admin.icare.index') : route('icare.index') }}" class="focus-ring inline-flex min-h-11 items-center gap-1 text-label text-primary">
        <x-icon name="arrow-left" /> {{ $isAdmin ? __('All iCare groups') : __('iCare') }}
    </a>
    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
        <h1 class="text-title text-ink">{{ $group->label() }}</h1>
        @unless ($group->is_active)
            <x-badge variant="neutral">{{ __('Inactive') }}</x-badge>
        @endunless
    </div>
    <p class="mt-1 text-body text-ink-muted">
        <a href="{{ route('icare.attendance.edit', $group) }}" class="focus-ring text-primary underline underline-offset-2">{{ __('Take attendance') }}</a>
    </p>

    @if (session('status'))
        <x-alert class="mt-4" variant="success" role="status">{{ session('status') }}</x-alert>
    @endif

    @if ($errors->any() && ! $errors->has('q'))
        <x-alert class="mt-4" variant="danger" :title="__('Please check these fields:')" summary />
    @endif

    <div class="mt-6 grid grid-cols-1 gap-6">
        {{-- Information --}}
        <x-card :title="__('iCare information')" id="information">
            <form method="POST" action="{{ route('icare.update', $group) }}" novalidate class="grid grid-cols-1 gap-5">
                @csrf
                @method('PUT')
                @include('cell-group.form', ['showStatus' => $isAdmin])
                <x-button>{{ __('Save information') }}</x-button>
            </form>
        </x-card>

        {{-- Leader --}}
        <x-card :title="__('Leader')" id="leader">
            @if ($leader)
                <p class="text-body text-ink">{{ $leader->full_name }}</p>
            @else
                <p class="text-body text-ink-muted">{{ __('No leader yet.') }}</p>
            @endif

            @if ($isAdmin)
                <p class="mt-1 text-caption text-ink-muted">{{ __('The previous leader steps down; their history is kept.') }}</p>
                @include('cell-group.people-search', [
                    'for' => 'leader',
                    'label' => $leader ? __('Change the leader') : __('Assign a leader'),
                    'action' => route('icare.leader.update', $group),
                    'method' => 'PUT',
                    'pick' => __('Make leader'),
                ])
            @else
                <p class="mt-1 text-caption text-ink-muted">{{ __('The IT admin assigns the leader.') }}</p>
            @endif
        </x-card>

        {{-- Co-leaders --}}
        <x-card :title="__('Co-leaders')" id="co-leaders">
            <p class="text-caption text-ink-muted">{{ __('Co-leaders take attendance and set members active or inactive.') }}</p>

            @if ($coLeaders->isEmpty())
                <p class="mt-3 text-body text-ink-muted">{{ __('No co-leaders yet.') }}</p>
            @else
                <ul class="mt-3 divide-y divide-line rounded-card border border-line">
                    @foreach ($coLeaders as $coLeader)
                        <li class="flex items-center justify-between gap-3 px-4 py-2">
                            <span class="min-w-0 text-body text-ink">{{ $coLeader->full_name }}</span>
                            <form method="POST" action="{{ route('icare.co-leaders.destroy', [$group, $coLeader]) }}"
                                data-confirm="{{ __('Remove :name as co-leader?', ['name' => $coLeader->full_name]) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" data-button class="focus-ring inline-flex min-h-11 items-center rounded-control px-3 text-label text-danger hover:bg-danger-soft">{{ __('Remove') }}</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif

            @include('cell-group.people-search', [
                'for' => 'co_leader',
                'label' => __('Add a co-leader'),
                'action' => route('icare.co-leaders.store', $group),
                'method' => 'POST',
                'pick' => __('Make co-leader'),
            ])
        </x-card>

        {{-- Members --}}
        <x-card :title="__('Members')" id="members">
            <p class="text-caption text-ink-muted">
                {{ __(':active active, :inactive inactive. Inactive members are not listed or counted in attendance.', [
                    'active' => $members->where('cell_group_active', true)->count(),
                    'inactive' => $members->where('cell_group_active', false)->count(),
                ]) }}
            </p>

            @if ($members->isEmpty())
                <p class="mt-3 text-body text-ink-muted">{{ __('No members yet.') }}</p>
            @else
                <ul class="mt-3 divide-y divide-line rounded-card border border-line">
                    @foreach ($members as $member)
                        <li class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 px-4 py-2">
                            <span class="flex min-w-0 flex-wrap items-center gap-2">
                                <span @class(['text-body', 'text-ink' => $member->cell_group_active, 'text-ink-muted' => ! $member->cell_group_active])>{{ $member->full_name }}</span>
                                @if ($member->cell_group_active)
                                    <x-badge variant="success" icon="check-circle">{{ __('Active') }}</x-badge>
                                @else
                                    <x-badge variant="neutral">{{ __('Inactive') }}</x-badge>
                                @endif
                            </span>
                            <span class="flex items-center gap-1">
                                <form method="POST" action="{{ route('icare.members.status', [$group, $member]) }}">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="active" value="{{ $member->cell_group_active ? 0 : 1 }}">
                                    <button type="submit" data-button class="focus-ring inline-flex min-h-11 items-center rounded-control px-3 text-label text-primary hover:bg-primary-soft">
                                        {{ $member->cell_group_active ? __('Set inactive') : __('Set active') }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('icare.members.destroy', [$group, $member]) }}"
                                    data-confirm="{{ __('Remove :name from :group? They will have no iCare.', ['name' => $member->full_name, 'group' => $group->label()]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" data-button class="focus-ring inline-flex min-h-11 items-center rounded-control px-3 text-label text-danger hover:bg-danger-soft">{{ __('Remove') }}</button>
                                </form>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif

            @include('cell-group.people-search', [
                'for' => 'member',
                'label' => __('Add a member'),
                'action' => route('icare.members.store', $group),
                'method' => 'POST',
                'pick' => __('Add to this iCare'),
            ])
            <p class="mt-2 text-caption text-ink-muted">{{ __('Someone already in another iCare moves here.') }}</p>
        </x-card>
    </div>
@endsection
