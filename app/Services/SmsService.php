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

    public function formatOtpMessage(string $otp): string
    {
        return __('DocuTrust OTP: :otp', ['otp' => $otp]);
    }
}
