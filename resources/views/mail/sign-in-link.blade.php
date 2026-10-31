<x-mail::message>
# {{ __('Sign in to :app', ['app' => config('app.name')]) }}

{{ __('Press the button to sign in. The link works once, within :minutes minutes.', ['minutes' => $minutes]) }}

<x-mail::button :url="$url">
{{ __('Sign in') }}
</x-mail::button>

{{ __('If you did not ask for this email, you can ignore it. Nobody can sign in without the link.') }}

{{ config('app.name') }}
</x-mail::message>
