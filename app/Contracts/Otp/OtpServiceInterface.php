<?php

namespace App\Contracts\Otp;

use App\Models\User;

/**
 * Future provider abstraction for OTP generation and verification workflows.
 */
interface OtpServiceInterface
{
    /**
     * @return array{success: bool, code: string, message: string, data: array<string, mixed>}
     */
    public function generateOtp(
        ?User $user,
        ?string $email,
        ?string $mobileNumber,
        string $purpose,
        string $channel = 'email',
    ): array;

    /**
     * @return array{success: bool, code: string, message: string, data: array<string, mixed>}
     */
    public function verifyOtp(
        string $inputOtp,
        ?User $user = null,
        ?string $email = null,
        ?string $mobileNumber = null,
    ): array;
}
