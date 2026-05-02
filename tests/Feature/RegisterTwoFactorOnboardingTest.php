<?php

namespace Tests\Feature;

use App\Enums\OnboardingStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RegisterTwoFactorOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_email_verification_link_advances_to_mobile_onboarding(): void
    {
        $user = User::factory()->signer()->create([
            'email_verified_at' => null,
            'onboarding_step' => OnboardingStep::EmailVerification,
            'mfa_enabled' => false,
        ]);

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(5), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->get($url)->assertRedirect(route('onboarding.mobile'));

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(OnboardingStep::MobileVerification, $user->onboarding_step);
        $this->assertAuthenticatedAs($user);
    }
}
