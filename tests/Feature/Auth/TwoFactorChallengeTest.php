<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use App\Support\AuthSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorChallengeTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_factor_page_redirects_without_authenticated_user(): void
    {
        $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));
    }

    public function test_user_can_complete_login_with_valid_totp_code(): void
    {
        $twoFactor = app(TwoFactorAuthenticationService::class);
        $secret = $twoFactor->generateSecretKey();
        $code = app(\PragmaRX\Google2FA\Google2FA::class)->getCurrentOtp($secret);

        $user = User::factory()->create([
            'two_factor_secret' => $secret,
            'two_factor_enabled' => true,
        ]);

        $this->actingAs($user)
            ->withSession([
                AuthSession::TWO_FACTOR_PASSED => false,
                AuthSession::PENDING_TWO_FACTOR_STARTED_AT => now()->timestamp,
            ])
            ->post(route('two-factor.verify'), [
                'code' => $code,
            ])
            ->assertRedirect(route($user->homeRouteName()));

        $this->assertAuthenticatedAs($user);
        $this->assertTrue((bool) session(AuthSession::TWO_FACTOR_PASSED));
    }

    public function test_two_factor_page_loads_when_user_is_authenticated_but_not_verified(): void
    {
        $user = User::factory()->create([
            'two_factor_enabled' => true,
        ]);

        $this->actingAs($user)
            ->withSession([
                AuthSession::TWO_FACTOR_PASSED => false,
                AuthSession::PENDING_TWO_FACTOR_STARTED_AT => now()->timestamp,
            ])
            ->get(route('two-factor.challenge'))
            ->assertOk()
            ->assertSeeText('Authentication code');
    }

    public function test_two_factor_rejects_invalid_code(): void
    {
        $twoFactor = app(TwoFactorAuthenticationService::class);
        $secret = $twoFactor->generateSecretKey();

        $user = User::factory()->create([
            'two_factor_secret' => $secret,
            'two_factor_enabled' => true,
        ]);

        $this->actingAs($user)
            ->withSession([
                AuthSession::TWO_FACTOR_PASSED => false,
                AuthSession::PENDING_TWO_FACTOR_STARTED_AT => now()->timestamp,
            ])
            ->from(route('two-factor.challenge'))
            ->post(route('two-factor.verify'), [
                'code' => '000000',
            ])
            ->assertRedirect(route('two-factor.challenge'))
            ->assertSessionHasErrors('code');

        $this->assertAuthenticatedAs($user);
        $this->assertFalse((bool) session(AuthSession::TWO_FACTOR_PASSED));
    }

    public function test_two_factor_challenge_expires_when_session_is_stale(): void
    {
        $user = User::factory()->create([
            'two_factor_enabled' => true,
        ]);

        $this->actingAs($user)
            ->withSession([
                AuthSession::TWO_FACTOR_PASSED => false,
                AuthSession::PENDING_TWO_FACTOR_STARTED_AT => now()->subMinutes(11)->timestamp,
            ])
            ->get(route('two-factor.challenge'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your verification session has expired. Please sign in again.');

        $this->assertGuest();
    }

    public function test_documents_route_does_not_force_two_factor_challenge_when_two_factor_is_not_verified(): void
    {
        $user = User::factory()->signer()->create([
            'two_factor_enabled' => true,
        ]);

        $this->actingAs($user)
            ->withSession([
                AuthSession::TWO_FACTOR_PASSED => false,
                AuthSession::PENDING_TWO_FACTOR_STARTED_AT => now()->timestamp,
            ])
            ->get(route('documents.index'))
            ->assertStatus(302)
            ->assertRedirect(route('documents.index'));
    }
}
