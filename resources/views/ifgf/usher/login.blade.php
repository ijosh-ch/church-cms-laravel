@extends('ifgf.layout')
@section('title', __('usher.sign_in'))
@section('site', 'Usher')

@section('content')
<div style="max-width:380px;margin:44px auto">
    <div class="card">
        <h1 style="font-size:20px;margin-bottom:4px">{{ __('usher.sign_in') }}</h1>
        <p class="sub" style="margin-bottom:18px">{{ __('ifgf.usher_login_help') }}</p>

        @foreach ($errors->all() as $error)
            <div class="flash flash-err">{{ $error }}</div>
        @endforeach

        <form method="POST" action="{{ route('usher.login.store') }}">
            @csrf
            <div class="field">
                <label for="email">{{ __('ifgf.email') }}</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            </div>
            <div class="field">
                <label for="password">{{ __('ifgf.password') }}</label>
                <input id="password" name="password" type="password" required autocomplete="current-password">
            </div>
            <button class="btn btn-primary btn-block" style="margin-top:18px">{{ __('usher.sign_in') }}</button>
        </form>

        {{-- No registration link, by design. Usher accounts are provisioned by an
             administrator; a church door station is not a place anyone signs up. --}}
    </div>
</div>
@endsection
