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

    'pki' => [
        'openssl_config_path' => env('DOCUTRUST_OPENSSL_CONFIG', 'C:\\php\\extras\\ssl\\openssl.cnf'),
        'root_ca_name' => env('DOCUTRUST_ROOT_CA_NAME', 'DocuTrust Root CA'),
        'root_ca_country' => env('DOCUTRUST_ROOT_CA_COUNTRY', 'PH'),
        'root_ca_valid_days' => (int) env('DOCUTRUST_ROOT_CA_VALID_DAYS', 3650),
        'signer_valid_days' => (int) env('DOCUTRUST_SIGNER_CERT_VALID_DAYS', 825),
    ],

];
