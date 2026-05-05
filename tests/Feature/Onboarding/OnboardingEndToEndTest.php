<?php

namespace Tests\Feature\Onboarding;

use App\Enums\OnboardingStep;
use App\Mail\EmailOtpVerificationMail;
use App\Models\User;
use App\Services\OtpService;
use App\Services\SmsService;
use App\Support\AuthSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class OnboardingEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_signer_onboarding_from_registration_to_documents(): void
    {
        Mail::fake();

        Volt::test('auth.register')
            ->set('first_name', 'E2E')
            ->set('last_name', 'Signer')
            ->set('email', 'e2e-signer@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('agreed_to_terms', true)
            ->call('register')
            ->assertRedirect(route('onboarding.email.verify', absolute: false));

        $user = User::query()->where('email', 'e2e-signer@example.com')->firstOrFail();
        $this->actingAs($user);

        Mail::assertSent(EmailOtpVerificationMail::class);

        Volt::test('auth.onboarding-email-verify')
            ->set('code', $user->fresh()->email_otp)
            ->call('verifyCode')
            ->assertRedirect(route('onboarding.mobile', absolute: false));

        $otpService = \Mockery::mock(OtpService::class);
        $otpService->shouldReceive('secondsUntilResendAvailable')->andReturn(0);
        $otpService->shouldReceive('generate')->andReturn('123456');
        $otpService->shouldReceive('verify')->andReturn(true);
        app()->instance(OtpService::class, $otpService);

        $smsService = \Mockery::mock(SmsService::class);
        $smsService->shouldReceive('send')->once();
        app()->instance(SmsService::class, $smsService);

        Volt::test('auth.onboarding-mobile')
            ->set('mobile_number', '+15551234567')
            ->call('sendOtp')
            ->set('otp', '123456')
            ->call('verifyOtp')
            ->assertRedirect(route('onboarding.kyc', absolute: false));

        Volt::test('auth.onboarding-kyc')
            ->call('skip')
            ->assertRedirect(route('onboarding.mfa', absolute: false));

        $component = Volt::test('auth.onboarding-mfa');
        $secret = session(AuthSession::SETUP_SECRET);
        $this->assertIsString($secret);

        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $component->set('code', $code)->call('verify')
            ->assertRedirect(route('documents.index', absolute: false));

        $user->refresh();
        $this->assertTrue($user->mfa_enabled);
        $this->assertTrue($user->two_factor_enabled);
        $this->assertSame(OnboardingStep::Completed, $user->onboarding_step);
        $this->assertNotNull($user->email_verified_at);

        $this->actingAs($user);

        Volt::test('documents.index')->assertOk();
    }
}
