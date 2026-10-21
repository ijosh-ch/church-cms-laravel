@extends('layouts.app')

@section('title', __('My profile').' — '.config('app.name'))

@section('content')
    <h1 class="mb-1 text-2xl font-semibold">{{ __('My profile') }}</h1>
    <p class="mb-6 text-sm text-stone-500">{{ __('Change your details here. Your email cannot be changed.') }}</p>

    @if ($member->needsProfile() && ! $errors->any())
        <x-alert class="mb-6" :title="__('A few details are still missing')">
            {{ __('Fill in where you live in Taiwan, your work or study, and the rest below, then save.') }}
        </x-alert>
    @endif

    @if (session('saved'))
        <div class="mb-6 rounded-md border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-900" role="status">
            {{ __('Saved. If your name, birthday or iCare changed, the birthday calendar is being updated.') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-800" role="alert">
            {{ __('Nothing was saved. Please check the highlighted fields.') }}
        </div>
    @endif

    <form method="POST" action="{{ route('profile.update') }}" novalidate>
        @csrf
        @method('PUT')

        <div class="mb-5">
            <p class="mb-1 block text-sm font-medium text-stone-800">{{ __('Email') }}</p>
            <p class="rounded-md border border-stone-200 bg-stone-100 px-3 py-2 text-base text-stone-700" data-testid="email">
                {{ $member->email ?? '—' }}
            </p>
        </div>

        <x-form.field name="full_name" :label="__('Full Name')" :value="$member->full_name" required
            :hint="__('As on your passport or ARC.')" autocomplete="name" />

        <x-form.field name="chinese_name" :label="__('Chinese Name')" :value="$member->chinese_name" />

        <x-form.field name="birthday" type="date" :label="__('Tanggal Lahir')" :value="$member->birthday?->toDateString()" required
            autocomplete="bday" />

        <x-form.field name="domicile_taiwan" :label="__('Domisili Taiwan')" :value="$member->domicile_taiwan" required />

        <x-form.field name="line_id" :label="__('Line ID')" :value="$member->line_id" />

        <x-form.field name="phone" type="tel" :label="__('WhatsApp Number')" :value="$member->phone ? '+'.$member->phone : ''" required
            autocomplete="tel" inputmode="tel" />

        <fieldset class="mb-5">
            <legend class="mb-1 text-sm font-medium text-stone-800">
                {{ __('Domisili Gereja IFGF') }} <span class="text-red-700" aria-hidden="true">*</span>
            </legend>
            @foreach ($branches as $branch)
                <label class="flex items-center gap-2 py-1 text-base">
                    <input type="radio" name="branch" value="{{ $branch->code }}" class="size-4"
                        @checked(old('branch', $member->branch->code) === $branch->code)>
                    {{ $branch->name }}@if ($branch->name_zh) / {{ $branch->name_zh }}@endif
                </label>
            @endforeach
            @error('branch')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </fieldset>

        <fieldset class="mb-5">
            <legend class="mb-1 text-sm font-medium text-stone-800">{{ __('Profesi saat ini') }}</legend>
            @foreach ($occupations as $value => $label)
                <label class="flex items-center gap-2 py-1 text-base">
                    <input type="radio" name="occupation" value="{{ $value }}" class="size-4"
                        @checked(old('occupation', $occupation) === $value)>
                    {{ __($label) }}
                </label>
            @endforeach
            @error('occupation')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </fieldset>

        <x-form.field name="occupation_other" :label="__('If other, please specify')" :value="$occupationOther" maxlength="100" />

        <div class="mb-5">
            <label for="education_level" class="mb-1 block text-sm font-medium text-stone-800">
                {{ __('Tingkat Pendidikan') }}
            </label>
            <select id="education_level" name="education_level"
                class="block w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-base shadow-xs focus:outline-2 focus:outline-offset-1 focus:outline-sky-700">
                <option value="">{{ __('— choose —') }}</option>
                @foreach ($educationLevels as $level)
                    <option value="{{ $level->value }}" @selected(old('education_level', $member->education_level?->value) === $level->value)>
                        {{ $level->label() }}
                    </option>
                @endforeach
            </select>
            @error('education_level')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <x-form.field name="school_or_company" :label="__('Sekolah / Kampus / Perusahaan')" :value="$member->school_or_company"
            :hint="__('Where you study or work in Taiwan.')" />

        <div class="mb-6">
            <label for="icare" class="mb-1 block text-sm font-medium text-stone-800">{{ __('iCare') }}</label>
            <select id="icare" name="icare"
                class="block w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-base shadow-xs focus:outline-2 focus:outline-offset-1 focus:outline-sky-700">
                <option value="{{ $noIcare }}" @selected(old('icare', $member->icare_team_id === null ? $noIcare : (string) $member->icare_team_id) === $noIcare)>
                    {{ __('Belum mengikuti') }}
                </option>
                @foreach ($teams as $team)
                    <option value="{{ $team->id }}" @selected(old('icare', (string) $member->icare_team_id) === (string) $team->id)>
                        iCare {{ $team->name }}
                    </option>
                @endforeach
            </select>
            @error('icare')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit"
            class="min-h-12 w-full rounded-md bg-sky-800 px-4 py-3 text-base font-medium text-white hover:bg-sky-900 focus:outline-2 focus:outline-offset-2 focus:outline-sky-700">
            {{ __('Save changes') }}
        </button>
    </form>

    <div class="mt-8 rounded-md border border-stone-200 bg-white p-4 text-sm text-stone-700">
        <p class="font-medium">{{ __('Birthday calendar') }}</p>
        <p class="mt-1" data-testid="calendar-status">
            @if ($member->calendar_event_id && $member->calendar_synced_at)
                {{ __('Last updated :when.', ['when' => $member->calendar_synced_at->timezone('Asia/Taipei')->format('D j M Y, H:i')]) }}
            @else
                {{ __('Not in the calendar yet.') }}
            @endif
        </p>
    </div>
@endsection
