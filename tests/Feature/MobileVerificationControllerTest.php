<?php

namespace Tests\Feature;

use App\Enums\OnboardingStep;
use App\Models\User;
use App\Services\OtpService;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileVerificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_send_mobile_otp(): void
    {
        $user = User::factory()->signer()->create([
            'onboarding_step' => OnboardingStep::MobileVerification,
            'email_verified_at' => now(),
        ]);

        $otpService = \Mockery::mock(OtpService::class);
        $otpService->shouldReceive('generateOtp')->once()->andReturn([
            'success' => true,
            'code' => 'otp_generated',
            'message' => 'OTP generated successfully.',
            'data' => ['otp' => '123456'],
        ]);
        app()->instance(OtpService::class, $otpService);

        $smsService = \Mockery::mock(SmsService::class);
        $smsService->shouldReceive('send')->once();
        app()->instance(SmsService::class, $smsService);

        $response = $this->actingAs($user)->postJson(route('mobile.send-otp'), [
            'mobile_number' => '+15551234567',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'OTP sent successfully.',
            ]);

        $this->assertSame('+15551234567', $user->fresh()->mobile_number);
    }

    public function test_authenticated_user_can_verify_mobile_otp(): void
    {
        $user = User::factory()->signer()->create([
            'onboarding_step' => OnboardingStep::MobileVerification,
            'email_verified_at' => now(),
        ]);

        $otpService = \Mockery::mock(OtpService::class);
        $otpService->shouldReceive('verifyOtp')->once()->withArgs(function (...$args) use ($user): bool {
            if (count($args) < 2) {
                return false;
            }

            return $args[0] === '123456'
                && $args[1] instanceof User
                && $args[1]->is($user);
        })->andReturn([
            'success' => true,
            'code' => 'otp_verified',
            'message' => 'OTP verified successfully.',
            'data' => [],
        ]);
        app()->instance(OtpService::class, $otpService);

        $response = $this->actingAs($user)->postJson(route('mobile.verify-otp'), [
            'otp' => '123456',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Mobile number verified successfully.',
            ]);

        $user->refresh();
        $this->assertNotNull($user->mobile_verified_at);
        $this->assertSame(OnboardingStep::Kyc, $user->onboarding_step);
    }

    public function test_send_otp_is_rate_limited_for_60_seconds(): void
    {
        $user = User::factory()->signer()->create([
            'onboarding_step' => OnboardingStep::MobileVerification,
            'email_verified_at' => now(),
        ]);

        $otpService = \Mockery::mock(OtpService::class);
        $otpService->shouldReceive('generateOtp')->once()->andReturn([
            'success' => true,
            'code' => 'otp_generated',
            'message' => 'OTP generated successfully.',
            'data' => ['otp' => '123456'],
        ]);
        app()->instance(OtpService::class, $otpService);

        $smsService = \Mockery::mock(SmsService::class);
        $smsService->shouldReceive('send')->once();
        app()->instance(SmsService::class, $smsService);

        $this->actingAs($user)->postJson(route('mobile.send-otp'), [
            'mobile_number' => '+15551234567',
        ])->assertOk();

        $this->actingAs($user)->postJson(route('mobile.send-otp'), [
            'mobile_number' => '+15551234567',
        ])->assertStatus(429);
    }
}
