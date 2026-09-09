<?php

/*
 * Package-owned config, merged under the "church-operations" key.
 */

return [
    'version' => '0.2.0-prototype',

    'calendar' => [
        /*
         | Which gateway backs BirthdaySyncService.
         |
         | 'fake' keeps everything in memory. It is the DEFAULT on purpose: a fresh
         | checkout, and the whole test suite, must run end to end without Google
         | credentials and without any chance of touching the live birthday calendar
         | (build.md - never touch live Google Calendar).
         |
         | Switch to 'google' only with a service account configured.
        */
        'driver' => env('IFGF_CALENDAR_DRIVER', 'fake'),

        /*
         | The birthday calendar id. Never commit a real one - it is an access
         | identifier (build.md SECURITY 2). Set IFGF_BIRTHDAY_CALENDAR_ID in
         | ~/.ifgf/*.env or the checkout .env.
        */
        'birthday_id' => env('IFGF_BIRTHDAY_CALENDAR_ID', 'ifgf-birthdays-demo'),

        /* Path to the Google service-account JSON. Outside the repository. */
        'service_account' => env('IFGF_GOOGLE_SERVICE_ACCOUNT'),
    ],

    'demo' => [
        /*
         | Guard rail. ifgf:demo:seed refuses to run when this is false, so the
         | fixture can never be created in an environment that holds real members.
        */
        'enabled' => env('IFGF_DEMO_ENABLED', true),
    ],
];
