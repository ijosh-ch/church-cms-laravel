<x-mail::message>
# {{ __('Two minutes to complete your profile') }}

{{ __('Thank you for joining us at IFGF Taipei–Zhongli. When you registered we asked only the basics. Please add where you live in Taiwan and your work or study — it helps your iCare leader get to know you.') }}

<x-mail::button :url="route('profile.edit')">
{{ __('Complete my profile') }}
</x-mail::button>

{{ __('If you are asked to sign in, use "Continue with Google" with this email address.') }}

{{ config('app.name') }}
</x-mail::message>
