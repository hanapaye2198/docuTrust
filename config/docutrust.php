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
        'root_ca_private_key_path' => env('DOCUTRUST_ROOT_CA_PRIVATE_KEY_PATH', ''),
        'signing_backend' => env('DOCUTRUST_SIGNING_BACKEND', 'app_managed'),
        'root_ca_name' => env('DOCUTRUST_ROOT_CA_NAME', 'DocuTrust Root CA'),
        'root_ca_country' => env('DOCUTRUST_ROOT_CA_COUNTRY', 'PH'),
        'root_ca_valid_days' => (int) env('DOCUTRUST_ROOT_CA_VALID_DAYS', 3650),
        'signer_valid_days' => (int) env('DOCUTRUST_SIGNER_CERT_VALID_DAYS', 825),
        'ocsp_responder_url' => env('DOCUTRUST_OCSP_URL', ''),
        'crl_distribution_url' => env('DOCUTRUST_CRL_URL', ''),
        'ca_issuers_url' => env('DOCUTRUST_CA_ISSUERS_URL', ''),
    ],

    'notary' => [
        'jurisdiction' => env('DOCUTRUST_NOTARY_JURISDICTION', 'Philippines'),
        'allowed_country_code' => env('DOCUTRUST_NOTARY_ALLOWED_COUNTRY', 'PH'),
        'timezone' => env('DOCUTRUST_NOTARY_TIMEZONE', 'Asia/Manila'),
        'require_location_verification' => (bool) env('DOCUTRUST_NOTARY_REQUIRE_LOCATION', true),
        'require_identity_verification' => (bool) env('DOCUTRUST_NOTARY_REQUIRE_IDENTITY', true),
        'require_video_session' => (bool) env('DOCUTRUST_NOTARY_REQUIRE_VIDEO', true),
        'session_expiry_hours' => (int) env('DOCUTRUST_NOTARY_SESSION_EXPIRY_HOURS', 72),
        'jitsi_base_url' => env('DOCUTRUST_NOTARY_JITSI_BASE_URL', 'https://meet.jit.si'),
        'jitsi_app_id' => env('DOCUTRUST_NOTARY_JITSI_APP_ID'),
        'jitsi_app_secret' => env('DOCUTRUST_NOTARY_JITSI_APP_SECRET'),
        'jitsi_api_key_id' => env('DOCUTRUST_NOTARY_JITSI_API_KEY_ID'),
        'verification_checklist' => [
            'face_matches_id',
            'id_valid_not_expired',
            'signer_conscious_aware',
            'signer_agrees_voluntarily',
            'signer_in_philippines',
            'id_shown_on_camera',
        ],
        'notarial_act_types' => [
            'acknowledgment',
            'jurat',
            'affidavit',
            'oath',
            'other',
        ],
    ],

];
