@extends('layouts.app')

@section('title', __('Registration').' — '.config('app.name'))

@section('content')
    <h1 class="mb-1 text-2xl font-semibold">{{ __('Registration') }}</h1>
    <p class="mb-6 text-sm text-stone-500">{{ __('Step 2 of 2') }}</p>

    @if ($errors->any())
        <div class="mb-6 rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-800" role="alert">
            {{ __('Please check the highlighted fields.') }}
        </div>
    @endif

    <form method="POST" action="{{ route('register.details') }}" novalidate>
        @csrf

        <fieldset class="mb-5">
            <legend class="mb-1 text-sm font-medium text-stone-800">
                {{ __('Profesi saat ini') }} <span class="text-red-700" aria-hidden="true">*</span>
            </legend>
            @foreach ($occupations as $occupation => $label)
                <label class="flex items-center gap-2 py-1 text-base">
                    <input type="radio" name="occupation" value="{{ $occupation }}" class="size-4"
                        @checked(old('occupation') === $occupation)>
                    {{ __($label) }}
                </label>
            @endforeach
            @error('occupation')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </fieldset>

        <x-form.field name="occupation_other" :label="__('If other, please specify')" maxlength="100" />

        <div class="mb-5">
            <label for="education_level" class="mb-1 block text-sm font-medium text-stone-800">
                {{ __('Tingkat Pendidikan') }}
            </label>
            <select id="education_level" name="education_level"
                class="block w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-base shadow-xs focus:outline-2 focus:outline-offset-1 focus:outline-sky-700">
                <option value="">{{ __('— choose —') }}</option>
                @foreach ($educationLevels as $level)
                    <option value="{{ $level->value }}" @selected(old('education_level') === $level->value)>
                        {{ $level->label() }}
                    </option>
                @endforeach
            </select>
            @error('education_level')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <x-form.field name="school_or_company" :label="__('Sekolah / Kampus / Perusahaan')" required
            :hint="__('Where you study or work in Taiwan.')" />

        <div class="mb-6">
            <label for="icare" class="mb-1 block text-sm font-medium text-stone-800">
                {{ __('iCare') }} <span class="text-red-700" aria-hidden="true">*</span>
            </label>
            <select id="icare" name="icare" required
                class="block w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-base shadow-xs focus:outline-2 focus:outline-offset-1 focus:outline-sky-700">
                <option value="">{{ __('— choose —') }}</option>
                <option value="{{ $noIcare }}" @selected(old('icare') === $noIcare)>{{ __('Belum mengikuti') }}</option>
                @foreach ($teams as $team)
                    <option value="{{ $team->id }}" @selected(old('icare') === (string) $team->id)>
                        iCare {{ $team->name }}
                    </option>
                @endforeach
            </select>
            @error('icare')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit"
            class="w-full rounded-md bg-sky-800 px-4 py-3 text-base font-medium text-white hover:bg-sky-900 focus:outline-2 focus:outline-offset-2 focus:outline-sky-700">
            {{ __('Register') }}
        </button>

        <p class="mt-4 text-center text-sm">
            <a href="{{ route('register') }}" class="text-sky-800 underline">{{ __('Back to step 1') }}</a>
        </p>
    </form>
@endsection
