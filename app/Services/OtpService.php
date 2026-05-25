<?php

namespace App\Services;

use App\Contracts\Otp\OtpServiceInterface;
use App\Mail\SendOtpMail;
use App\Models\OtpVerification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class OtpService implements OtpServiceInterface
{
    public function __construct(
        private readonly OtpAuditLogger $auditLogger,
    ) {}

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
        ?Request $request = null,
    ): array {
        $identifier = $this->resolveIdentifier($user, $email, $mobileNumber);
        if ($identifier === null) {
            return $this->response(false, 'recipient_missing', 'A valid OTP recipient is required.');
        }

        if (! $this->passesRateLimit($identifier, $channel, $purpose, $request)) {
            $this->logAudit($user, 'otp_rate_limited', $mobileNumber, $request);

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
        $otpRecord = OtpVerification::query()->create([
            'user_id' => $user?->id,
            'email' => $email,
            'mobile_number' => $mobileNumber,
            'purpose' => $purpose,
            'channel' => $channel,
            'otp_code' => Hash::make($plainOtp),
            'expires_at' => now()->addMinutes($this->expiresInMinutes()),
            'verified_at' => null,
            'attempts' => 0,
            'ip_address' => ($request ?? request())->ip(),
            'user_agent' => ($request ?? request())->userAgent(),
        ]);

        if ($channel === 'email' && $email !== null && $email !== '') {
            Mail::to($email)->queue(new SendOtpMail(
                otp: $plainOtp,
                purpose: $purpose,
                expiresInMinutes: $this->expiresInMinutes(),
            ));
        }

        $this->logAudit($user, $channel === 'sms' ? 'phone_otp_sent' : 'email_otp_sent', $mobileNumber, $request);

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

    public function generate(User $user, ?Request $request = null): string
    {
        $result = $this->generateOtp(
            user: $user,
            email: null,
            mobileNumber: (string) $user->mobile_number,
            purpose: 'mobile_verification',
            channel: 'sms',
            request: $request,
        );

        if (! $result['success']) {
            return '';
        }

        return (string) ($result['data']['otp'] ?? '');
    }

    public function verify(User $user, string $inputOtp, ?Request $request = null): bool
    {
        $result = $this->verifyOtp(
            inputOtp: $inputOtp,
            user: $user,
            email: null,
            mobileNumber: (string) $user->mobile_number,
            request: $request,
        );

        return $result['success'];
    }

    /**
     * @return array{success: bool, code: string, message: string, data: array<string, mixed>}
     */
    public function verifyOtp(
        string $inputOtp,
        ?User $user = null,
        ?string $email = null,
        ?string $mobileNumber = null,
        ?Request $request = null,
    ): array {
        $otp = $this->latestActiveOtp($user, $email, $mobileNumber);
        if ($otp === null) {
            $this->logAudit($user, 'otp_verification_failed', $mobileNumber, $request);

            return $this->response(false, 'otp_not_found', 'No active OTP found. Please request a new code.');
        }

        if ($otp->expires_at->isPast()) {
            $otp->update(['verified_at' => now()]);
            $this->logAudit($user, 'otp_verification_expired', $mobileNumber, $request);

            return $this->response(false, 'otp_expired', 'OTP has expired. Please request a new code.');
        }

        if ($otp->attempts >= $this->maxAttempts()) {
            $this->logAudit($user, 'otp_verification_failed', $mobileNumber, $request);

            return $this->response(false, 'max_attempts_reached', 'Maximum verification attempts reached. Please request a new code.');
        }

        if (! Hash::check($inputOtp, (string) $otp->otp_code)) {
            $otp->increment('attempts');
            $this->logAudit($user, 'otp_verification_failed', $mobileNumber, $request);

            return $this->response(
                false,
                'otp_invalid',
                'Invalid verification code.',
                ['attempts' => $otp->fresh()?->attempts ?? $otp->attempts],
            );
        }

        $otp->update(['verified_at' => now()]);
        $this->invalidateActiveOtps($user, $email, $mobileNumber);
        $this->logAudit($user, $mobileNumber !== null ? 'phone_otp_verified' : 'email_otp_verified', $mobileNumber, $request);

        return $this->response(true, 'otp_verified', 'OTP verified successfully.');
    }

    public function secondsUntilResendAvailable(?User $user = null, ?string $email = null, ?string $mobileNumber = null, ?OtpVerification $activeOtp = null): int
    {
        $latestOtp = $activeOtp ?? $this->latestActiveOtp($user, $email, $mobileNumber);
        if ($latestOtp === null || $latestOtp->created_at === null) {
            return 0;
        }

        $seconds = $this->resendCooldownSeconds() - $latestOtp->created_at->diffInSeconds(now());

        return max($seconds, 0);
    }

    private function latestActiveOtp(?User $user = null, ?string $email = null, ?string $mobileNumber = null): ?OtpVerification
    {
        return OtpVerification::query()
            ->when($user?->id !== null, fn ($query) => $query->where('user_id', $user->id))
            ->when($email !== null && $email !== '', fn ($query) => $query->where('email', $email))
            ->when($mobileNumber !== null && $mobileNumber !== '', fn ($query) => $query->where('mobile_number', $mobileNumber))
            ->whereNull('verified_at')
            ->latest('created_at')
            ->first();
    }

    private function invalidateActiveOtps(?User $user = null, ?string $email = null, ?string $mobileNumber = null): void
    {
        OtpVerification::query()
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

    private function passesRateLimit(string $identifier, string $channel, string $purpose, ?Request $request = null): bool
    {
        $ip = ($request ?? request())->ip() ?? 'unknown';
        $cacheKey = sprintf('otp_rate:%s:%s:%s:%s', $channel, $purpose, $identifier, $ip);
        $maxPerWindow = (int) config('otp.rate_limit_max', 3);
        $windowSeconds = (int) config('otp.rate_limit_window_seconds', 60);

        $attempts = (int) Cache::get($cacheKey, 0);

        if ($attempts >= $maxPerWindow) {
            return false;
        }

        Cache::put($cacheKey, $attempts + 1, $windowSeconds);

        if ($channel === 'sms') {
            $mobileKey = $this->smsRateLimitKey($identifier, $ip);
            if (RateLimiter::tooManyAttempts($mobileKey, $maxPerWindow)) {
                return false;
            }

            RateLimiter::hit($mobileKey, $windowSeconds);
        }

        return true;
    }

    private function smsRateLimitKey(string $identifier, string $ip): string
    {
        return 'otp-sms:'.$identifier.':'.$ip;
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

    private function logAudit(?User $user, string $action, ?string $mobileNumber, ?Request $request): void
    {
        if ($user === null) {
            return;
        }

        $this->auditLogger->log($user, $action, $mobileNumber, $request);
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
