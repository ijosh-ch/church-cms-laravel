{{-- Signed in — an usher helping a newcomer — keeps the menu; everyone else gets the signed-out column. --}}
@extends(auth()->check() ? 'layouts.app' : 'layouts.guest')

@section('title', __('Registration').' — '.config('app.name'))

@section('content')
    <h1 class="text-title text-ink">{{ __('Registration') }}</h1>
    <x-steps class="mt-4" current="1" total="2" :name="__('About you')" />

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
        {{ __('Your name, birthday, contact details, place of study or work and iCare group. They are used only to record your attendance, to remember your birthday and to connect you with an iCare group at IFGF Taipei–Zhongli.') }}
    </x-alert>

    <form method="POST" action="{{ route('register') }}" novalidate class="mt-6 grid gap-5">
        @csrf

        <x-field name="email" :label="__('Email')" :hint="$google ? __('Confirmed by Google.') : null">
            <x-input type="email" :value="$saved['email'] ?? ''" required autocomplete="email" inputmode="email"
                :readonly="(bool) $google" />
        </x-field>

        <x-field name="full_name" :label="__('Full Name')" :hint="__('As on your passport or ARC.')">
            <x-input :value="$saved['full_name'] ?? ''" required autocomplete="name" />
        </x-field>

        <x-field name="chinese_name" :label="__('Chinese Name')" optional>
            <x-input :value="$saved['chinese_name'] ?? ''" />
        </x-field>

        <x-field name="birthday" :label="__('Tanggal Lahir')"
            :hint="($google['birthday'] ?? null) ? __('From your Google account. Change it if it is wrong.') : null">
            <x-input type="date" :value="$saved['birthday'] ?? ''" required autocomplete="bday" />
        </x-field>

        <x-field name="domicile_taiwan" :label="__('Domisili Taiwan')" :hint="__('For example: Taipei - Da\'an')">
            <x-input :value="$saved['domicile_taiwan'] ?? ''" required />
        </x-field>

        <x-field name="line_id" :label="__('Line ID')" optional>
            <x-input :value="$saved['line_id'] ?? ''" />
        </x-field>

        <x-field name="phone" :label="__('WhatsApp Number')">
            <x-input type="tel" :value="$saved['phone'] ?? ''" required autocomplete="tel" inputmode="tel" />
        </x-field>

        <x-radio-cards name="branch" :legend="__('Domisili Gereja IFGF')" :value="$saved['branch'] ?? ''"
            :options="$branches->mapWithKeys(fn ($branch) => [$branch->code => $branch->name.($branch->name_zh ? ' / '.$branch->name_zh : '')])" />

        <x-radio-cards name="attend_again" :legend="__('Apakah Anda berencana untuk menghadiri kembali Sunday Gathering?')"
            :value="$saved['attend_again'] ?? ''" :options="['yes' => __('Ya'), 'no' => __('Tidak')]" />

        <div class="mt-3">
            <x-button>{{ __('Next') }}</x-button>
        </div>
    </form>
@endsection
