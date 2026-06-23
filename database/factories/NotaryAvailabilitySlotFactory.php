<?php

namespace Database\Factories;

use App\Models\NotaryAvailabilitySlot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotaryAvailabilitySlot>
 */
class NotaryAvailabilitySlotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 day', '+2 weeks')->setTime(fake()->numberBetween(9, 16), 0);
        $end = (clone $start)->modify('+1 hour');

        return [
            'notary_user_id' => User::factory()->notary(),
            'date' => $start->format('Y-m-d'),
            'start_time' => $start->format('H:i'),
            'end_time' => $end->format('H:i'),
            'is_booked' => false,
            'is_blocked' => false,
            'duration_minutes' => 60,
        ];
    }

    public function booked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_booked' => true,
        ]);
    }

    public function blocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_blocked' => true,
        ]);
    }
}
