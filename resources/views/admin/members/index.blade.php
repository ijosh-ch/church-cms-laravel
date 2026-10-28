@extends('layouts.app')

@section('title', __('Members').' — '.config('app.name'))

@section('content')
    <h1 class="mb-4 text-2xl font-semibold">{{ __('Members') }}</h1>

    <form method="POST" action="{{ route('admin.members.search') }}" class="mb-6 rounded-md border border-stone-200 bg-white p-4">
        @csrf

        <label for="q" class="mb-1 block text-sm font-medium text-stone-800">{{ __('Search by name or email') }}</label>
        <input id="q" name="q" type="search" value="{{ $filter['q'] }}" maxlength="100"
            class="mb-4 block w-full rounded-md border border-stone-400 bg-white px-3 py-2 text-base focus:outline-2 focus:outline-offset-1 focus:outline-sky-700">

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="status" class="mb-1 block text-sm font-medium text-stone-800">{{ __('Status') }}</label>
                <select id="status" name="status" class="block w-full rounded-md border border-stone-400 bg-white px-3 py-2 text-base">
                    <option value="">{{ __('All') }}</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected($filter['status'] === $status->value)>{{ ucfirst($status->value) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="branch" class="mb-1 block text-sm font-medium text-stone-800">{{ __('Branch') }}</label>
                <select id="branch" name="branch" class="block w-full rounded-md border border-stone-400 bg-white px-3 py-2 text-base">
                    <option value="">{{ __('All') }}</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->code }}" @selected($filter['branch'] === $branch->code)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="icare" class="mb-1 block text-sm font-medium text-stone-800">iCare</label>
                <select id="icare" name="icare" class="block w-full rounded-md border border-stone-400 bg-white px-3 py-2 text-base">
                    <option value="">{{ __('All') }}</option>
                    <option value="{{ $noIcare }}" @selected($filter['icare'] === $noIcare)>{{ __('Belum join iCare') }}</option>
                    @foreach ($teams as $team)
                        <option value="{{ $team->id }}" @selected($filter['icare'] === (string) $team->id)>iCare {{ $team->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-4 flex gap-3">
            <button type="submit"
                class="min-h-11 rounded-md bg-sky-800 px-5 text-base font-medium text-white hover:bg-sky-900 focus:outline-2 focus:outline-offset-2 focus:outline-sky-700">
                {{ __('Search') }}
            </button>
            @if ($filtered)
                <button type="submit" form="clear-filter"
                    class="min-h-11 rounded-md border border-stone-400 bg-white px-5 text-base font-medium text-stone-900 hover:bg-stone-100">
                    {{ __('Clear') }}
                </button>
            @endif
        </div>
    </form>

    <form id="clear-filter" method="POST" action="{{ route('admin.members.search') }}">@csrf</form>

    <p class="mb-3 text-sm text-stone-600" data-testid="count">
        {{ trans_choice(':count member|:count members', $members->total(), ['count' => $members->total()]) }}
    </p>

    @if ($members->isEmpty())
        <div class="rounded-md border border-stone-200 bg-white p-6 text-center text-stone-700">
            @if ($filtered)
                <p>{{ __('No member matches this search.') }}</p>
                <button type="submit" form="clear-filter" class="mt-2 text-sky-800 underline">{{ __('Show everyone') }}</button>
            @else
                <p>{{ __('Nobody has registered yet.') }}</p>
                <a href="{{ route('register', ['manual' => 1]) }}" class="mt-2 inline-block text-sky-800 underline">{{ __('Open the registration form') }}</a>
            @endif
        </div>
    @else
        <ul class="divide-y divide-stone-200 rounded-md border border-stone-200 bg-white">
            @foreach ($members as $member)
                <li>
                    <a href="{{ route('admin.members.edit', $member) }}"
                        class="flex min-h-14 flex-wrap items-center justify-between gap-x-4 gap-y-1 px-4 py-3 hover:bg-stone-50 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700">
                        <span>
                            <span class="block text-base font-medium text-stone-900">
                                {{ $member->full_name }}@if ($member->chinese_name) <span class="font-normal text-stone-600">{{ $member->chinese_name }}</span>@endif
                            </span>
                            <span class="block text-sm text-stone-600">
                                {{ $member->branch->name }} · {{ $member->cellGroup ? 'iCare '.$member->cellGroup->name : __('Belum join iCare') }}
                            </span>
                        </span>
                        <span class="flex gap-2 text-xs font-medium">
                            <span class="rounded-full bg-stone-100 px-2.5 py-1 text-stone-800">{{ $member->category->label() }}</span>
                            <span @class([
                                'rounded-full px-2.5 py-1',
                                'bg-emerald-100 text-emerald-900' => $member->status->value === 'active',
                                'bg-amber-100 text-amber-900' => $member->status->value === 'visitor',
                                'bg-stone-200 text-stone-800' => $member->status->value === 'inactive',
                            ])>{{ ucfirst($member->status->value) }}</span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="mt-4">{{ $members->links() }}</div>
    @endif
@endsection
