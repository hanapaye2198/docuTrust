<?php

namespace App\Http\Controllers;

use App\Enums\OnboardingStep;
use App\Http\Requests\SendMobileOtpRequest;
use App\Http\Requests\VerifyMobileOtpRequest;
use App\Services\OtpAuditLogger;
use App\Services\OtpService;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class MobileVerificationController extends Controller
{
    public function sendOtp(
        SendMobileOtpRequest $request,
        OtpService $otpService,
        SmsService $smsService,
        OtpAuditLogger $auditLogger,
    ): JsonResponse {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => __('Unauthorized.')], 401);
        }

        if (RateLimiter::tooManyAttempts($this->sendThrottleKey($request), 1)) {
            return response()->json([
                'message' => __('Please wait before requesting another OTP.'),
                'retry_after' => RateLimiter::availableIn($this->sendThrottleKey($request)),
            ], 429);
        }

        $user->forceFill([
            'mobile_number' => $request->string('mobile_number')->toString(),
            'mobile_verified_at' => null,
        ])->save();

        try {
            $result = $otpService->generateOtp(
                user: $user,
                email: null,
                mobileNumber: (string) $user->mobile_number,
                purpose: 'mobile_verification',
                channel: 'sms',
                request: $request,
            );

            if (! $result['success']) {
                $status = match ($result['code']) {
                    'cooldown_active', 'rate_limited' => 429,
                    default => 422,
                };

                return response()->json([
                    'message' => __($result['message']),
                    'code' => $result['code'],
                    'meta' => $result['data'],
                ], $status);
            }

            $plainOtp = (string) ($result['data']['otp'] ?? '');
            $smsService->send(
                (string) $user->mobile_number,
                $smsService->formatOtpMessage(),
                $plainOtp,
            );
        } catch (Throwable $throwable) {
            report($throwable);
            $auditLogger->log($user, 'phone_otp_send_failed', (string) $user->mobile_number, $request);

            return response()->json([
                'message' => __('Unable to send OTP right now. Please try again.'),
            ], 500);
        }

        RateLimiter::hit($this->sendThrottleKey($request), (int) config('otp.resend_cooldown_seconds', 60));

        return response()->json([
            'message' => __('OTP sent successfully.'),
            'retry_after_seconds' => (int) ($result['data']['retry_after_seconds'] ?? config('otp.resend_cooldown_seconds', 60)),
        ]);
    }

    public function verifyOtp(
        VerifyMobileOtpRequest $request,
        OtpService $otpService,
    ): JsonResponse {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => __('Unauthorized.')], 401);
        }

        $result = $otpService->verifyOtp(
            inputOtp: $request->string('otp')->toString(),
            user: $user,
            email: null,
            mobileNumber: (string) $user->mobile_number,
            request: $request,
        );

        if (! $result['success']) {
            return response()->json([
                'message' => __($result['message']),
                'code' => $result['code'],
                'meta' => $result['data'],
            ], 422);
        }

        $user->forceFill([
            'mobile_verified_at' => now(),
            'onboarding_step' => OnboardingStep::Kyc,
        ])->save();

        return response()->json([
            'message' => __('Mobile number verified successfully.'),
        ]);
    }

    private function sendThrottleKey(SendMobileOtpRequest $request): string
    {
        return 'mobile-otp-send:'.$request->user()?->id;
    }
}
