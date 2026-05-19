<?php

namespace Tests\Feature\Auth;

use App\Enums\EkycStatus;
use App\Enums\OnboardingStep;
use App\Enums\UserRole;
use App\Mail\EmailOtpVerificationMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        Mail::fake();

        $response = Volt::test('auth.register')
            ->set('first_name', 'Test')
            ->set('middle_name', 'Sample')
            ->set('last_name', 'User')
            ->set('suffix', 'Jr.')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('agreed_to_terms', true)
            ->call('register');

        $response
            ->assertHasNoErrors()
            ->assertRedirect(route('onboarding.email.verify', absolute: false));

        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'role' => UserRole::Client->value,
            'onboarding_step' => OnboardingStep::EmailVerification->value,
            'ekyc_status' => EkycStatus::NotSubmitted->value,
        ]);

        $user = User::query()->where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('Test Sample User Jr.', $user->name);
        $this->assertSame('Test', $user->first_name);
        $this->assertSame('Sample', $user->middle_name);
        $this->assertSame('User', $user->last_name);
        $this->assertSame('Jr.', $user->suffix);
        $this->assertFalse((bool) $user->two_factor_enabled);
        $this->assertFalse($user->mfa_enabled);
        $this->assertNotNull($user->email_otp);

        Mail::assertSent(EmailOtpVerificationMail::class);
    }

    public function test_terms_must_be_accepted_before_registration(): void
    {
        $response = Volt::test('auth.register')
            ->set('first_name', 'Test')
            ->set('last_name', 'User')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('agreed_to_terms', false)
            ->call('register');

        $response->assertHasErrors(['agreed_to_terms']);
        $this->assertGuest();
    }
}
