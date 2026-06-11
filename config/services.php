<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'brevo' => [
        'key' => env('BREVO_API_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'blockchain' => [
        'base_url' => env('BLOCKCHAIN_SERVICE_URL', 'http://127.0.0.1:3001'),
        'timeout' => env('BLOCKCHAIN_SERVICE_TIMEOUT', 10),
    ],

    'txtbox' => [
        'key' => env('TXTBOX_API_KEY'),
    ],

    'semaphore' => [
        'api_key' => env('SEMAPHORE_API_KEY'),
        'sender_name' => env('SEMAPHORE_SENDER_NAME', 'DocuTrust'),
        'timeout' => (int) env('SEMAPHORE_TIMEOUT', 15),
    ],

    'gatewayhub' => [
        'base_url' => env('GATEWAYHUB_BASE_URL', 'https://gatewayhub.io'),
        'timeout' => env('GATEWAYHUB_TIMEOUT', 15),
        'api_key' => env('GATEWAYHUB_API_KEY', ''),
        'webhook_secret' => env('GATEWAYHUB_WEBHOOK_SECRET', ''),
        'demo_mode' => (bool) env('GATEWAYHUB_DEMO_MODE', false),
        'demo_gateways' => [
            ['code' => 'gcash', 'name' => 'GCash'],
            ['code' => 'maya', 'name' => 'Maya'],
            ['code' => 'coins', 'name' => 'Coins.ph'],
        ],
    ],

    'eis' => [
        'environment' => env('EIS_ENVIRONMENT', 'sandbox'),
        'base_url' => env('EIS_BASE_URL', ''),
        'timeout' => env('EIS_TIMEOUT', 30),
        'public_key_path' => env('EIS_PUBLIC_KEY_PATH', ''),
        'signing_private_key_path' => env('EIS_SIGNING_PRIVATE_KEY_PATH', ''),
        'rsa_padding' => env('EIS_RSA_PADDING', 'pkcs1'),
        'auth_endpoint' => env('EIS_AUTH_ENDPOINT', '/api/authentication'),
        'submit_endpoint' => env('EIS_SUBMIT_ENDPOINT', ''),
        'inquiry_endpoint' => env('EIS_INQUIRY_ENDPOINT', ''),
        'callback_url' => env('EIS_CALLBACK_URL', ''),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 30),
    ],

    'marketing_chatbot' => [
        'enabled' => env('MARKETING_CHATBOT_ENABLED', true),
    ],

    'remote_signing' => [
        'provider_name' => env('REMOTE_SIGNING_PROVIDER_NAME', 'remote_managed'),
        'base_url' => env('REMOTE_SIGNING_BASE_URL', ''),
        'timeout' => env('REMOTE_SIGNING_TIMEOUT', 10),
        'api_key' => env('REMOTE_SIGNING_API_KEY', ''),
        'api_mode' => env('REMOTE_SIGNING_API_MODE', 'csc'),
        'default_credential_id' => env('REMOTE_SIGNING_DEFAULT_CREDENTIAL_ID', ''),
        'csc' => [
            'sign_hash_endpoint' => env('REMOTE_SIGNING_CSC_SIGN_HASH_ENDPOINT', '/csc/v1/signatures/signHash'),
            'timestamp_endpoint' => env('REMOTE_SIGNING_CSC_TIMESTAMP_ENDPOINT', '/csc/v1/signatures/timestamp'),
            'authorize_endpoint' => env('REMOTE_SIGNING_CSC_AUTHORIZE_ENDPOINT', '/csc/v2/credentials/authorize'),
            'authorize_check_endpoint' => env('REMOTE_SIGNING_CSC_AUTHORIZE_CHECK_ENDPOINT', '/csc/v2/credentials/authorizeCheck'),
            'hash_algorithm' => env('REMOTE_SIGNING_CSC_HASH_ALGORITHM', '2.16.840.1.101.3.4.2.1'),
            'sign_algorithm' => env('REMOTE_SIGNING_CSC_SIGN_ALGORITHM', '1.2.840.113549.1.1.11'),
            'authorization_mode' => env('REMOTE_SIGNING_CSC_AUTHORIZATION_MODE', 'explicit'),
            'timestamp_enabled' => env('REMOTE_SIGNING_CSC_TIMESTAMP_ENABLED', false),
            'timestamp_openssl_binary' => env('REMOTE_SIGNING_CSC_TIMESTAMP_OPENSSL_BINARY', ''),
            'timestamp_trust_cert_path' => env('REMOTE_SIGNING_CSC_TIMESTAMP_TRUST_CERT_PATH', ''),
        ],
        'legacy' => [
            'sign_endpoint' => env('REMOTE_SIGNING_SIGN_ENDPOINT', '/sign'),
        ],
    ],

];
