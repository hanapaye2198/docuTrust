<?php

namespace Database\Factories;

use App\Enums\EkycStatus;
use App\Enums\OnboardingStep;
use App\Enums\OrganizationRole;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        return [
            'organization_id' => Organization::factory(),
            'first_name' => $firstName,
            'middle_name' => null,
            'last_name' => $lastName,
            'suffix' => null,
            'name' => $firstName.' '.$lastName,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'onboarding_step' => OnboardingStep::Completed,
            'ekyc_status' => EkycStatus::Verified,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::NotaryAdmin,
            'organization_role' => OrganizationRole::Admin,
            'mfa_enabled' => true,
            'two_factor_secret' => null,
            'two_factor_enabled' => false,
            'two_factor_onboarding_completed_at' => now(),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function signer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Client,
        ]);
    }

    public function notary(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Notary,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::NotaryAdmin,
        ]);
    }

    public function client(): static
    {
        return $this->signer();
    }

    public function notaryAdmin(): static
    {
        return $this->admin();
    }

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::SuperAdmin,
        ]);
    }

    public function organizationMember(): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_role' => OrganizationRole::Member,
        ]);
    }
}
