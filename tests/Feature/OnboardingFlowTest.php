<?php

namespace Tests\Feature;

use App\Contracts\Ekyc\IdDocumentTextExtractor;
use App\Enums\OnboardingStep;
use App\Mail\EmailOtpVerificationMail;
use App\Models\User;
use App\Services\OtpService;
use App\Services\SmsService;
use App\Support\AuthSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class OnboardingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_redirects_to_email_verification_and_sends_mail(): void
    {
        Mail::fake();

        Volt::test('auth.register')
            ->set('first_name', 'Test')
            ->set('last_name', 'User')
            ->set('email', 'newsigner@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('agreed_to_terms', true)
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('onboarding.email.verify', absolute: false));

        $this->assertAuthenticated();

        $user = User::query()->where('email', 'newsigner@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame(OnboardingStep::EmailVerification, $user->onboarding_step);
        $this->assertNotNull($user->email_otp);
        $this->assertNotNull($user->email_otp_expires_at);

        Mail::assertSent(EmailOtpVerificationMail::class);
    }

    public function test_wrong_email_otp_stays_on_page_with_error(): void
    {
        Mail::fake();

        Volt::test('auth.register')
            ->set('first_name', 'Test')
            ->set('last_name', 'User')
            ->set('email', 'otp@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('agreed_to_terms', true)
            ->call('register');

        $user = User::query()->where('email', 'otp@example.com')->firstOrFail();

        $this->actingAs($user);

        Volt::test('auth.onboarding-email-verify')
            ->set('code', '000000')
            ->call('verifyCode')
            ->assertHasErrors(['code']);

        $user->refresh();
        $this->assertSame(OnboardingStep::EmailVerification, $user->onboarding_step);
    }

    public function test_correct_email_otp_advances_to_mobile(): void
    {
        Mail::fake();

        Volt::test('auth.register')
            ->set('first_name', 'Test')
            ->set('last_name', 'User')
            ->set('email', 'good@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('agreed_to_terms', true)
            ->call('register');

        $user = User::query()->where('email', 'good@example.com')->firstOrFail();

        $this->actingAs($user);

        Volt::test('auth.onboarding-email-verify')
            ->set('code', $user->email_otp)
            ->call('verifyCode')
            ->assertHasNoErrors()
            ->assertRedirect(route('onboarding.mobile', absolute: false));

        $user->refresh();
        $this->assertSame(OnboardingStep::MobileVerification, $user->onboarding_step);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_mobile_verify_advances_to_kyc(): void
    {
        $user = User::factory()->signer()->create([
            'onboarding_step' => OnboardingStep::MobileVerification,
            'email_verified_at' => now(),
            'mfa_enabled' => false,
        ]);

        $this->actingAs($user);

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

        $user->refresh();
        $this->assertSame(OnboardingStep::Kyc, $user->onboarding_step);
        $this->assertSame('+15551234567', $user->mobile_number);
        $this->assertNotNull($user->mobile_verified_at);
    }

    public function test_complete_mfa_goes_to_documents_and_enables_mfa(): void
    {
        $user = User::factory()->signer()->create([
            'onboarding_step' => OnboardingStep::Mfa,
            'email_verified_at' => now(),
            'mobile_verified_at' => now(),
            'mfa_enabled' => false,
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_onboarding_completed_at' => null,
        ]);

        $this->actingAs($user);

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
    }

    public function test_cannot_access_documents_without_completed_mfa(): void
    {
        $user = User::factory()->signer()->create([
            'onboarding_step' => OnboardingStep::Mfa,
            'email_verified_at' => now(),
            'mobile_verified_at' => now(),
            'mfa_enabled' => false,
        ]);

        $this->actingAs($user)
            ->get(route('documents.index'))
            ->assertRedirect(route('onboarding.mfa'));
    }

    public function test_cannot_skip_onboarding_steps_manually(): void
    {
        $user = User::factory()->signer()->create([
            'onboarding_step' => OnboardingStep::EmailVerification,
            'email_verified_at' => null,
            'mfa_enabled' => false,
        ]);

        $this->actingAs($user)
            ->get(route('onboarding.mobile'))
            ->assertRedirect(route('onboarding.email.verify'));
    }

    public function test_kyc_continue_stores_file_and_advances_to_mfa(): void
    {
        $user = User::factory()->signer()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'name' => 'Test User',
            'onboarding_step' => OnboardingStep::Kyc,
            'email_verified_at' => now(),
            'mobile_verified_at' => now(),
            'mfa_enabled' => false,
        ]);

        $this->mock(IdDocumentTextExtractor::class)
            ->shouldReceive('extract')
            ->once()
            ->andReturn('TEST USER GOVERNMENT ID');

        $this->actingAs($user);

        $file = UploadedFile::fake()->image('id.jpg', 200, 200);

        Volt::test('auth.onboarding-kyc')
            ->set('kyc_id_type', 'passport')
            ->set('id_document', $file)
            ->call('continue')
            ->assertHasNoErrors()
            ->assertRedirect(route('onboarding.mfa', absolute: false));

        $user->refresh();
        $this->assertSame(OnboardingStep::Mfa, $user->onboarding_step);
        $this->assertNotNull($user->kyc_file_path);
        $this->assertSame('passport', $user->kyc_id_type);
        $this->assertNotNull($user->kyc_verified_at);
    }

    public function test_expired_email_otp_is_rejected(): void
    {
        Mail::fake();

        Volt::test('auth.register')
            ->set('first_name', 'Test')
            ->set('last_name', 'User')
            ->set('email', 'expired-otp@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('agreed_to_terms', true)
            ->call('register');

        $user = User::query()->where('email', 'expired-otp@example.com')->firstOrFail();
        $otp = $user->email_otp;
        $this->assertNotNull($otp);

        $user->forceFill([
            'email_otp_expires_at' => now()->subMinute(),
        ])->save();

        $this->actingAs($user);

        Volt::test('auth.onboarding-email-verify')
            ->set('code', $otp)
            ->call('verifyCode')
            ->assertHasErrors(['code']);

        $user->refresh();
        $this->assertSame(OnboardingStep::EmailVerification, $user->onboarding_step);
    }

    public function test_invalid_mfa_code_is_rejected(): void
    {
        $user = User::factory()->signer()->create([
            'onboarding_step' => OnboardingStep::Mfa,
            'email_verified_at' => now(),
            'mobile_verified_at' => now(),
            'mfa_enabled' => false,
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
        ]);

        $this->actingAs($user);

        Volt::test('auth.onboarding-mfa')
            ->set('code', '000000')
            ->call('verify')
            ->assertHasErrors(['code']);

        $user->refresh();
        $this->assertSame(OnboardingStep::Mfa, $user->onboarding_step);
        $this->assertFalse($user->mfa_enabled);
    }

    public function test_guest_cannot_access_onboarding_routes(): void
    {
        $this->get(route('onboarding.email.verify'))->assertRedirect(route('login'));
        $this->get(route('onboarding.mobile'))->assertRedirect(route('login'));
        $this->get(route('onboarding.kyc'))->assertRedirect(route('login'));
        $this->get(route('onboarding.mfa'))->assertRedirect(route('login'));
    }

    public function test_completed_signer_can_access_documents(): void
    {
        $user = User::factory()->signer()->create();

        $this->assertTrue($user->hasCompletedOnboarding());

        $this->actingAs($user);

        Volt::test('documents.index')->assertOk();
    }

    public function test_kyc_step_user_redirected_from_mobile_route(): void
    {
        $user = User::factory()->signer()->create([
            'onboarding_step' => OnboardingStep::Kyc,
            'email_verified_at' => now(),
            'mobile_verified_at' => now(),
            'mfa_enabled' => false,
        ]);

        $this->actingAs($user)
            ->get(route('onboarding.mobile'))
            ->assertRedirect(route('onboarding.kyc'));
    }

    public function test_kyc_continue_requires_id_type_and_file(): void
    {
        $user = User::factory()->signer()->create([
            'onboarding_step' => OnboardingStep::Kyc,
            'email_verified_at' => now(),
            'mobile_verified_at' => now(),
            'mfa_enabled' => false,
        ]);

        $this->actingAs($user);

        Volt::test('auth.onboarding-kyc')
            ->call('continue')
            ->assertHasErrors(['kyc_id_type', 'id_document']);
    }
}
