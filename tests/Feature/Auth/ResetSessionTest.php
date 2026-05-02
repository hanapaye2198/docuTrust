<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\AuthSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResetSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_session_clears_auth_related_session_data_and_logs_out_user(): void
    {
        $user = User::factory()->signer()->create();

        $response = $this->actingAs($user)
            ->withSession([
                AuthSession::TWO_FACTOR_PASSED => true,
                AuthSession::REGISTER_PENDING_DATA => ['email' => 'sample@example.com'],
                AuthSession::PENDING_TWO_FACTOR_USER_ID => $user->id,
                AuthSession::PENDING_TWO_FACTOR_REMEMBER => true,
                AuthSession::SETUP_SECRET => 'SECRET',
                AuthSession::REGISTER_TWO_FACTOR_SECRET => 'PENDING',
                AuthSession::REGISTER_TWO_FACTOR_USER_ID => $user->id,
            ])
            ->get(route('session.reset'));

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('status')
            ->assertSessionMissing(AuthSession::TWO_FACTOR_PASSED)
            ->assertSessionMissing(AuthSession::REGISTER_PENDING_DATA)
            ->assertSessionMissing(AuthSession::PENDING_TWO_FACTOR_USER_ID)
            ->assertSessionMissing(AuthSession::PENDING_TWO_FACTOR_REMEMBER)
            ->assertSessionMissing(AuthSession::SETUP_SECRET)
            ->assertSessionMissing(AuthSession::REGISTER_TWO_FACTOR_SECRET)
            ->assertSessionMissing(AuthSession::REGISTER_TWO_FACTOR_USER_ID);

        $this->assertGuest();
    }
}
