<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Org',
            'slug' => fake()->unique()->slug(),
            'plan' => 'free',
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ];
    }
}
