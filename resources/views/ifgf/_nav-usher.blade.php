@php $r = Route::currentRouteName(); @endphp

@section('nav-desktop')
    <a href="{{ route('usher.scan') }}"    @if($r==='usher.scan') aria-current="page" @endif>{{ __('ifgf.scan') }}</a>
    <a href="{{ route('usher.roster') }}"  @if($r==='usher.roster') aria-current="page" @endif>{{ __('ifgf.roster') }}</a>
    <a href="{{ route('usher.reports') }}" @if($r==='usher.reports') aria-current="page" @endif>{{ __('ifgf.reports') }}</a>
@endsection

@section('nav-mobile')
    <a href="{{ route('usher.scan') }}" @if($r==='usher.scan') aria-current="page" @endif>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M3 12h18"/></svg>
        {{ __('ifgf.scan') }}
    </a>
    <a href="{{ route('usher.roster') }}" @if($r==='usher.roster') aria-current="page" @endif>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01"/></svg>
        {{ __('ifgf.roster') }}
    </a>
    <a href="{{ route('usher.reports') }}" @if($r==='usher.reports') aria-current="page" @endif>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 3v18h18M7 15l4-4 3 3 5-6"/></svg>
        {{ __('ifgf.reports') }}
    </a>
@endsection
