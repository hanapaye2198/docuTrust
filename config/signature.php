<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Compliance phase
    |--------------------------------------------------------------------------
    */

    'compliance' => [
        'phase' => env('SIGNATURE_COMPLIANCE_PHASE', 'early_production'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Signature engine (wraps existing capture/seal flow)
    |--------------------------------------------------------------------------
    */

    'engines' => [
        'default' => env('SIGNATURE_ENGINE', 'basic'),
    ],

    'pades_enabled' => env('PADES_ENABLED', false),

    'ltv_enabled' => env('LTV_ENABLED', false),

    'tsa' => [
        'url' => env('TSA_URL', 'http://timestamp.digicert.com'),
        'ca_cert' => env('TSA_CA_CERT', ''),
        'timeout' => (int) env('TSA_TIMEOUT', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature flags (early production: HSM/KMS/PKCS#11 off by default)
    |--------------------------------------------------------------------------
    */

    'features' => [
        'hsm' => [
            'enabled' => (bool) env('SIGNATURE_HSM_ENABLED', false),
        ],
        'aws_cloudhsm' => [
            'enabled' => (bool) env('SIGNATURE_AWS_CLOUDHSM_ENABLED', false),
        ],
        'aws_kms' => [
            'enabled' => (bool) env('SIGNATURE_AWS_KMS_ENABLED', false),
        ],
        'pkcs11' => [
            'enabled' => (bool) env('SIGNATURE_PKCS11_ENABLED', false),
        ],
        'pades' => [
            'enabled' => (bool) env('SIGNATURE_PADES_ENABLED', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Optional PKI protocol routes (off in early production)
    |--------------------------------------------------------------------------
    */

    'routes' => [
        'hsm' => (bool) env('HSM_ROUTES_ENABLED', false),
        'ocsp' => (bool) env('SIGNATURE_OCSP_ROUTES_ENABLED', false),
        'crl' => (bool) env('SIGNATURE_CRL_ROUTES_ENABLED', false),
        'scep' => (bool) env('SIGNATURE_SCEP_CMP_ROUTES_ENABLED', false),
        'cmp' => (bool) env('SIGNATURE_SCEP_CMP_ROUTES_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | HSM / KMS placeholders (not wired until purchased)
    |--------------------------------------------------------------------------
    */

    'hsm' => [
        'backend' => env('HSM_BACKEND', 'disabled'),
    ],

    'aws_cloudhsm' => [
        'cluster_id' => env('AWS_CLOUDHSM_CLUSTER_ID', ''),
        'region' => env('AWS_CLOUDHSM_REGION', 'us-east-1'),
    ],

    'aws_kms' => [
        'key_id' => env('AWS_KMS_KEY_ID', ''),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'pkcs11' => [
        'library_path' => env('PKCS11_LIBRARY_PATH', ''),
        'slot' => env('PKCS11_SLOT', '0'),
    ],

    'pades' => [
        'profile' => env('SIGNATURE_PADES_PROFILE', 'B-T'),
    ],

];
