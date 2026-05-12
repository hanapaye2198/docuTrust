<?php

namespace Database\Factories;

use App\Models\NotaryRequest;
use App\Models\NotarySession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotarySession>
 */
class NotarySessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'notary_request_id' => NotaryRequest::factory(),
            'notary_user_id' => null,
            'provider_name' => 'manual',
            'status' => 'scheduled',
            'room_name' => fake()->bothify('notary-room-###'),
            'meeting_url' => fake()->url(),
            'host_reference' => fake()->uuid(),
            'scheduled_for' => now()->addDay(),
            'evidence' => [],
        ];
    }
}
