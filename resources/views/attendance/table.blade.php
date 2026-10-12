@extends('layouts.app')

@section('title', __('Attendance').' — '.config('app.name'))

@section('content')
    <h1 class="mb-4 text-2xl font-semibold">{{ __('Attendance') }}</h1>

    <form method="GET" action="{{ route('usher.attendance') }}" class="mb-6 grid gap-4 rounded-md border border-stone-200 bg-white p-4 sm:grid-cols-3 sm:items-end">
        <div>
            <label for="date" class="mb-1 block text-sm font-medium text-stone-800">{{ __('Date') }}</label>
            <input id="date" name="date" type="date" value="{{ $date->format('Y-m-d') }}"
                class="block min-h-11 w-full rounded-md border border-stone-400 bg-white px-3 text-base">
        </div>
        <div>
            <label for="branch" class="mb-1 block text-sm font-medium text-stone-800">{{ __('Branch') }}</label>
            <select id="branch" name="branch" class="block min-h-11 w-full rounded-md border border-stone-400 bg-white px-3 text-base">
                @foreach ($branches as $option)
                    <option value="{{ $option->code }}" @selected($option->is($branch))>{{ $option->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit"
            class="min-h-11 rounded-md bg-sky-800 px-5 text-base font-medium text-white hover:bg-sky-900 focus:outline-2 focus:outline-offset-2 focus:outline-sky-700">
            {{ __('Show') }}
        </button>
    </form>

    <h2 class="mb-2 text-lg font-semibold">{{ $branch->name }} · {{ $date->format('D j M Y') }}</h2>

    <dl class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-5" data-testid="totals">
        @foreach ($totals as $key => $total)
            <div @class(['rounded-md border p-3', 'border-sky-800 bg-sky-50' => $key === 'subtotal', 'border-stone-200 bg-white' => $key !== 'subtotal'])>
                <dt class="text-sm text-stone-600">{{ $total['label'] }}</dt>
                <dd class="text-2xl font-semibold tabular-nums" data-total="{{ $key }}">{{ $total['onsite'] }}</dd>
                <dd class="text-sm text-stone-600">{{ __('online') }} <span data-online="{{ $key }}">{{ $total['online'] }}</span></dd>
            </div>
        @endforeach
    </dl>

    @if ($rows->isEmpty())
        <div class="rounded-md border border-stone-200 bg-white p-6 text-center text-stone-700">
            <p>{{ __('Nobody has been recorded for this service yet.') }}</p>
            @can('scan')
                <a href="{{ route('usher.scan') }}" class="mt-2 inline-block text-sky-800 underline">{{ __('Start scanning') }}</a>
            @endcan
        </div>
    @else
        <ul class="divide-y divide-stone-200 rounded-md border border-stone-200 bg-white">
            @foreach ($rows as $row)
                <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-4 py-3">
                    <span>
                        <span class="block text-base font-medium">{{ $row->member?->full_name ?? $row->guest_name ?? '—' }}</span>
                        <span class="block text-sm text-stone-600">
                            {{ $row->member?->icareTeam ? 'iCare '.$row->member->icareTeam->name : ($row->member ? __('Belum mengikuti') : __('Guest')) }}
                            @if ($row->category) · {{ $row->category->label() }}@endif
                        </span>
                    </span>
                    <span class="text-sm text-stone-700">
                        <span class="font-mono tabular-nums">{{ $row->recorded_at->timezone('Asia/Taipei')->format('H:i') }}</span>
                        · {{ ucfirst($row->mode->value) }} · {{ strtoupper($row->method->value) }}
                    </span>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
