<?php

return [
    'length' => (int) env('OTP_LENGTH', 6),
    'expires_in_minutes' => (int) env('OTP_EXPIRES_IN_MINUTES', 5),
    'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN_SECONDS', 60),
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
];
