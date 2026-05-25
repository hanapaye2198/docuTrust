<?php

return [
    'length' => (int) env('OTP_LENGTH', 6),
    'expires_in_minutes' => (int) env('OTP_EXPIRES_IN_MINUTES', 5),
    'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN_SECONDS', 60),
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
    'rate_limit_max' => (int) env('OTP_RATE_LIMIT_MAX', 3),
    'rate_limit_window_seconds' => (int) env('OTP_RATE_LIMIT_WINDOW_SECONDS', 60),
    'sms_message_template' => env('OTP_SMS_MESSAGE_TEMPLATE', 'DocuTrust OTP: {otp}'),
];
