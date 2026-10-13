<x-mail::message>
# {{ __('Welcome to IFGF Taipei–Zhongli') }}

{{ __('Thank you for registering with IFGF Taipei–Zhongli. Show your attendance QR to the usher on Sunday.') }}

{{ __('If the QR cannot be scanned, give the usher this code:') }} **{{ $member->short_code }}**

<x-mail::button :url="route('home')">
{{ __('Open my QR') }}
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
