<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default eKYC driver
    |--------------------------------------------------------------------------
    |
    | Supported: "tesseract" (local OCR name matching), "sumsub" (third-party
    | identity verification with document capture, liveness, and face match).
    |
    */
    'default_driver' => env('EKYC_DRIVER', 'tesseract'),

    /*
    |--------------------------------------------------------------------------
    | Tesseract OCR driver
    |--------------------------------------------------------------------------
    |
    | Used when default_driver is "tesseract". Requires the Tesseract binary
    | installed on the server.
    |
    */
    'ocr_driver' => env('EKYC_OCR_DRIVER', 'tesseract'),

    'tesseract_binary' => env('EKYC_TESSERACT_BINARY', 'tesseract'),

    'tesseract_lang' => env('EKYC_TESSERACT_LANG', 'eng'),

    /*
    |--------------------------------------------------------------------------
    | Name matching
    |--------------------------------------------------------------------------
    |
    | Minimum similar_text score (0-100) for first and last name tokens.
    | Used by the Tesseract driver only.
    |
    */
    'name_match_threshold' => (int) env('EKYC_NAME_MATCH_THRESHOLD', 85),

    /*
    |--------------------------------------------------------------------------
    | Sumsub configuration
    |--------------------------------------------------------------------------
    |
    | Credentials and settings for the Sumsub identity verification provider.
    | Obtain these from the Sumsub Dashboard → Dev Space → App Tokens.
    |
    */
    'sumsub' => [
        'app_token' => env('SUMSUB_APP_TOKEN'),
        'secret_key' => env('SUMSUB_SECRET_KEY'),
        'base_url' => env('SUMSUB_BASE_URL', 'https://api.sumsub.com'),
        'webhook_secret' => env('SUMSUB_WEBHOOK_SECRET'),
        'level_name' => env('SUMSUB_LEVEL_NAME', 'basic-kyc-level'),
        'ttl_in_secs' => (int) env('SUMSUB_TOKEN_TTL', 600),
    ],

];
