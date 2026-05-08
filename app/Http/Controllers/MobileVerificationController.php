<?php

namespace App\Http\Controllers;

use App\Enums\OnboardingStep;
use App\Http\Requests\SendMobileOtpRequest;
use App\Http\Requests\VerifyMobileOtpRequest;
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
    ): JsonResponse {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => __('Unauthorized.')], 401);
        }

        $throttleKey = 'mobile-otp-send:'.$user->id;
        if (RateLimiter::tooManyAttempts($throttleKey, 1)) {
            return response()->json([
                'message' => __('Please wait before requesting another OTP.'),
                'retry_after' => RateLimiter::availableIn($throttleKey),
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
            );

            if (! $result['success']) {
                $status = $result['code'] === 'cooldown_active' ? 429 : 422;

                return response()->json([
                    'message' => __($result['message']),
                    'code' => $result['code'],
                    'meta' => $result['data'],
                ], $status);
            }

            $smsService->send(
                (string) $user->mobile_number,
                __('Your DocuTrust verification code is :otp. It expires in 5 minutes.', ['otp' => (string) ($result['data']['otp'] ?? '')]),
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json([
                'message' => __('Unable to send OTP right now. Please try again.'),
            ], 500);
        }

        RateLimiter::hit($throttleKey, 60);

        return response()->json([
            'message' => __('OTP sent successfully.'),
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
        );

        if (! $result['success']) {
            return response()->json([
                'message' => __('Invalid or expired OTP.'),
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
}
