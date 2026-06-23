<?php

namespace Database\Factories;

use App\Models\NotaryAppointment;
use App\Models\NotaryAvailabilitySlot;
use App\Models\NotaryRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotaryAppointment>
 */
class NotaryAppointmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'notary_availability_slot_id' => NotaryAvailabilitySlot::factory()->booked(),
            'notary_request_id' => NotaryRequest::factory(),
            'client_user_id' => User::factory()->enotarySigner(),
            'notary_user_id' => User::factory()->notary(),
            'status' => 'pending',
            'notes' => fake()->optional()->sentence(),
            'meeting_link' => null,
            'confirmed_at' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => fake()->sentence(),
        ]);
    }
}
