<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Google sign-in (PRD FR-2). The client id and secret are read from the OAuth client file
    | that Google Cloud lets you download; ~/.ifgf/google.env names it in IFGF_GOOGLE_CLIENT_JSON.
    | Nothing secret lives in this repository or its .env.
    */
    'google' => (static function (): array {
        $path = env('IFGF_GOOGLE_CLIENT_JSON');
        $client = is_string($path) && is_readable($path)
            ? (json_decode((string) file_get_contents($path), true)['web'] ?? [])
            : [];

        return [
            'client_id' => env('GOOGLE_CLIENT_ID', $client['client_id'] ?? null),
            'client_secret' => env('GOOGLE_CLIENT_SECRET', $client['client_secret'] ?? null),
            'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
        ];
    })(),

];
