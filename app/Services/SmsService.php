<?php

namespace App\Services;

use App\Contracts\Sms\SmsProviderInterface;
use RuntimeException;

class SmsService
{
    public function __construct(
        private readonly SmsProviderInterface $provider,
    ) {}

    public function send(string $number, string $message, ?string $code = null): void
    {
        $result = $this->provider->sendOtp($number, $message, $code);

        if (! $result['success']) {
            throw new RuntimeException('SMS delivery failed.');
        }
    }

    /**
     * Message template for Semaphore OTP API. Use the {otp} placeholder;
     * the provider substitutes it when the code is sent via send().
     */
    public function formatOtpMessage(): string
    {
        return (string) config('otp.sms_message_template', 'Your DocuTrust One-Time Password is: {otp}');
    }
}
