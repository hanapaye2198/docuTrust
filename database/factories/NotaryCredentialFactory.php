<?php

namespace Database\Factories;

use App\Models\NotaryCredential;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotaryCredential>
 */
class NotaryCredentialFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'commission_number' => fake()->numerify('CN-####-####'),
            'commission_jurisdiction' => 'Philippines',
            'commission_issued_at' => now()->subYear(),
            'commission_expires_at' => now()->addYears(2),
            'roll_number' => fake()->numerify('#####'),
            'ibp_number' => fake()->numerify('IBP-####'),
            'ptr_number' => fake()->numerify('PTR-####'),
            'mcle_compliance_number' => fake()->numerify('MCLE-####'),
            'seal_image_path' => null,
            'signature_image_path' => null,
            'status' => 'active',
            'submitted_at' => now(),
            'is_renewal' => false,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
            'submitted_at' => now(),
            'reviewed_at' => null,
            'reviewed_by_user_id' => null,
            'rejection_reason' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'commission_expires_at' => now()->subMonth(),
            'status' => 'expired',
        ]);
    }
}
