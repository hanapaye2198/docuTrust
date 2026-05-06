<?php

namespace Database\Factories;

use App\Enums\DocumentSignerStatus;
use App\Models\Document;
use App\Models\DocumentSigner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DocumentSigner>
 */
class DocumentSignerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'remote_credential_id' => 'credential-'.Str::lower((string) Str::uuid()),
            'access_token' => (string) Str::uuid(),
            'status' => DocumentSignerStatus::Pending,
            'signing_order' => 0,
            'signed_at' => null,
            'expires_at' => now()->addDays(7),
        ];
    }
}
