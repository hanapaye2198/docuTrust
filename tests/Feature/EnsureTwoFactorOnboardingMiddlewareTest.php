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
            'onboarding_step' => OnboardingStep::EmailVerified,
        ]);

        $this->actingAs($user)
            ->get(route('onboarding.phone.verify'))
            ->assertOk();
    }

    public function test_it_redirects_to_expected_step_when_onboarding_is_incomplete(): void
    {
        $user = User::factory()->signer()->create([
            'onboarding_step' => OnboardingStep::Registered,
        ]);

        $response = $this->actingAs($user)->get(route('documents.index'));

        $response->assertRedirect(route('onboarding.email.notice'));
    }
}
