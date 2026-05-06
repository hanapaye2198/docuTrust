<?php

namespace Database\Factories;

use App\Models\DocumentSigner;
use App\Models\TrustAuthorizationSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrustAuthorizationSession>
 */
class TrustAuthorizationSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_signer_id' => DocumentSigner::factory(),
            'provider_name' => 'trust_service_provider',
            'credential_id' => 'credential-'.fake()->uuid(),
            'authorization_mode' => 'explicit',
            'status' => 'authorized',
            'authorization_reference' => 'auth-'.fake()->uuid(),
            'sad' => 'sad-token-'.fake()->uuid(),
            'access_token' => null,
            'expires_at' => now()->addMinutes(10),
            'completed_at' => now(),
            'payload' => [
                'auth_mode' => 'otp',
            ],
        ];
    }
}
