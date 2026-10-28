@extends('layouts.app')

@section('title', __('Find by name').' — '.config('app.name'))

@section('content')
    <p class="mb-2 text-sm"><a href="{{ route('usher.scan') }}" class="text-sky-800 underline">← {{ __('Back to scanning') }}</a></p>
    <h1 class="mb-1 text-2xl font-semibold">{{ __('Find by name') }}</h1>
    <p class="mb-4 text-sm text-stone-600">{{ $branch->name }} · {{ __('Sunday Service') }}</p>

    @if ($ticked = session('ticked'))
        <div role="status" @class([
            'mb-4 rounded-md border-2 p-4 text-lg font-medium',
            'border-emerald-400 bg-emerald-50 text-emerald-950' => $ticked['status'] === 'recorded',
            'border-amber-400 bg-amber-50 text-amber-950' => $ticked['status'] === 'already',
        ])>
            {{ $ticked['status'] === 'recorded' ? __('Recorded') : __('Already recorded') }}: {{ $ticked['name'] }}
        </div>
    @endif

    <form method="POST" action="{{ route('usher.roster.search') }}" class="mb-6">
        @csrf
        <label for="q" class="mb-1 block text-sm font-medium text-stone-800">{{ __('Name') }}</label>
        <div class="flex gap-3">
            <input id="q" name="q" type="search" value="{{ $q }}" maxlength="100" autocomplete="off"
                class="block min-h-12 w-full rounded-md border border-stone-400 bg-white px-3 text-base focus:outline-2 focus:outline-offset-1 focus:outline-sky-700">
            <button type="submit"
                class="min-h-12 rounded-md bg-sky-800 px-5 text-base font-medium text-white hover:bg-sky-900 focus:outline-2 focus:outline-offset-2 focus:outline-sky-700">
                {{ __('Search') }}
            </button>
        </div>
        <p class="mt-1 text-sm text-stone-600">{{ __('Type at least two letters.') }}</p>
    </form>

    @if (mb_strlen($q) < 2)
        <p class="rounded-md border border-stone-200 bg-white p-6 text-center text-stone-700">{{ __('Search for a member to tick them.') }}</p>
    @elseif ($members->isEmpty())
        <div class="rounded-md border border-stone-200 bg-white p-6 text-center text-stone-700">
            <p>{{ __('Nobody with that name is registered.') }}</p>
            <a href="{{ route('register', ['manual' => 1]) }}" class="mt-2 inline-block text-sky-800 underline">{{ __('They can register here') }}</a>
        </div>
    @else
        <ul class="divide-y divide-stone-200 rounded-md border border-stone-200 bg-white">
            @foreach ($members as $member)
                <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <span>
                        <span class="block text-base font-medium">{{ $member->full_name }}@if ($member->chinese_name) <span class="font-normal text-stone-600">{{ $member->chinese_name }}</span>@endif</span>
                        <span class="block text-sm text-stone-600">{{ $member->cellGroup ? 'iCare '.$member->cellGroup->name : __('Belum join iCare') }}</span>
                    </span>

                    @if ($row = $recorded->get($member->id))
                        <span class="rounded-full bg-emerald-100 px-3 py-1.5 text-sm font-medium text-emerald-900">
                            {{ __('Recorded') }} {{ $row->recorded_at->timezone('Asia/Taipei')->format('H:i') }} · {{ ucfirst($row->mode->value) }}
                        </span>
                    @else
                        <form method="POST" action="{{ route('usher.roster.tick') }}" class="flex gap-2">
                            @csrf
                            <input type="hidden" name="member" value="{{ $member->public_ref }}">
                            <button type="submit" name="mode" value="onsite"
                                class="min-h-11 rounded-md bg-sky-800 px-4 text-sm font-medium text-white hover:bg-sky-900">{{ __('Onsite') }}</button>
                            <button type="submit" name="mode" value="online"
                                class="min-h-11 rounded-md border border-stone-400 bg-white px-4 text-sm font-medium text-stone-900 hover:bg-stone-100">{{ __('Online') }}</button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
@endsection
