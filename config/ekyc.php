<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OCR driver
    |--------------------------------------------------------------------------
    |
    | Supported: "tesseract" (requires tesseract binary on the server)
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
    |
    */
    'name_match_threshold' => (int) env('EKYC_NAME_MATCH_THRESHOLD', 85),

];
