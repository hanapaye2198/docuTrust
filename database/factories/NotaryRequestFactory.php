<?php

namespace Database\Factories;

use App\Enums\NotaryRequestStatus;
use App\Models\NotaryRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotaryRequest>
 */
class NotaryRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'status' => NotaryRequestStatus::Draft,
            'request_type' => 'acknowledgment',
            'metadata' => [],
        ];
    }
}
