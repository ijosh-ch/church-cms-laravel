@extends('layouts.app')

@section('title', __('Thank you').' — '.config('app.name'))

@section('content')
    <h1 class="mb-3 text-2xl font-semibold">{{ __('Thank you') }}</h1>

    <p class="text-base text-stone-700">
        {{ __('We have received your registration. Please check your email for your attendance QR. If you registered before, the email has a link to sign in instead.') }}
    </p>
@endsection
