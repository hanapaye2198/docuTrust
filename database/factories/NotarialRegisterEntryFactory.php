<?php

namespace Database\Factories;

use App\Models\NotarialRegisterEntry;
use App\Models\NotaryCredential;
use App\Models\NotaryRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NotarialRegisterEntry>
 */
class NotarialRegisterEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'notary_request_id' => NotaryRequest::factory(),
            'notary_credential_id' => NotaryCredential::factory(),
            'document_id' => null,
            'entry_number' => fake()->numberBetween(1, 999),
            'entry_year' => (int) now()->format('Y'),
            'document_title' => fake()->sentence(4),
            'document_description' => fake()->sentence(),
            'parties' => [
                ['name' => fake()->name(), 'address' => fake()->address()],
            ],
            'witnesses' => [],
            'competent_evidence' => [
                ['person_name' => fake()->name(), 'id_type' => 'Passport', 'id_number' => fake()->numerify('P##########')],
            ],
            'notarized_at' => now(),
            'notarial_act_type' => fake()->randomElement(['acknowledgment', 'jurat', 'affidavit', 'oath']),
            'fees' => fake()->randomFloat(2, 100, 1000),
            'official_receipt_number' => fake()->numerify('OR: ######'),
            'notary_signature_path' => null,
            'qr_code_path' => null,
            'qr_verification_token' => Str::uuid()->toString(),
        ];
    }
}
