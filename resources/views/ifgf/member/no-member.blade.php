@extends('ifgf.layout')
@section('title', __('ifgf.no_member_title'))
@section('site', 'Member')

@section('content')
<div class="card empty" style="margin-top:40px">
    <h1 style="font-size:19px">{{ __('ifgf.no_member_title') }}</h1>
    <p class="sub" style="margin-top:8px">{{ __('ifgf.no_member_help') }}</p>
</div>
@endsection
