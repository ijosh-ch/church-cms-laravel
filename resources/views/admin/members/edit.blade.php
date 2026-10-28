@extends('layouts.app')

@section('title', $member->full_name.' — '.config('app.name'))

@section('content')
    <p class="mb-2 text-sm"><a href="{{ route('admin.members.index') }}" class="text-sky-800 underline">← {{ __('All members') }}</a></p>
    <h1 class="mb-1 text-2xl font-semibold">{{ $member->full_name }}</h1>
    <p class="mb-6 text-sm text-stone-600">{{ $member->category->label() }} · {{ __('Registered :date', ['date' => $member->created_at->timezone('Asia/Taipei')->format('j M Y')]) }}</p>

    @if (session('saved'))
        <div class="mb-6 rounded-md border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-900" role="status">{{ __('Saved.') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-800" role="alert">
            {{ $errors->has('team_id') ? $errors->first('team_id') : __('Nothing was saved. Please check the highlighted fields.') }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.members.update', $member) }}" novalidate>
        @csrf
        @method('PUT')

        <div class="mb-5">
            <p class="mb-1 block text-sm font-medium text-stone-800">{{ __('Email') }}</p>
            <p class="rounded-md border border-stone-200 bg-stone-100 px-3 py-2 text-base text-stone-700">{{ $member->email ?? '—' }}</p>
            @if ($member->email_verified_at === null)
                <p class="mt-1 text-sm text-amber-800" data-testid="unverified">
                    {{ __('Not verified yet. A team or role takes effect only after this member has signed in with Google.') }}
                </p>
            @endif
        </div>

        <div class="mb-5">
            <label for="status" class="mb-1 block text-sm font-medium text-stone-800">{{ __('Status') }} <span class="text-red-700" aria-hidden="true">*</span></label>
            <select id="status" name="status" required
                class="block w-full rounded-md border border-stone-400 bg-white px-3 py-2 text-base focus:outline-2 focus:outline-offset-1 focus:outline-sky-700">
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(old('status', $member->status->value) === $status->value)>{{ ucfirst($status->value) }}</option>
                @endforeach
            </select>
            @error('status')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <x-form.field name="full_name" :label="__('Full Name')" :value="$member->full_name" required />
        <x-form.field name="chinese_name" :label="__('Chinese Name')" :value="$member->chinese_name" />
        <x-form.field name="birthday" type="date" :label="__('Tanggal Lahir')" :value="$member->birthday?->toDateString()" />
        <x-form.field name="domicile_taiwan" :label="__('Domisili Taiwan')" :value="$member->domicile_taiwan" />
        <x-form.field name="line_id" :label="__('Line ID')" :value="$member->line_id" />
        <x-form.field name="phone" type="tel" :label="__('WhatsApp Number')" :value="$member->phone ? '+'.$member->phone : ''" inputmode="tel" />

        <fieldset class="mb-5">
            <legend class="mb-1 text-sm font-medium text-stone-800">{{ __('Domisili Gereja IFGF') }} <span class="text-red-700" aria-hidden="true">*</span></legend>
            @foreach ($branches as $branch)
                <label class="flex min-h-11 items-center gap-2 text-base">
                    <input type="radio" name="branch" value="{{ $branch->code }}" class="size-4" @checked(old('branch', $member->branch->code) === $branch->code)>
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
                <label class="flex min-h-11 items-center gap-2 text-base">
                    <input type="radio" name="occupation" value="{{ $value }}" class="size-4" @checked(old('occupation', $occupation) === $value)>
                    {{ __($label) }}
                </label>
            @endforeach
            @error('occupation')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </fieldset>

        <x-form.field name="occupation_other" :label="__('If other, please specify')" :value="$occupationOther" maxlength="100" />

        <div class="mb-5">
            <label for="education_level" class="mb-1 block text-sm font-medium text-stone-800">{{ __('Tingkat Pendidikan') }}</label>
            <select id="education_level" name="education_level" class="block w-full rounded-md border border-stone-400 bg-white px-3 py-2 text-base">
                <option value="">{{ __('— choose —') }}</option>
                @foreach ($educationLevels as $level)
                    <option value="{{ $level->value }}" @selected(old('education_level', $member->education_level?->value) === $level->value)>{{ $level->label() }}</option>
                @endforeach
            </select>
            @error('education_level')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <x-form.field name="school_or_company" :label="__('Sekolah / Kampus / Perusahaan')" :value="$member->school_or_company" />

        <div class="mb-6">
            <label for="icare" class="mb-1 block text-sm font-medium text-stone-800">iCare</label>
            <select id="icare" name="icare" class="block w-full rounded-md border border-stone-400 bg-white px-3 py-2 text-base">
                <option value="{{ $noIcare }}" @selected(old('icare', $member->cell_group_id === null ? $noIcare : '') === $noIcare)>{{ __('Belum join iCare') }}</option>
                @foreach ($cellGroups as $team)
                    <option value="{{ $team->id }}" @selected(old('icare', (string) $member->cell_group_id) === (string) $team->id)>iCare {{ $team->name }}</option>
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

    <section class="mt-10" aria-labelledby="teams-heading">
        <h2 id="teams-heading" class="mb-1 text-lg font-semibold">{{ __('Teams and roles') }}</h2>
        <p class="mb-4 text-sm text-stone-600">{{ __('Being in Usher lets a member scan attendance. Being in IT makes them an admin.') }}</p>

        @if (session('teams-saved'))
            <div class="mb-4 rounded-md border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-900" role="status">{{ __('Teams updated.') }}</div>
        @endif

        @if ($member->teams->isEmpty())
            <p class="mb-4 rounded-md border border-stone-200 bg-white p-4 text-stone-700">{{ __('Not in any team.') }}</p>
        @else
            <ul class="mb-4 divide-y divide-stone-200 rounded-md border border-stone-200 bg-white">
                @foreach ($member->teams as $team)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                        <span>
                            <span class="block text-base font-medium">{{ $team->name }}</span>
                            <span class="block text-sm text-stone-600">
                                {{ ucfirst($team->pivot->role) }}@if ($team->ability) · {{ __('grants :ability', ['ability' => $team->ability->value]) }}@endif
                            </span>
                        </span>
                        <form method="POST" action="{{ route('admin.members.teams.destroy', $member) }}">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="team_id" value="{{ $team->id }}">
                            <button type="submit"
                                class="min-h-11 rounded-md border border-stone-400 bg-white px-4 text-sm font-medium text-stone-900 hover:bg-stone-100">
                                {{ __('Remove') }}<span class="sr-only"> {{ __('from :team', ['team' => $team->name]) }}</span>
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('admin.members.teams.store', $member) }}" class="rounded-md border border-stone-200 bg-white p-4">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="team_id" class="mb-1 block text-sm font-medium text-stone-800">{{ __('Team') }}</label>
                    <select id="team_id" name="team_id" required class="block w-full rounded-md border border-stone-400 bg-white px-3 py-2 text-base">
                        @foreach ($allTeams as $team)
                            <option value="{{ $team->id }}">{{ $team->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="role" class="mb-1 block text-sm font-medium text-stone-800">{{ __('Role') }}</label>
                    <select id="role" name="role" required class="block w-full rounded-md border border-stone-400 bg-white px-3 py-2 text-base">
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}" @selected($role->value === 'member')>{{ ucfirst($role->value) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <button type="submit"
                class="mt-4 min-h-11 rounded-md border border-stone-400 bg-white px-5 text-base font-medium text-stone-900 hover:bg-stone-100 focus:outline-2 focus:outline-offset-2 focus:outline-sky-700">
                {{ __('Add to team or change role') }}
            </button>
        </form>
    </section>
@endsection
