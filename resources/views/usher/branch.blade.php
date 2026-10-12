@extends('layouts.app')

@section('title', __('Choose the branch').' — '.config('app.name'))

@section('content')
    <h1 class="mb-1 text-2xl font-semibold">{{ __('Where are you scanning?') }}</h1>
    <p class="mb-6 text-base text-stone-600">{{ __('Every scan is counted for the branch you choose here.') }}</p>

    @error('branch')
        <div class="mb-6 rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ $message }}</div>
    @enderror

    <form method="POST" action="{{ route('usher.branch.store') }}" class="grid gap-4">
        @csrf
        @foreach ($branches as $branch)
            <button type="submit" name="branch" value="{{ $branch->code }}"
                class="min-h-24 w-full rounded-md border-2 border-sky-800 bg-white px-4 py-6 text-2xl font-semibold text-sky-900 hover:bg-sky-50 focus:outline-2 focus:outline-offset-2 focus:outline-sky-700">
                {{ $branch->name }}@if ($branch->name_zh) <span class="block text-lg font-normal text-stone-600">{{ $branch->name_zh }}</span>@endif
            </button>
        @endforeach
    </form>
@endsection
