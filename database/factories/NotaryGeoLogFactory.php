<?php

namespace Database\Factories;

use App\Enums\NotaryGeoVerificationStatus;
use App\Models\NotaryGeoLog;
use App\Models\NotaryRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotaryGeoLog>
 */
class NotaryGeoLogFactory extends Factory
{
    protected $model = NotaryGeoLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'notary_request_id' => NotaryRequest::factory(),
            'notary_signer_id' => null,
            'ip_address' => fake()->ipv4(),
            'country' => 'PH',
            'city' => 'Davao City',
            'latitude' => 7.0731,
            'longitude' => 125.6128,
            'vpn_detected' => false,
            'verification_status' => NotaryGeoVerificationStatus::Passed,
            'verified_at' => now(),
        ];
    }
}
