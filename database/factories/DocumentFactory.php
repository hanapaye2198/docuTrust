<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'file_path' => 'documents/'.fake()->uuid().'.pdf',
            'email_subject' => null,
            'email_message' => null,
            'audit_enabled' => true,
            'audit_settings' => Document::defaultAuditSettings(),
            'signing_workflow' => Document::SIGNING_WORKFLOW_SEQUENTIAL,
            'status' => DocumentStatus::Draft,
        ];
    }
}
