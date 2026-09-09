@extends('ifgf.layout')
@section('title', __('ifgf.my_code'))
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

@section('styles')
.qr-card { text-align: center; }
/* White plate behind the code regardless of theme — scanners need the contrast, and a
   dark-mode QR on a dark card is unreadable to a camera. */
.qr-plate { display: inline-block; background: #fff; padding: 16px; border-radius: 16px; }
.qr-plate svg { display: block; width: min(260px, 62vw); height: auto; }
.short-code { font: 700 26px/1 ui-monospace, "SF Mono", Menlo, Consolas, monospace;
              letter-spacing: .14em; margin-top: 6px; }
@endsection

@section('content')
<div class="page-head">
    <h1>{{ __('ifgf.my_code') }}</h1>
    <p class="sub">{{ __('ifgf.qr_help') }}</p>
</div>

<div class="card qr-card">
    <div class="qr-plate">{!! $svg !!}</div>

    <div style="margin-top:16px">
        <div class="sub" style="font-size:12px;text-transform:uppercase;letter-spacing:.06em;font-weight:600">
            {{ __('ifgf.short_code') }}
        </div>
        {{-- The human fallback when a camera will not read. 40 bits, so the lookup route
             is throttled hard server-side. --}}
        <div class="short-code">{{ $credential->short_code }}</div>
    </div>

    <div style="margin-top:14px">
        <span class="badge badge-accent">{{ __('ifgf.version') }} {{ $credential->version }}</span>
        <span class="badge">{{ $credential->issued_at?->format('d M Y') }}</span>
    </div>
</div>

<div class="card" style="margin-top:14px">
    <h2>{{ __('ifgf.about_your_code') }}</h2>
    {{-- Worth saying plainly to members: this is the visible half of replacing a QR that
         used to carry their email, phone, name and iCare group in readable text. --}}
    <p class="sub">{{ __('ifgf.qr_privacy_note') }}</p>
    <p class="sub" style="margin-top:10px">{{ __('ifgf.qr_lost_note') }}</p>
</div>
@endsection
