<?php

namespace Database\Factories;

use App\Enums\SignatureFieldType;
use App\Models\Template;
use App\Models\TemplateField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TemplateField>
 */
class TemplateFieldFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'template_id' => Template::factory(),
            'role_name' => 'Client',
            'type' => SignatureFieldType::Signature,
            'position_data' => [
                'x' => 0.1,
                'y' => 0.1,
                'width' => 0.2,
                'height' => 0.05,
            ],
        ];
    }
}
