@extends('ifgf.layout')
@section('title', __('ifgf.reports'))
@section('site', 'Usher')
@include('ifgf._nav-usher')

@section('styles')
.bar-row { display: flex; align-items: center; gap: 10px; padding: 7px 0; }
.bar-label { flex: 0 0 92px; font-size: 13px; color: var(--fg-muted); }
.bar-track { flex: 1; height: 10px; border-radius: 999px; background: var(--surface-2); overflow: hidden; }
.bar-fill { height: 100%; border-radius: 999px; background: var(--accent); }
.bar-value { flex: 0 0 40px; text-align: right; font-variant-numeric: tabular-nums;
             font-weight: 600; font-size: 13px; }
@endsection

@section('content')
<div class="page-head">
    <h1>{{ __('ifgf.quarterly_report') }}</h1>
    <p class="sub">{{ $summary['from'] }} &rarr; {{ $summary['to'] }}</p>
</div>

<form method="GET" class="card" style="margin-bottom:14px">
    <div class="toolbar">
        <div class="field">
            <label for="year">{{ __('ifgf.year') }}</label>
            <select name="year" id="year" onchange="this.form.submit()">
                @foreach (range(now()->year + 1, now()->year - 4) as $y)
                    <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="quarter">{{ __('ifgf.quarter') }}</label>
            <select name="quarter" id="quarter" onchange="this.form.submit()">
                @foreach ([1, 2, 3, 4] as $q)
                    <option value="{{ $q }}" @selected($quarter === $q)>Q{{ $q }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-primary">{{ __('ifgf.apply') }}</button>
    </div>
</form>

<div class="grid grid-stats">
    <div class="card stat">
        <div class="label">{{ __('ifgf.total_attendance') }}</div>
        <div class="value">{{ $summary['grand_total'] }}</div>
        <div class="foot">{{ $summary['quarter'] }}</div>
    </div>
    @foreach ($summary['branches'] as $code => $b)
        <div class="card stat">
            <div class="label">{{ $b['name'] }}</div>
            <div class="value">{{ $b['total'] }}</div>
            <div class="foot">{{ __('ifgf.avg_per_service', ['n' => $b['average'], 's' => $b['services']]) }}</div>
        </div>
    @endforeach
    <div class="card stat">
        <div class="label">{{ __('ifgf.online') }}</div>
        <div class="value">{{ $modes['online'] ?? 0 }}</div>
        <div class="foot">{{ __('ifgf.onsite') }} {{ $modes['onsite'] ?? 0 }}</div>
    </div>
</div>

<div class="card" style="margin-top:14px">
    <h2>{{ __('ifgf.by_category') }}</h2>

    {{-- The shape of the legacy 'Summary Absen' block: categories down, branches across.
         Wrapped in .table-wrap so a phone scrolls the table, never the page. --}}
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>{{ __('ifgf.category') }}</th>
                    @foreach ($summary['branches'] as $code => $b)
                        <th class="num">{{ $code }}</th>
                    @endforeach
                    <th class="num">{{ __('ifgf.total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($summary['totals'] as $categoryCode => $total)
                    <tr>
                        <td>{{ __('ifgf.cat_' . $categoryCode) }}</td>
                        @foreach ($summary['branches'] as $b)
                            <td class="num">{{ $b['categories'][$categoryCode] ?? 0 }}</td>
                        @endforeach
                        <td class="num"><strong>{{ $total }}</strong></td>
                    </tr>
                @endforeach

                @php $uncat = collect($summary['branches'])->sum('uncategorised'); @endphp
                @if ($uncat > 0)
                    <tr>
                        {{-- Guests and members with no category. Counted in the total but
                             never folded into a category, or the report would stop
                             agreeing with the roster. --}}
                        <td>{{ __('ifgf.uncategorised') }}</td>
                        @foreach ($summary['branches'] as $b)
                            <td class="num">{{ $b['uncategorised'] }}</td>
                        @endforeach
                        <td class="num"><strong>{{ $uncat }}</strong></td>
                    </tr>
                @endif

                <tr>
                    <td><strong>{{ __('ifgf.total') }}</strong></td>
                    @foreach ($summary['branches'] as $b)
                        <td class="num"><strong>{{ $b['total'] }}</strong></td>
                    @endforeach
                    <td class="num"><strong>{{ $summary['grand_total'] }}</strong></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="card" style="margin-top:14px">
    <h2>{{ __('ifgf.week_by_week') }}</h2>

    @php $peak = collect($weeks)->max('total') ?: 1; @endphp

    @forelse ($weeks as $w)
        <div class="bar-row">
            <div class="bar-label">{{ \Illuminate\Support\Carbon::parse($w['date'])->format('d M') }}</div>
            <div class="bar-track">
                <div class="bar-fill" style="width: {{ round($w['total'] / $peak * 100) }}%"></div>
            </div>
            <div class="bar-value">{{ $w['total'] }}</div>
        </div>
    @empty
        <div class="empty">{{ __('ifgf.no_data_quarter') }}</div>
    @endforelse
</div>
@endsection
