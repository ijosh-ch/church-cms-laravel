@extends('layouts.app')

@section('title', __('Registration').' — '.config('app.name'))

@section('content')
    <h1 class="mb-1 text-2xl font-semibold">{{ __('Registration') }}</h1>
    <p class="mb-4 text-sm text-stone-500">{{ __('Step 1 of 2') }}</p>

    <div class="mb-6 rounded-md border border-stone-200 bg-white p-4 text-sm text-stone-700">
        <p class="font-medium">{{ __('What we collect, and why') }}</p>
        <p class="mt-1">
            {{ __('Your name, birthday, contact details, place of study or work and iCare group. They are used only to record your attendance, to remember your birthday and to connect you with an iCare group at IFGF Taipei–Zhongli.') }}
        </p>
    </div>

    @if ($errors->any())
        <div class="mb-6 rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-800" role="alert">
            {{ __('Please check the highlighted fields.') }}
        </div>
    @endif

    @if ($google)
        <div class="mb-6 rounded-md border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900" role="status">
            <p>{{ __('Signed in with Google as :email. Fill in the rest to finish registering.', ['email' => $google['email']]) }}</p>
            <p class="mt-1">
                <a href="{{ route('register', ['manual' => 1]) }}" class="underline">{{ __('Use a different email') }}</a>
            </p>
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}" novalidate>
        @csrf

        <x-form.field name="email" type="email" :label="__('Email')" :value="$saved['email'] ?? ''" required
            autocomplete="email" inputmode="email" :readonly="(bool) $google"
            :hint="$google ? __('Confirmed by Google.') : null" />

        <x-form.field name="full_name" :label="__('Full Name')" :value="$saved['full_name'] ?? ''" required
            :hint="__('As on your passport or ARC.')" autocomplete="name" />

        <x-form.field name="chinese_name" :label="__('Chinese Name')" :value="$saved['chinese_name'] ?? ''" />

        <x-form.field name="birthday" type="date" :label="__('Tanggal Lahir')" :value="$saved['birthday'] ?? ''" required
            autocomplete="bday" />

        <x-form.field name="domicile_taiwan" :label="__('Domisili Taiwan')" :value="$saved['domicile_taiwan'] ?? ''" required
            :hint="__('For example: Taipei - Da\'an')" />

        <x-form.field name="line_id" :label="__('Line ID')" :value="$saved['line_id'] ?? ''" />

        <x-form.field name="phone" type="tel" :label="__('WhatsApp Number')" :value="$saved['phone'] ?? ''" required
            autocomplete="tel" inputmode="tel" />

        <fieldset class="mb-5">
            <legend class="mb-1 text-sm font-medium text-stone-800">
                {{ __('Domisili Gereja IFGF') }} <span class="text-red-700" aria-hidden="true">*</span>
            </legend>
            @foreach ($branches as $branch)
                <label class="flex items-center gap-2 py-1 text-base">
                    <input type="radio" name="branch" value="{{ $branch->code }}" class="size-4"
                        @checked(old('branch', $saved['branch'] ?? '') === $branch->code)>
                    {{ $branch->name }}@if ($branch->name_zh) / {{ $branch->name_zh }}@endif
                </label>
            @endforeach
            @error('branch')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </fieldset>

        <fieldset class="mb-6">
            <legend class="mb-1 text-sm font-medium text-stone-800">
                {{ __('Apakah Anda berencana untuk menghadiri kembali Sunday Gathering?') }}
                <span class="text-red-700" aria-hidden="true">*</span>
            </legend>
            <label class="flex items-center gap-2 py-1 text-base">
                <input type="radio" name="attend_again" value="yes" class="size-4"
                    @checked(old('attend_again', $saved['attend_again'] ?? '') === 'yes')>
                {{ __('Ya') }}
            </label>
            <label class="flex items-center gap-2 py-1 text-base">
                <input type="radio" name="attend_again" value="no" class="size-4"
                    @checked(old('attend_again', $saved['attend_again'] ?? '') === 'no')>
                {{ __('Tidak') }}
            </label>
            @error('attend_again')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </fieldset>

        <button type="submit"
            class="w-full rounded-md bg-sky-800 px-4 py-3 text-base font-medium text-white hover:bg-sky-900 focus:outline-2 focus:outline-offset-2 focus:outline-sky-700">
            {{ __('Next') }}
        </button>
    </form>
@endsection
