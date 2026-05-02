<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\SignatureFieldType;
use App\Enums\TemplateRoleType;
use App\Models\Document;
use App\Models\Template;
use App\Models\TemplateField;
use App\Models\TemplateSigner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TemplateFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_templates_index(): void
    {
        $this->get(route('templates.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_templates_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('templates.index'))
            ->assertOk()
            ->assertSee(__('Templates'));
    }

    public function test_use_template_creates_document_and_signers(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $user = User::factory()->create();
        $pdf = UploadedFile::fake()->create('base.pdf', 50, 'application/pdf');
        $storedPath = $pdf->store('templates', 'public');

        $template = Template::factory()->for($user)->create([
            'name' => 'Standard contract',
            'files' => [$storedPath],
        ]);

        TemplateSigner::query()->create([
            'template_id' => $template->id,
            'role_name' => 'Client',
            'role_type' => TemplateRoleType::Signer,
            'signing_order' => 0,
        ]);

        TemplateField::query()->create([
            'template_id' => $template->id,
            'role_name' => 'Client',
            'type' => SignatureFieldType::Signature,
            'position_data' => [
                'x' => 0.1,
                'y' => 0.1,
                'width' => 0.2,
                'height' => 0.05,
            ],
        ]);

        $this->actingAs($user)->post(route('templates.documents.store', $template), [
            'document_title' => 'Acme — Contract',
            'assignees' => [
                'Client' => [
                    'name' => 'Jane Doe',
                    'email' => 'jane@example.com',
                ],
            ],
        ])->assertRedirect();

        $document = Document::query()->first();
        $this->assertNotNull($document);
        $this->assertSame('Acme — Contract', $document->title);
        $this->assertSame(DocumentStatus::Draft, $document->status);
        $this->assertTrue(Storage::disk('local')->exists($document->file_path));
        $this->assertDatabaseHas('document_signers', [
            'document_id' => $document->id,
            'role_name' => 'Client',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);
        $this->assertDatabaseHas('signature_fields', [
            'document_id' => $document->id,
        ]);
    }

    public function test_use_template_aborts_when_template_field_role_has_no_matching_signer(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $pdf = UploadedFile::fake()->create('base.pdf', 50, 'application/pdf');
        $storedPath = $pdf->store('templates', 'public');

        $template = Template::factory()->for($user)->create([
            'name' => 'Broken mapping',
            'files' => [$storedPath],
        ]);

        TemplateSigner::query()->create([
            'template_id' => $template->id,
            'role_name' => 'Client',
            'role_type' => TemplateRoleType::Signer,
            'signing_order' => 0,
        ]);

        TemplateField::query()->create([
            'template_id' => $template->id,
            'role_name' => 'UnknownRole',
            'type' => SignatureFieldType::Signature,
            'position_data' => [
                'x' => 0.1,
                'y' => 0.1,
                'width' => 0.2,
                'height' => 0.05,
            ],
        ]);

        $this->actingAs($user)->post(route('templates.documents.store', $template), [
            'document_title' => 'X',
            'assignees' => [
                'Client' => [
                    'name' => 'Jane Doe',
                    'email' => 'jane@example.com',
                ],
            ],
        ])->assertStatus(422);
    }
}
