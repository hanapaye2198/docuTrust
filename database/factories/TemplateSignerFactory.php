<?php

namespace Database\Factories;

use App\Enums\TemplateRoleType;
use App\Models\Template;
use App\Models\TemplateSigner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TemplateSigner>
 */
class TemplateSignerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'template_id' => Template::factory(),
            'role_name' => fake()->unique()->word(),
            'role_type' => TemplateRoleType::Signer,
            'signing_order' => 0,
        ];
    }
}
