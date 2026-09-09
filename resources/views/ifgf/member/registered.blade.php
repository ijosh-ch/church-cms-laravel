@extends('ifgf.layout')
@section('title', __('ifgf.welcome_title'))
@section('site', 'Member')

@section('styles')
.qr-plate { display: inline-block; background: #fff; padding: 16px; border-radius: 16px; }
.qr-plate svg { display: block; width: min(240px, 60vw); height: auto; }
.short-code { font: 700 24px/1 ui-monospace, "SF Mono", Menlo, Consolas, monospace;
              letter-spacing: .14em; margin-top: 6px; }
.tick-big { width: 52px; height: 52px; border-radius: 50%; background: var(--ok-soft);
            color: var(--ok); display: grid; place-items: center; margin: 0 auto 14px; }
@endsection

@section('content')
<div class="card" style="text-align:center;margin-top:24px">
    <div class="tick-big">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
    </div>

    <h1 style="font-size:21px">{{ __('ifgf.welcome_title') }}</h1>
    <p class="sub">{{ __('ifgf.welcome_name', ['name' => $member->full_name]) }}</p>
    <p class="sub" style="margin-top:4px">
        {{ $member->branch?->name }}@if($member->category) · {{ $member->category->name }}@endif
    </p>
</div>

<div class="card" style="text-align:center;margin-top:14px">
    <h2>{{ __('ifgf.your_attendance_code') }}</h2>

    <div class="qr-plate">{!! $svg !!}</div>

    <div style="margin-top:14px">
        <div class="sub" style="font-size:12px;text-transform:uppercase;letter-spacing:.06em;font-weight:600">
            {{ __('ifgf.short_code') }}
        </div>
        <div class="short-code">{{ $credential->short_code }}</div>
    </div>

    <p class="sub" style="margin-top:14px">{{ __('ifgf.save_your_code') }}</p>

    <a class="btn btn-primary btn-block" style="margin-top:14px"
       href="{{ route('member.home', ['member' => $member->public_ref]) }}">
        {{ __('ifgf.go_to_my_page') }}
    </a>
</div>

<div class="card" style="margin-top:14px">
    <h2>{{ __('ifgf.about_your_code') }}</h2>
    <p class="sub">{{ __('ifgf.qr_privacy_note') }}</p>
    <p class="sub" style="margin-top:10px">{{ __('ifgf.qr_lost_note') }}</p>
</div>
@endsection
