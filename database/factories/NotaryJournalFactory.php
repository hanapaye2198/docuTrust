<?php

namespace Database\Factories;

use App\Models\NotaryJournal;
use App\Models\NotaryRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotaryJournal>
 */
class NotaryJournalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'notary_request_id' => NotaryRequest::factory(),
            'notary_user_id' => User::factory(),
            'entry_type' => 'note',
            'summary' => fake()->sentence(),
            'legal_assertions' => [],
            'recorded_at' => now(),
        ];
    }
}
