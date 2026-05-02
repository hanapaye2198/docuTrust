<?php

namespace Database\Factories;

use App\Enums\SignatureFieldType;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\SignatureField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SignatureField>
 */
class SignatureFieldFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'signer_id' => function (array $attributes) {
                return DocumentSigner::factory()->create([
                    'document_id' => $attributes['document_id'],
                ])->id;
            },
            'type' => SignatureFieldType::Signature,
            'page_number' => 1,
            'position_data' => [
                'x' => 0.1,
                'y' => 0.2,
                'width' => 0.25,
                'height' => 0.08,
            ],
        ];
    }
}
