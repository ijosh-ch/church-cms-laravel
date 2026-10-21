{{-- Signed in — an usher helping a newcomer — keeps the menu; everyone else gets the signed-out column. --}}
@extends(auth()->check() ? 'layouts.app' : 'layouts.guest')

@section('title', __('Registration').' — '.config('app.name'))

@section('content')
    <h1 class="text-title text-ink">{{ __('Registration') }}</h1>
    <p class="mt-1 text-body text-ink-muted">{{ __('Seven short questions. You can add the rest later, in your profile.') }}</p>

    @if ($errors->any())
        <x-alert class="mt-6" variant="danger" :title="__('Please check these fields:')" summary />
    @endif

    @if ($google)
        <x-alert class="mt-6" role="status">
            <p>{{ __('Signed in with Google as :email. Fill in the rest to finish registering.', ['email' => $google['email']]) }}</p>
            <a href="{{ route('register', ['manual' => 1]) }}" class="focus-ring inline-flex min-h-11 items-center rounded-sm underline underline-offset-2">
                {{ __('Use a different email') }}
            </a>
        </x-alert>
    @endif

    <x-alert class="mt-6" :title="__('What we collect, and why')">
        {{ __('Your name, contact details, birthday, branch and iCare group. They are used only to record your attendance, to remember your birthday and to connect you with an iCare group at IFGF Taipei–Zhongli.') }}
    </x-alert>

    <form method="POST" action="{{ route('register') }}" novalidate class="mt-6 grid gap-5">
        @csrf

        <x-field name="full_name" :label="__('Full Name')" :hint="__('As on your passport or ARC.')">
            <x-input :value="$saved['full_name'] ?? ''" required autocomplete="name" />
        </x-field>

        <x-field name="email" :label="__('Email')" :hint="$google ? __('Confirmed by Google.') : __('We send your attendance QR here.')">
            <x-input type="email" :value="$saved['email'] ?? ''" required autocomplete="email" inputmode="email"
                :readonly="(bool) $google" />
        </x-field>

        @php
            $phoneCountry = \App\Enums\PhoneCountry::tryFrom((string) old('phone_country')) ?? \App\Enums\PhoneCountry::DEFAULT;
        @endphp
        <x-field name="phone" :label="__('WhatsApp Number')" :hint="__('Choose the country code first, then type your number.')">
            <div class="grid grid-cols-[auto_minmax(0,1fr)] gap-2">
                <x-select control="phone_country" :aria-label="__('Country code')" data-phone-country>
                    @foreach (\App\Enums\PhoneCountry::cases() as $country)
                        <option value="{{ $country->value }}" data-example="{{ $country->example() }}" @selected($country === $phoneCountry)>
                            {{ $country->label() }}
                        </option>
                    @endforeach
                </x-select>
                <x-input type="tel" required autocomplete="tel-national" inputmode="tel"
                    :placeholder="$phoneCountry->example()" data-phone-number />
            </div>
        </x-field>

        <x-field name="birthday" :label="__('Tanggal Lahir')"
            :hint="($google['birthday'] ?? null) ? __('From your Google account. Change it if it is wrong.') : null">
            <x-input type="date" :value="$saved['birthday'] ?? ''" required autocomplete="bday" />
        </x-field>

        <x-radio-cards name="branch" :legend="__('Domisili Gereja IFGF')"
            :options="$branches->mapWithKeys(fn ($branch) => [$branch->code => $branch->name.($branch->name_zh ? ' / '.$branch->name_zh : '')])" />

        <x-field name="icare" label="iCare" :hint="__('Not in an iCare group yet? Choose Belum mengikuti.')">
            <x-select required>
                <option value="">{{ __('Choose one') }}</option>
                <option value="{{ $noIcare }}" @selected(old('icare') === $noIcare)>{{ __('Belum mengikuti') }}</option>
                @foreach ($teams as $team)
                    <option value="{{ $team->id }}" @selected(old('icare') === (string) $team->id)>iCare {{ $team->name }}</option>
                @endforeach
            </x-select>
        </x-field>

        <x-radio-cards name="attend_again" :legend="__('Apakah Anda berencana untuk menghadiri kembali Sunday Gathering?')"
            :options="['yes' => __('Ya'), 'no' => __('Tidak')]" />

        <div class="mt-3">
            <x-button>{{ __('Register') }}</x-button>
        </div>
    </form>
@endsection
