<?php

return [
    /*
    |--------------------------------------------------------------------------
    | HSM Backend
    |--------------------------------------------------------------------------
    |
    | The HSM backend to use for cryptographic operations. Options include:
    | - thales: Thales Luna Network HSM
    | - aws-cloudhsm: AWS CloudHSM
    | - utimaco: Utimaco Security Server
    | - mock: Development/testing (no real HSM)
    |
    */

    'backend' => env('HSM_BACKEND', 'mock'),

    /*
    |--------------------------------------------------------------------------
    | Thales Luna Configuration
    |--------------------------------------------------------------------------
    */

    'thales' => [
        'partition_label' => env('THALES_PARTITION_LABEL', 'default'),
        'partition_password' => env('THALES_PARTITION_PASSWORD', ''),
        'library_path' => env('THALES_LIBRARY_PATH', '/opt/luna/lib/libCryptoki2_64.so'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AWS CloudHSM Configuration
    |--------------------------------------------------------------------------
    */

    'aws' => [
        'cluster_id' => env('AWS_CLOUDHSM_CLUSTER_ID', ''),
        'region' => env('AWS_CLOUDHSM_REGION', 'us-east-1'),
        'access_key_id' => env('AWS_ACCESS_KEY_ID'),
        'secret_access_key' => env('AWS_SECRET_ACCESS_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Utimaco Configuration
    |--------------------------------------------------------------------------
    */

    'utimaco' => [
        'slot_id' => env('UTIMACO_SLOT_ID', 0),
        'user_pin' => env('UTIMACO_USER_PIN', ''),
        'library_path' => env('UTIMACO_LIBRARY_PATH', '/usr/lib/libcsulutimaco.so'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Key Configuration
    |--------------------------------------------------------------------------
    */

    'key' => [
        'size' => env('HSM_KEY_SIZE', 2048), // 2048 or 4096 bits
        'algorithm' => 'RSA',
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Monitoring
    |--------------------------------------------------------------------------
    */

    'monitoring' => [
        'enabled' => env('HSM_MONITORING_ENABLED', true),
        'check_interval' => env('HSM_CHECK_INTERVAL', 60), // seconds
        'alert_threshold' => env('HSM_ALERT_THRESHOLD', 3), // consecutive failures
    ],

    /*
    |--------------------------------------------------------------------------
    | Virtual Gateway (VGW) Configuration
    |--------------------------------------------------------------------------
    |
    | CSC requires a dedicated VGW for all incoming PKI requests.
    | Configure IP allowlisting, mTLS, and rate limiting here.
    |
    */

    'gateway' => [
        'api_key' => env('VGW_API_KEY', ''),
        'service_token' => env('VGW_SERVICE_TOKEN', ''),
        'ip_allowlist' => env('VGW_IP_ALLOWLIST', ''), // Comma-separated IPs or CIDRs
        'mtls_required' => (bool) env('VGW_MTLS_REQUIRED', false),
        'trusted_ca_cert' => env('VGW_TRUSTED_CA_CERT', ''),
        'rate_limit' => (int) env('VGW_RATE_LIMIT', 60), // requests per minute
    ],

    /*
    |--------------------------------------------------------------------------
    | OCSP Configuration
    |--------------------------------------------------------------------------
    */

    'ocsp' => [
        'enabled' => (bool) env('OCSP_ENABLED', true),
        'cache_seconds' => (int) env('OCSP_CACHE_SECONDS', 600),
        'responder_url' => env('OCSP_RESPONDER_URL', '/ocsp'),
    ],
];
