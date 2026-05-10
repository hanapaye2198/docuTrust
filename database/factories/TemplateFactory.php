<?php

namespace Database\Factories;

use App\Enums\TemplateSigningMethod;
use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Template>
 */
class TemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true).' Template',
            'files' => ['templates/placeholder.pdf'],
            'document_workflow' => false,
            'email_subject' => null,
            'email_message' => null,
            'signing_method' => TemplateSigningMethod::AccountVerified,
            'audit_enabled' => true,
            'audit_settings' => Template::defaultAuditSettings(),
        ];
    }
}
