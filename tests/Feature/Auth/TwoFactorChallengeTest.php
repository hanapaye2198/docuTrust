<?php

namespace Tests\Feature\Auth;

use App\Enums\OnboardingStep;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use App\Support\AuthSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
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
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $user = User::factory()->create([
            'two_factor_secret' => $secret,
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
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
            'two_factor_confirmed_at' => now(),
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
            'two_factor_confirmed_at' => now(),
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
            'two_factor_confirmed_at' => now(),
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

    public function test_documents_route_forces_two_factor_challenge_when_two_factor_is_not_verified(): void
    {
        $user = User::factory()->signer()->create([
            'onboarding_step' => OnboardingStep::Completed,
            'mfa_enabled' => true,
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession([
                AuthSession::TWO_FACTOR_PASSED => false,
                AuthSession::PENDING_TWO_FACTOR_STARTED_AT => now()->timestamp,
            ])
            ->get(route('documents.index'))
            ->assertStatus(302)
            ->assertRedirect(route('two-factor.challenge'));
    }

    public function test_user_can_use_recovery_code_during_two_factor_challenge(): void
    {
        $user = User::factory()->create([
            'two_factor_secret' => app(TwoFactorAuthenticationService::class)->generateSecretKey(),
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => ['test-1111', 'test-2222'],
        ]);

        $this->actingAs($user)
            ->withSession([
                AuthSession::TWO_FACTOR_PASSED => false,
                AuthSession::PENDING_TWO_FACTOR_STARTED_AT => now()->timestamp,
            ])
            ->post(route('two-factor.verify'), [
                'recovery_code' => 'test-1111',
            ])
            ->assertRedirect(route($user->homeRouteName()));

        $remainingCodes = $user->fresh()->two_factor_recovery_codes;
        $this->assertSame(['test-2222'], $remainingCodes);
    }

    public function test_user_cannot_use_last_remaining_recovery_code(): void
    {
        $user = User::factory()->create([
            'two_factor_secret' => app(TwoFactorAuthenticationService::class)->generateSecretKey(),
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => ['last-0001'],
        ]);

        $this->actingAs($user)
            ->withSession([
                AuthSession::TWO_FACTOR_PASSED => false,
                AuthSession::PENDING_TWO_FACTOR_STARTED_AT => now()->timestamp,
            ])
            ->from(route('two-factor.challenge'))
            ->post(route('two-factor.verify'), [
                'recovery_code' => 'last-0001',
            ])
            ->assertRedirect(route('two-factor.challenge'))
            ->assertSessionHasErrors('code');
    }

    public function test_login_remember_preference_creates_trusted_device_after_two_factor(): void
    {
        $twoFactor = app(TwoFactorAuthenticationService::class);
        $secret = $twoFactor->generateSecretKey();
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $user = User::factory()->signer()->create([
            'onboarding_step' => OnboardingStep::Completed,
            'mfa_enabled' => true,
            'two_factor_secret' => $secret,
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession([
                AuthSession::TWO_FACTOR_PASSED => false,
                AuthSession::PENDING_TWO_FACTOR_STARTED_AT => now()->timestamp,
                AuthSession::PENDING_TWO_FACTOR_REMEMBER => true,
            ])
            ->withServerVariables([
                'HTTP_USER_AGENT' => 'PHPUnitLoginRememberDevice',
                'HTTP_ACCEPT_LANGUAGE' => 'en',
                'REMOTE_ADDR' => '127.0.0.1',
            ])
            ->post(route('two-factor.verify'), [
                'code' => $code,
            ])
            ->assertRedirect(route($user->homeRouteName()));

        $this->assertDatabaseCount('trusted_devices', 1);
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_remember_device_creates_trusted_device_record(): void
    {
        $twoFactor = app(TwoFactorAuthenticationService::class);
        $secret = $twoFactor->generateSecretKey();
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $user = User::factory()->signer()->create([
            'onboarding_step' => OnboardingStep::Completed,
            'mfa_enabled' => true,
            'two_factor_secret' => $secret,
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession([
                AuthSession::TWO_FACTOR_PASSED => false,
                AuthSession::PENDING_TWO_FACTOR_STARTED_AT => now()->timestamp,
            ])
            ->withServerVariables([
                'HTTP_USER_AGENT' => 'PHPUnitTrustedDevice',
                'HTTP_ACCEPT_LANGUAGE' => 'en',
                'REMOTE_ADDR' => '127.0.0.1',
            ])
            ->post(route('two-factor.verify'), [
                'code' => $code,
                'remember_device' => '1',
            ])
            ->assertRedirect(route($user->homeRouteName()));

        $this->assertDatabaseCount('trusted_devices', 1);
        $device = TrustedDevice::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($device);
        $this->assertNull($device->revoked_at);
        $this->assertTrue($device->expires_at->isFuture());
    }
}
