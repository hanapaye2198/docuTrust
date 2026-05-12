<?php

namespace App\Services;

use App\Mail\SendOtpMail;
use App\Models\Otp;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    /**
     * @return array{success: bool, code: string, message: string, data: array<string, mixed>}
     */
    public function generateForEmail(User $user, string $purpose = 'verification'): array
    {
        return $this->generateOtp(
            user: $user,
            email: $user->email,
            mobileNumber: null,
            purpose: $purpose,
            channel: 'email',
        );
    }

    /**
     * @return array{success: bool, code: string, message: string, data: array<string, mixed>}
     */
    public function generateOtp(
        ?User $user,
        ?string $email,
        ?string $mobileNumber,
        string $purpose,
        string $channel = 'email',
    ): array {
        $identifier = $this->resolveIdentifier($user, $email, $mobileNumber);
        if ($identifier === null) {
            return $this->response(false, 'recipient_missing', 'A valid OTP recipient is required.');
        }

        if (! $this->passesRateLimit($identifier, $channel, $purpose)) {
            return $this->response(false, 'rate_limited', 'OTP request is currently rate-limited.');
        }

        $activeOtp = $this->latestActiveOtp($user, $email, $mobileNumber);
        $cooldownRemaining = $this->secondsUntilResendAvailable($user, $email, $mobileNumber, $activeOtp);
        if ($cooldownRemaining > 0) {
            return $this->response(
                false,
                'cooldown_active',
                'Please wait before requesting another OTP.',
                ['retry_after_seconds' => $cooldownRemaining],
            );
        }

        $this->invalidateActiveOtps($user, $email, $mobileNumber);

        $plainOtp = $this->generateCode();
        $otpRecord = Otp::query()->create([
            'user_id' => $user?->id,
            'email' => $email,
            'mobile_number' => $mobileNumber,
            'otp_code' => Hash::make($plainOtp),
            'expires_at' => now()->addMinutes($this->expiresInMinutes()),
            'verified_at' => null,
            'attempts' => 0,
        ]);

        if ($channel === 'email' && $email !== null && $email !== '') {
            Mail::to($email)->queue(new SendOtpMail(
                otp: $plainOtp,
                purpose: $purpose,
                expiresInMinutes: $this->expiresInMinutes(),
            ));
        }

        return $this->response(
            true,
            'otp_generated',
            'OTP generated successfully.',
            [
                'otp_id' => $otpRecord->id,
                'otp' => $plainOtp,
                'expires_at' => $otpRecord->expires_at?->toISOString(),
                'retry_after_seconds' => $this->resendCooldownSeconds(),
                'channel' => $channel,
            ],
        );
    }

    public function generate(User $user): string
    {
        $result = $this->generateOtp(
            user: $user,
            email: null,
            mobileNumber: (string) $user->mobile_number,
            purpose: 'mobile_verification',
            channel: 'sms',
        );

        if (! $result['success']) {
            return '';
        }

        return (string) ($result['data']['otp'] ?? '');
    }

    public function verify(User $user, string $inputOtp): bool
    {
        $result = $this->verifyOtp(
            inputOtp: $inputOtp,
            user: $user,
            email: null,
            mobileNumber: (string) $user->mobile_number,
        );

        return $result['success'];
    }

    /**
     * @return array{success: bool, code: string, message: string, data: array<string, mixed>}
     */
    public function verifyOtp(string $inputOtp, ?User $user = null, ?string $email = null, ?string $mobileNumber = null): array
    {
        $otp = $this->latestActiveOtp($user, $email, $mobileNumber);
        if ($otp === null) {
            return $this->response(false, 'otp_not_found', 'No active OTP found.');
        }

        if ($otp->expires_at->isPast()) {
            $otp->update(['verified_at' => now()]);

            return $this->response(false, 'otp_expired', 'OTP has expired.');
        }

        if ($otp->attempts >= $this->maxAttempts()) {
            return $this->response(false, 'max_attempts_reached', 'Maximum verification attempts reached.');
        }

        if (! Hash::check($inputOtp, (string) $otp->otp_code)) {
            $otp->increment('attempts');

            return $this->response(
                false,
                'otp_invalid',
                'Invalid OTP provided.',
                ['attempts' => $otp->fresh()?->attempts ?? $otp->attempts],
            );
        }

        $otp->update(['verified_at' => now()]);
        $this->invalidateActiveOtps($user, $email, $mobileNumber);

        return $this->response(true, 'otp_verified', 'OTP verified successfully.');
    }

    public function secondsUntilResendAvailable(?User $user = null, ?string $email = null, ?string $mobileNumber = null, ?Otp $activeOtp = null): int
    {
        $latestOtp = $activeOtp ?? $this->latestActiveOtp($user, $email, $mobileNumber);
        if ($latestOtp === null || $latestOtp->created_at === null) {
            return 0;
        }

        $seconds = $this->resendCooldownSeconds() - $latestOtp->created_at->diffInSeconds(now());

        return max($seconds, 0);
    }

    private function latestActiveOtp(?User $user = null, ?string $email = null, ?string $mobileNumber = null): ?Otp
    {
        return Otp::query()
            ->when($user?->id !== null, fn ($query) => $query->where('user_id', $user->id))
            ->when($email !== null && $email !== '', fn ($query) => $query->where('email', $email))
            ->when($mobileNumber !== null && $mobileNumber !== '', fn ($query) => $query->where('mobile_number', $mobileNumber))
            ->whereNull('verified_at')
            ->latest('created_at')
            ->first();
    }

    private function invalidateActiveOtps(?User $user = null, ?string $email = null, ?string $mobileNumber = null): void
    {
        Otp::query()
            ->when($user?->id !== null, fn ($query) => $query->where('user_id', $user->id))
            ->when($email !== null && $email !== '', fn ($query) => $query->where('email', $email))
            ->when($mobileNumber !== null && $mobileNumber !== '', fn ($query) => $query->where('mobile_number', $mobileNumber))
            ->whereNull('verified_at')
            ->update(['verified_at' => now()]);
    }

    private function generateCode(): string
    {
        $min = 10 ** ($this->otpLength() - 1);
        $max = (10 ** $this->otpLength()) - 1;

        return (string) random_int($min, $max);
    }

    private function otpLength(): int
    {
        return max((int) config('otp.length', 6), 4);
    }

    private function expiresInMinutes(): int
    {
        return max((int) config('otp.expires_in_minutes', 5), 1);
    }

    private function resendCooldownSeconds(): int
    {
        return max((int) config('otp.resend_cooldown_seconds', 60), 0);
    }

    private function maxAttempts(): int
    {
        return max((int) config('otp.max_attempts', 5), 1);
    }

    private function passesRateLimit(string $identifier, string $channel, string $purpose): bool
    {
        $key = sprintf('otp_rate:%s:%s:%s', $channel, $purpose, $identifier);
        $maxPerWindow = (int) config('otp.rate_limit_max', 5);
        $windowSeconds = (int) config('otp.rate_limit_window_seconds', 300);

        $attempts = (int) Cache::get($key, 0);

        if ($attempts >= $maxPerWindow) {
            return false;
        }

        Cache::put($key, $attempts + 1, $windowSeconds);

        return true;
    }

    private function resolveIdentifier(?User $user, ?string $email, ?string $mobileNumber): ?string
    {
        if ($user?->id !== null) {
            return 'user:'.$user->id;
        }

        if ($email !== null && $email !== '') {
            return 'email:'.$email;
        }

        if ($mobileNumber !== null && $mobileNumber !== '') {
            return 'mobile:'.$mobileNumber;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{success: bool, code: string, message: string, data: array<string, mixed>}
     */
    private function response(bool $success, string $code, string $message, array $data = []): array
    {
        return [
            'success' => $success,
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ];
    }
}
