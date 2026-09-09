@extends('ifgf.layout')
@section('title', __('ifgf.my_church'))
@section('site', 'Member')

@php $r = Route::currentRouteName(); $q = request('member') ? ['member' => request('member')] : []; @endphp

@section('nav-desktop')
    <a href="{{ route('member.home', $q) }}" @if($r==='member.home') aria-current="page" @endif>{{ __('ifgf.home') }}</a>
    <a href="{{ route('member.qr', $q) }}"   @if($r==='member.qr') aria-current="page" @endif>{{ __('ifgf.my_code') }}</a>
@endsection

@section('nav-mobile')
    <a href="{{ route('member.home', $q) }}" @if($r==='member.home') aria-current="page" @endif>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m3 10 9-7 9 7v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
        {{ __('ifgf.home') }}
    </a>
    <a href="{{ route('member.qr', $q) }}" @if($r==='member.qr') aria-current="page" @endif>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h3v3h-3zM19 19h2v2h-2z"/></svg>
        {{ __('ifgf.my_code') }}
    </a>
@endsection

@section('content')
<div class="page-head">
    <h1>{{ __('ifgf.hello', ['name' => explode(' ', $member->full_name)[0]]) }}</h1>
    <p class="sub">{{ $member->branch?->name }} · {{ $member->category?->name ?? __('ifgf.no_category') }}</p>
</div>

@if ($allMembers->isNotEmpty())
    {{-- Prototype only: pick which demo member you are looking at. Disappears the moment
         Google / Apple sign-in lands, because the member will come from the session. --}}
    <form method="GET" class="card" style="margin-bottom:14px">
        <div class="field">
            <label for="member">{{ __('ifgf.viewing_as') }}</label>
            <select name="member" id="member" onchange="this.form.submit()">
                @foreach ($allMembers as $m)
                    <option value="{{ $m->public_ref }}" @selected($m->id === $member->id)>{{ $m->full_name }}</option>
                @endforeach
            </select>
        </div>
    </form>
@endif

<div class="grid grid-stats">
    <div class="card stat">
        <div class="label">{{ __('ifgf.this_quarter') }}</div>
        <div class="value">{{ $attendedThisQuarter }}</div>
        <div class="foot">{{ __('ifgf.services_attended') }}</div>
    </div>
    <div class="card stat">
        <div class="label">{{ __('ifgf.icare') }}</div>
        <div class="value" style="font-size:17px;line-height:1.35">
            {{ $group?->name ?? __('ifgf.no_icare') }}
        </div>
        <div class="foot">
            @if ($group === null)
                {{ __('ifgf.join_icare_hint') }}
            @else
                {{ $group->branch?->name }}
            @endif
        </div>
    </div>
    <div class="card stat">
        <div class="label">{{ __('ifgf.birthday') }}</div>
        <div class="value" style="font-size:17px;line-height:1.35">
            {{ $member->birthday?->format('d M') ?? '—' }}
        </div>
        <div class="foot">{{ $member->birthday ? __('ifgf.on_calendar') : __('ifgf.no_birthday') }}</div>
    </div>
</div>

<div class="grid grid-2" style="margin-top:14px">
    <div class="card">
        <h2>{{ __('ifgf.recent_attendance') }}</h2>
        @forelse ($attendance as $a)
            <div class="person" style="padding-left:0;padding-right:0">
                <div class="person-main">
                    <div class="person-name">{{ $a->occurrence->service_date->format('D, d M Y') }}</div>
                    <div class="person-meta">{{ $a->occurrence->branch?->name }}</div>
                </div>
                <span class="badge {{ $a->mode === 'online' ? 'badge-accent' : 'badge-ok' }}">
                    {{ __('ifgf.' . $a->mode) }}
                </span>
            </div>
        @empty
            <div class="empty">{{ __('ifgf.no_attendance_yet') }}</div>
        @endforelse
    </div>

    <div class="card">
        <h2>{{ __('ifgf.my_details') }}</h2>
        <div class="table-wrap" style="border:0">
            <table>
                <tbody>
                    <tr><th>{{ __('ifgf.name') }}</th><td>{{ $member->full_name }}</td></tr>
                    @if ($member->chinese_name)
                        <tr><th>{{ __('ifgf.chinese_name') }}</th><td>{{ $member->chinese_name }}</td></tr>
                    @endif
                    <tr><th>{{ __('ifgf.branch') }}</th><td>{{ $member->branch?->name }}</td></tr>
                    <tr><th>{{ __('ifgf.category') }}</th><td>{{ $member->category?->name ?? '—' }}</td></tr>
                    @foreach ($member->contactPoints as $c)
                        <tr><th>{{ __('ifgf.contact_' . $c->type) }}</th><td>{{ $c->value }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <a class="btn btn-primary btn-block" style="margin-top:14px" href="{{ route('member.qr', $q) }}">
            {{ __('ifgf.show_my_code') }}
        </a>
    </div>
</div>
@endsection
