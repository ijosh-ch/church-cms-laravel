<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Site domains
    |--------------------------------------------------------------------------
    |
    | The application answers on two hostnames:
    |
    |   member.ifgf.site  — members. Register, profile, own QR, own attendance history.
    |   attend.ifgf.site  — ushers.  Scan credentials, tick a roster, read attendance info.
    |
    | Set both to null in local development, where there are no subdomains. The usher
    | routes then register under the /usher URL prefix instead of a domain constraint, so
    | the whole flow is reachable at http://localhost/usher/... without a hosts-file edit.
    |
    */

    'member_domain' => env('IFGF_MEMBER_DOMAIN'),

    'usher_domain' => env('IFGF_USHER_DOMAIN'),

    /** Fallback URL prefix for the usher site when no domain is configured (local dev). */
    'usher_prefix' => 'usher',

    /*
    |--------------------------------------------------------------------------
    | Session isolation
    |--------------------------------------------------------------------------
    |
    | 🔴 SESSION_DOMAIN MUST NOT be set to ".ifgf.site".
    |
    | A cookie scoped to the parent domain is sent to BOTH sites, so one authenticated
    | session would span the member site and the usher station. That is the wrong boundary
    | here for two reasons:
    |
    |   1. The usher station is a shared device at a church door. A session that leaks into
    |      it from a member's phone — or out of it onto one — is a real exposure, and the
    |      station is exactly where an unattended logged-in browser is most likely.
    |   2. SEC-001 (usergroup_id = 3 grants every permission via Gate::before) means the
    |      cost of a session crossing that line is total, not partial.
    |
    | Leaving SESSION_DOMAIN null gives each hostname its own host-only cookie. An usher who
    | is also a member signs in twice. That is the correct trade.
    |
    */

    'enforce_session_isolation' => true,

    /*
    |--------------------------------------------------------------------------
    | Scan endpoint throttling
    |--------------------------------------------------------------------------
    |
    | resolve() on a 256-bit token is not guessable and needs no protection. The manual
    | short-code fallback carries only 40 bits and IS guessable, so its route is throttled
    | hard. These are attempts per minute, per authenticated usher.
    |
    */

    'throttle' => [
        'scan' => env('IFGF_THROTTLE_SCAN', '120,1'),
        'short_code' => env('IFGF_THROTTLE_SHORT_CODE', '10,1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Prototype mode
    |--------------------------------------------------------------------------
    |
    | 🔴 OPENS THE USHER SITE WITHOUT AUTHENTICATION. Never enable it anywhere
    | reachable from the internet.
    |
    | It exists so the prototype can be walked through end to end before the
    | usher role is seeded and before AUTH-001 / SEC-001 are fixed. Two locks,
    | both required:
    |
    |   1. this flag, which is false by default; and
    |   2. APP_ENV=local — checked independently in EnsureUsher, so setting the
    |      flag on a staging or production box does nothing.
    |
    | Every page shows a banner while it is on, so nobody mistakes an open
    | prototype for a secured one.
    |
    */

    'prototype_mode' => env('IFGF_PROTOTYPE_MODE', false) && env('APP_ENV') === 'local',

    /*
    |--------------------------------------------------------------------------
    | Attribution
    |--------------------------------------------------------------------------
    |
    | Shown in the footer of every IFGF page.
    |
    | 🔴 The upstream church management system is MIT-licensed by GegoSoft
    | Technologies (OPC) Private Limited, and MIT requires its notice to travel
    | with every copy. That notice lives in the repository LICENSE file and is
    | acknowledged in the footer; it must not be removed or replaced. The IFGF
    | copyright below sits ALONGSIDE it, covering IFGF-authored work only.
    |
    */

    'copyright_holder' => env('IFGF_COPYRIGHT_HOLDER', 'IFGF Taipei Zhongli'),

    'copyright_year' => env('IFGF_COPYRIGHT_YEAR', '2026'),

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    |
    | Indonesian first: the legacy portal, the registration form and the whole
    | roster vocabulary are Indonesian, so it is the honest default rather than
    | English. zh_TW is Traditional — 198 of 217 members carry a Chinese name.
    |
    */

    'locales' => ['id', 'en', 'zh_TW'],

    'default_locale' => env('IFGF_DEFAULT_LOCALE', 'id'),

];
