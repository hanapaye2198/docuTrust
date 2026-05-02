<?php

namespace Database\Factories;

use App\Enums\OrganizationRole;
use App\Enums\EkycStatus;
use App\Enums\OnboardingStep;
use App\Enums\UserRole;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
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
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'onboarding_step' => OnboardingStep::Completed,
            'ekyc_status' => EkycStatus::Verified,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::Admin,
            'organization_role' => OrganizationRole::Admin,
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
            'role' => UserRole::Signer,
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
            'role' => UserRole::Admin,
        ]);
    }

    public function organizationMember(): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_role' => OrganizationRole::Member,
        ]);
    }
}
