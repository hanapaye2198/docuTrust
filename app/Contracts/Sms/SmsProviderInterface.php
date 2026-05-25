<?php

namespace App\Contracts\Sms;

interface SmsProviderInterface
{
    /**
     * @return array{success: bool, message_id: int|null, provider: string, raw: array<string, mixed>}
     */
    public function sendOtp(string $number, string $message, ?string $code = null): array;
}
