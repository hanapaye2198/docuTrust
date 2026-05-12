<?php

namespace Database\Factories;

use App\Models\NotaryRequest;
use App\Models\NotarySigner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotarySigner>
 */
class NotarySignerFactory extends Factory
{
    protected $model = NotarySigner::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'notary_request_id' => NotaryRequest::factory(),
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'role' => 'signer',
        ];
    }
}
