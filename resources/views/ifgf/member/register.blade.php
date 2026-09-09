@extends('ifgf.layout')
@section('title', __('ifgf.register_title'))
@section('site', 'Member')

@section('nav-desktop')
    <a href="{{ route('member.register') }}" aria-current="page">{{ __('ifgf.register_title') }}</a>
@endsection

@section('nav-mobile')
    <a href="{{ route('member.register') }}" aria-current="page">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM19 8v6M22 11h-6"/></svg>
        {{ __('ifgf.register_title') }}
    </a>
@endsection

@section('styles')
.req::after { content: " *"; color: var(--danger); }
.hint { font-size: 12.5px; color: var(--fg-faint); margin-top: 5px; }
fieldset { border: 0; padding: 0; margin: 0 0 22px; }
legend { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em;
         color: var(--fg-muted); padding: 0 0 10px; }
@endsection

@section('content')
<div class="page-head">
    <h1>{{ __('ifgf.register_title') }}</h1>
    <p class="sub">{{ __('ifgf.register_help') }}</p>
</div>

@if ($errors->any())
    <div class="flash flash-err">
        <strong>{{ __('ifgf.fix_these') }}</strong>
        <ul style="margin:8px 0 0;padding-left:20px">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('member.register.store') }}" class="card">
    @csrf

    <fieldset>
        <legend>{{ __('ifgf.about_you') }}</legend>

        <div class="field">
            <label for="full_name" class="req">{{ __('ifgf.name') }}</label>
            <input id="full_name" name="full_name" value="{{ old('full_name') }}" required
                   autocomplete="name" maxlength="128">
        </div>

        <div class="field">
            <label for="chinese_name">{{ __('ifgf.chinese_name') }}</label>
            <input id="chinese_name" name="chinese_name" value="{{ old('chinese_name') }}" maxlength="64">
            <p class="hint">{{ __('ifgf.optional') }}</p>
        </div>

        <div class="field">
            <label for="birthday">{{ __('ifgf.birthday') }}</label>
            {{-- max=today: a birthday in the future is always a typo, and the same rule is
                 enforced server-side in RegisterMemberRequest. --}}
            <input id="birthday" name="birthday" type="date" value="{{ old('birthday') }}"
                   max="{{ now()->toDateString() }}">
            <p class="hint">{{ __('ifgf.birthday_hint') }}</p>
        </div>

        <div class="field">
            <label for="gender">{{ __('ifgf.gender') }}</label>
            <select id="gender" name="gender">
                <option value="">{{ __('ifgf.prefer_not_say') }}</option>
                <option value="male" @selected(old('gender') === 'male')>{{ __('ifgf.male') }}</option>
                <option value="female" @selected(old('gender') === 'female')>{{ __('ifgf.female') }}</option>
            </select>
        </div>
    </fieldset>

    <fieldset>
        <legend>{{ __('ifgf.how_to_reach_you') }}</legend>

        <div class="field">
            <label for="email">{{ __('ifgf.email') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}"
                   autocomplete="email" inputmode="email" maxlength="190">
        </div>

        <div class="field">
            <label for="phone">{{ __('ifgf.whatsapp') }}</label>
            <input id="phone" name="phone" type="tel" value="{{ old('phone') }}"
                   autocomplete="tel" inputmode="tel" maxlength="32" placeholder="0912345678">
            {{-- Any spelling is fine: PhoneNormalizer converts to E.164 on save, which is
                 what makes duplicate detection work across formats. --}}
            <p class="hint">{{ __('ifgf.phone_hint') }}</p>
        </div>

        <div class="field">
            <label for="line_id">{{ __('ifgf.line') }}</label>
            <input id="line_id" name="line_id" value="{{ old('line_id') }}" maxlength="64">
            <p class="hint">{{ __('ifgf.optional') }}</p>
        </div>

        <p class="hint">{{ __('ifgf.contact_required') }}</p>
    </fieldset>

    <fieldset>
        <legend>{{ __('ifgf.your_church') }}</legend>

        <div class="field">
            <label for="branch_id" class="req">{{ __('ifgf.branch') }}</label>
            <select id="branch_id" name="branch_id" required>
                <option value="">{{ __('ifgf.choose') }}</option>
                @foreach ($branches as $b)
                    <option value="{{ $b->id }}" @selected((int) old('branch_id') === $b->id)>
                        {{ $b->name }}@if($b->name_zh) / {{ $b->name_zh }}@endif
                    </option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="category_id">{{ __('ifgf.category') }}</label>
            <select id="category_id" name="category_id">
                <option value="">{{ __('ifgf.choose') }}</option>
                @foreach ($categories as $c)
                    <option value="{{ $c->id }}" @selected((int) old('category_id') === $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="group_id">{{ __('ifgf.icare') }}</label>
            <select id="group_id" name="group_id">
                {{-- "Not yet" is a real answer, stored as the absence of a membership row
                     rather than as a group anyone belongs to. --}}
                <option value="">{{ __('ifgf.no_icare_yet') }}</option>
                @foreach ($groups as $g)
                    <option value="{{ $g->id }}" @selected((int) old('group_id') === $g->id)>{{ $g->name }}</option>
                @endforeach
            </select>
            <p class="hint">{{ __('ifgf.icare_hint') }}</p>
        </div>

        <div class="field">
            <label for="domicile_taiwan">{{ __('ifgf.domicile') }}</label>
            <input id="domicile_taiwan" name="domicile_taiwan" value="{{ old('domicile_taiwan') }}" maxlength="128">
        </div>

        <div class="field">
            <label for="school_or_company">{{ __('ifgf.school_or_company') }}</label>
            <input id="school_or_company" name="school_or_company" value="{{ old('school_or_company') }}" maxlength="128">
        </div>
    </fieldset>

    <button type="submit" class="btn btn-primary btn-block">{{ __('ifgf.register_submit') }}</button>

    <p class="hint" style="text-align:center;margin-top:12px">{{ __('ifgf.register_privacy') }}</p>
</form>
@endsection
