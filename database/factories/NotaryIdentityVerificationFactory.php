<?php

namespace Database\Factories;

use App\Enums\NotaryIdentityVerificationStatus;
use App\Models\NotaryIdentityVerification;
use App\Models\NotarySigner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotaryIdentityVerification>
 */
class NotaryIdentityVerificationFactory extends Factory
{
    protected $model = NotaryIdentityVerification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'notary_signer_id' => NotarySigner::factory(),
            'id_type' => 'passport',
            'id_number' => fake()->numerify('P########'),
            'id_image_path' => 'notary/identity/'.fake()->uuid().'.pdf',
            'selfie_image_path' => 'notary/identity/'.fake()->uuid().'.jpg',
            'verification_status' => NotaryIdentityVerificationStatus::Pending,
            'verified_by' => null,
            'verified_at' => null,
            'rejection_reason' => null,
        ];
    }
}
