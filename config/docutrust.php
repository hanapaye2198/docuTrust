<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Client-side idle session (mirrored in resources/js/idle-session.js)
    |--------------------------------------------------------------------------
    |
    | SESSION_LIFETIME should be >= the longest idle_minutes here (see .env.example).
    |
    */
    'idle_session' => [
        'onboarding' => [
            'idle_minutes' => (int) env('DOCUTRUST_IDLE_ONBOARDING_MINUTES', 10),
            'warning_minutes' => (int) env('DOCUTRUST_IDLE_ONBOARDING_WARNING_MINUTES', 9),
        ],
        'app' => [
            'idle_minutes' => (int) env('DOCUTRUST_IDLE_APP_MINUTES', 20),
            'warning_minutes' => (int) env('DOCUTRUST_IDLE_APP_WARNING_MINUTES', 19),
        ],
    ],

];
