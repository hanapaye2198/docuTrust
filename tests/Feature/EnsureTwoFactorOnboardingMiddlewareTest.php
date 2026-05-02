<?php

namespace Tests\Feature;

use App\Enums\OnboardingStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureTwoFactorOnboardingMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_allows_onboarding_route_that_matches_current_step(): void
    {
        $user = User::factory()->signer()->create([
            'onboarding_step' => OnboardingStep::MobileVerification,
            'email_verified_at' => now(),
            'mfa_enabled' => false,
        ]);

        $this->actingAs($user)
            ->get(route('onboarding.mobile'))
            ->assertOk();
    }

    public function test_it_redirects_to_expected_step_when_onboarding_is_incomplete(): void
    {
        $user = User::factory()->signer()->create([
            'onboarding_step' => OnboardingStep::EmailVerification,
            'email_verified_at' => null,
            'mfa_enabled' => false,
        ]);

        $response = $this->actingAs($user)->get(route('documents.index'));

        $response->assertRedirect(route('onboarding.email.verify'));
    }

    public function test_kyc_user_redirected_from_documents_to_kyc(): void
    {
        $user = User::factory()->signer()->create([
            'onboarding_step' => OnboardingStep::Kyc,
            'email_verified_at' => now(),
            'mfa_enabled' => false,
        ]);

        $this->actingAs($user)
            ->get(route('documents.index'))
            ->assertRedirect(route('onboarding.kyc'));
    }

    public function test_mobile_user_redirected_from_mfa_route(): void
    {
        $user = User::factory()->signer()->create([
            'onboarding_step' => OnboardingStep::MobileVerification,
            'email_verified_at' => now(),
            'mfa_enabled' => false,
        ]);

        $this->actingAs($user)
            ->get(route('onboarding.mfa'))
            ->assertRedirect(route('onboarding.mobile'));
    }
}
