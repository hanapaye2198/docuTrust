<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_session_password_hash_logs_out_before_protected_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        session()->put('password_hash_web', 'invalid-hash');

        $response = $this->get(route('documents.index'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_home_page_shows_guest_actions_when_not_authenticated(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Get Started', false);
        $response->assertDontSee('Dashboard', false);
    }
}
