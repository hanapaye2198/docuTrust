<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt as LivewireVolt;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_e_notary_login_mode_can_be_rendered_from_login_screen(): void
    {
        $response = $this->get('/login?mode=enotary');

        $response->assertOk();
        $response->assertSee('e-Notary', escape: false);
        $response->assertSee('Notary workspace', escape: false);
    }

    public function test_legacy_e_notary_login_route_redirects_to_login_mode(): void
    {
        $response = $this->get('/e-notary/login');

        $response->assertRedirect('/login?mode=enotary');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = LivewireVolt::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login');

        $response
            ->assertHasNoErrors()
            ->assertRedirect(route($user->homeRouteName(), absolute: false));

        $this->assertAuthenticated();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_with_confirmed_two_factor_are_redirected_to_challenge_after_password_login(): void
    {
        $user = User::factory()->create([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
            'two_factor_secret' => app(TwoFactorAuthenticationService::class)->generateSecretKey(),
        ]);

        $response = LivewireVolt::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login');

        $response
            ->assertHasNoErrors()
            ->assertRedirect(route('two-factor.challenge', absolute: false));
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
