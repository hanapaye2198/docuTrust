<?php

namespace Database\Factories;

use App\Models\AttorneyNotarialRegistry;
use App\Models\NotaryRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttorneyNotarialRegistry>
 */
class AttorneyNotarialRegistryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'notary_request_id' => NotaryRequest::factory(),
            'entry_no' => $this->faker->bothify('DRAFT-###'),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->sentence(),
            'parties' => [
                ['name' => $this->faker->name(), 'address' => $this->faker->address()],
            ],
            'witnesses' => [],
            'competent_evidence' => [
                ['person_name' => $this->faker->name(), 'id_type' => 'Passport', 'id_number' => strtoupper($this->faker->bothify('P######'))],
            ],
            'notarization_timestamps' => [],
            'notarial_act_type' => 'acknowledgment',
            'fees' => 500.00,
            'official_receipt_no' => strtoupper($this->faker->bothify('OR-#####')),
            'notary_signature_path' => null,
        ];
    }
}
