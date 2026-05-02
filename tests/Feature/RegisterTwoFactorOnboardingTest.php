<?php

namespace Tests\Feature;

use App\Enums\EkycStatus;
use App\Enums\OnboardingStep;
use App\Models\User;
use App\Support\AuthSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Volt\Volt;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class RegisterTwoFactorOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_user_is_redirected_to_email_notice_from_app(): void
    {
        $user = User::factory()->signer()->create([
            'onboarding_step' => OnboardingStep::Registered,
        ]);

        $this->actingAs($user)
            ->get(route('documents.index'))
            ->assertRedirect(route('onboarding.email.notice'));
    }

    public function test_email_verified_user_is_redirected_to_phone_verification(): void
    {
        $user = User::factory()->signer()->create([
            'onboarding_step' => OnboardingStep::EmailVerified,
            'ekyc_status' => EkycStatus::NotSubmitted,
        ]);

        $this->actingAs($user)
            ->get(route('documents.index'))
            ->assertRedirect(route('onboarding.phone.verify'));
    }

    public function test_verification_link_marks_email_verified_and_advances_step(): void
    {
        $user = User::factory()->signer()->create([
            'email_verified_at' => null,
            'onboarding_step' => OnboardingStep::Registered,
        ]);

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(5), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->get($url)->assertRedirect(route('onboarding.phone.verify'));

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(OnboardingStep::EmailVerified, $user->onboarding_step);
        $this->assertAuthenticatedAs($user);
    }

    public function test_mfa_setup_route_requires_ekyc_verified_step(): void
    {
        $user = User::factory()->signer()->create([
            'onboarding_step' => OnboardingStep::PhoneVerified,
            'ekyc_status' => EkycStatus::NotSubmitted,
        ]);

        $this->actingAs($user)
            ->get(route('onboarding.mfa-setup'))
            ->assertRedirect(route('onboarding.ekyc'));
    }

    public function test_mfa_setup_route_redirects_if_step_is_ekyc_verified_but_ekyc_status_is_not_verified(): void
    {
        $user = User::factory()->signer()->create([
            'onboarding_step' => OnboardingStep::EkycVerified,
            'ekyc_status' => EkycStatus::Pending,
        ]);

        $this->actingAs($user)
            ->get(route('onboarding.mfa-setup'))
            ->assertRedirect(route('onboarding.ekyc'));
    }

    public function test_onboarding_mfa_setup_completes_onboarding_and_enables_two_factor(): void
    {
        $user = User::factory()->signer()->create([
            'onboarding_step' => OnboardingStep::EkycVerified,
            'ekyc_status' => EkycStatus::Verified,
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_onboarding_completed_at' => null,
        ]);

        $this->actingAs($user);

        $component = Volt::test('auth.onboarding-mfa-setup');
        $secret = session(AuthSession::SETUP_SECRET);
        $this->assertIsString($secret);

        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $component->set('code', $code)->call('verify')
            ->assertRedirect(route('documents.index', absolute: false));

        $user->refresh();
        $this->assertTrue($user->two_factor_enabled);
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNotNull($user->two_factor_onboarding_completed_at);
        $this->assertSame(OnboardingStep::Completed, $user->onboarding_step);
        $this->assertSame(EkycStatus::Verified, $user->ekyc_status);
        $this->assertFalse(session()->has(AuthSession::SETUP_SECRET));
    }
}
