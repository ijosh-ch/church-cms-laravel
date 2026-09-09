@extends('ifgf.layout')
@section('title', __('ifgf.roster'))
@section('site', 'Usher')
@include('ifgf._nav-usher')

@section('styles')
.person .tick {
    width: 30px; height: 30px; flex: none; border-radius: 8px;
    border: 2px solid var(--border); background: var(--surface);
    display: grid; place-items: center; cursor: pointer; padding: 0;
}
/* The whole row is the target on a phone, not just the 30px box. */
.person { cursor: pointer; -webkit-tap-highlight-color: transparent; }
.person.is-present .tick { background: var(--ok); border-color: var(--ok); color: #fff; }
.person .tick svg { width: 17px; height: 17px; opacity: 0; }
.person.is-present .tick svg { opacity: 1; }
.person.is-busy { opacity: .5; pointer-events: none; }
@endsection

@section('content')
<div class="page-head">
    <h1>{{ __('ifgf.roster') }}</h1>
    <p class="sub">{{ __('ifgf.roster_help') }}</p>
</div>

@if ($occurrence === null)
    <div class="card empty">{{ __('ifgf.no_occurrence') }}</div>
@else
    <form method="GET" class="card" style="margin-bottom:14px">
        <div class="toolbar">
            <div class="field">
                <label for="branch">{{ __('ifgf.branch') }}</label>
                <select name="branch" id="branch" onchange="this.form.submit()">
                    @foreach ($branches as $b)
                        <option value="{{ $b->id }}" @selected($branchId === $b->id)>{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="occurrence">{{ __('ifgf.service') }}</label>
                <select name="occurrence" id="occurrence" onchange="this.form.submit()">
                    @foreach ($occurrences as $o)
                        <option value="{{ $o->id }}" @selected($occurrence->id === $o->id)>
                            {{ $o->service_date->format('D, d M Y') }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                {{-- "Not in any iCare" is a real, selectable state — the absence of a
                     membership row, never a group called "Belum mengikuti". --}}
                <label for="group">{{ __('ifgf.icare') }}</label>
                <select name="group" id="group" onchange="this.form.submit()">
                    <option value="">{{ __('ifgf.all_icare') }}</option>
                    @foreach ($groups as $g)
                        <option value="{{ $g->id }}" @selected($groupId === $g->id)>{{ $g->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="q">{{ __('ifgf.search') }}</label>
                <input type="search" name="q" id="q" value="{{ $search }}" placeholder="{{ __('ifgf.search_name') }}">
            </div>

            <button class="btn btn-primary">{{ __('ifgf.apply') }}</button>
        </div>
    </form>

    @if ($occurrence->is_locked)
        <div class="flash flash-err">{{ __('ifgf.locked_notice') }}</div>
    @endif

    <div class="grid grid-stats" style="margin-bottom:14px">
        <div class="card stat">
            <div class="label">{{ __('ifgf.present') }}</div>
            <div class="value" id="present-count">{{ $roster->whereNotNull('attendance_status')->count() }}</div>
            <div class="foot">{{ __('ifgf.of_n', ['n' => $roster->count()]) }}</div>
        </div>
        <div class="card stat">
            <div class="label">{{ __('ifgf.service') }}</div>
            <div class="value" style="font-size:20px">{{ $occurrence->service_date->format('d M') }}</div>
            <div class="foot">{{ $occurrence->branch->name }}</div>
        </div>
    </div>

    <div class="table-wrap" style="overflow:hidden">
        @forelse ($roster as $m)
            @php $present = $m->attendance_status !== null; @endphp
            <div class="person {{ $present ? 'is-present' : '' }}"
                 data-url="{{ route('usher.roster.toggle', [$occurrence, $m]) }}"
                 role="button" tabindex="0"
                 aria-pressed="{{ $present ? 'true' : 'false' }}">
                <div class="avatar">{{ mb_substr($m->full_name, 0, 1) }}</div>
                <div class="person-main">
                    <div class="person-name">{{ $m->full_name }}</div>
                    <div class="person-meta">
                        {{ $m->category?->name ?? __('ifgf.no_category') }}
                        ·
                        {{ $m->currentGroupMembership->first()?->group?->name ?? __('ifgf.no_icare') }}
                    </div>
                </div>
                <button type="button" class="tick" aria-hidden="true" tabindex="-1">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
                         stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                </button>
            </div>
        @empty
            <div class="empty">{{ __('ifgf.no_members') }}</div>
        @endforelse
    </div>
@endif
@endsection

@section('scripts')
<script>
@php
    $msgLocked = __('ifgf.locked_notice');
    $msgNetwork = __('ifgf.network_error');
@endphp

const csrf = document.querySelector('meta[name=csrf-token]').content;
const counter = document.getElementById('present-count');
const MSG_LOCKED = @json($msgLocked);
const MSG_NETWORK = @json($msgNetwork);

async function toggle(row) {
    if (row.classList.contains('is-busy')) return;
    row.classList.add('is-busy');

    try {
        const r = await fetch(row.dataset.url, {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'}
        });
        const d = await r.json();

        if (d.status === 'added' || d.status === 'removed') {
            const present = d.status === 'added';
            row.classList.toggle('is-present', present);
            row.setAttribute('aria-pressed', present ? 'true' : 'false');
            if (counter) counter.textContent = Number(counter.textContent) + (present ? 1 : -1);
            if (navigator.vibrate) navigator.vibrate(20);
        } else {
            // 'locked' — the occurrence has been reported on and must not change.
            alert(MSG_LOCKED);
        }
    } catch (e) {
        alert(MSG_NETWORK);
    } finally {
        row.classList.remove('is-busy');
    }
}

document.querySelectorAll('.person[data-url]').forEach(function (row) {
    row.addEventListener('click', function () { toggle(row); });
    row.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(row); }
    });
});
</script>
@endsection
