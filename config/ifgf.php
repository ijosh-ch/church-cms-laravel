<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Birthday calendar (PRD FR-6)
    |--------------------------------------------------------------------------
    |
    | `fake` keeps every event in memory and never touches the network; it is
    | the default, and the only driver the test suite can ever get. `google`
    | writes to the calendar named in IFGF_CALENDAR_ID with the service account
    | key at IFGF_CALENDAR_KEY. All three come from ~/.ifgf/calendar.env, which
    | belongs to the machine: a development PC names the DEV calendar, and only
    | the production server names the live one.
    |
    */

    'calendar' => [
        'driver' => env('IFGF_CALENDAR_DRIVER', 'fake'),
        'id' => env('IFGF_CALENDAR_ID'),
        'key' => env('IFGF_CALENDAR_KEY'),
    ],

];
