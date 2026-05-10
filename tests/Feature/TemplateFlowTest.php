<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\SignatureFieldType;
use App\Enums\SigningMethod;
use App\Enums\TemplateRoleType;
use App\Enums\TemplateSigningMethod;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\Template;
use App\Models\TemplateField;
use App\Models\TemplateSigner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
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

    public function test_template_role_type_marks_signer_approver_and_recipient_as_active(): void
    {
        $this->assertSame(['signer', 'approver', 'recipient'], TemplateRoleType::activeValues());
        $this->assertTrue(TemplateRoleType::Signer->isActive());
        $this->assertTrue(TemplateRoleType::Approver->isActive());
        $this->assertTrue(TemplateRoleType::Recipient->isActive());
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
            'email_subject' => 'Please sign the standard contract',
            'email_message' => "Hi Jane,\nPlease review and sign this contract today.",
            'audit_enabled' => false,
            'audit_settings' => [
                'show_email' => false,
                'show_document_id' => false,
                'show_author' => false,
                'show_mobile' => false,
                'show_id_details' => false,
            ],
            'signing_method' => TemplateSigningMethod::EmailLink,
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
        $this->assertSame('Please sign the standard contract', $document->email_subject);
        $this->assertSame("Hi Jane,\nPlease review and sign this contract today.", $document->email_message);
        $this->assertFalse($document->isAuditTrailEnabled());
        $this->assertSame([
            'show_email' => false,
            'show_document_id' => false,
            'show_author' => false,
            'show_mobile' => false,
            'show_id_details' => false,
        ], $document->audit_settings);
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

    public function test_use_template_can_create_password_protected_document(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $user = User::factory()->create();
        $pdf = UploadedFile::fake()->create('base.pdf', 50, 'application/pdf');
        $storedPath = $pdf->store('templates', 'public');

        $template = Template::factory()->for($user)->create([
            'name' => 'Protected standard contract',
            'files' => [$storedPath],
            'signing_method' => TemplateSigningMethod::EmailLink,
        ]);

        TemplateSigner::query()->create([
            'template_id' => $template->id,
            'role_name' => 'Client',
            'role_type' => TemplateRoleType::Signer,
            'signing_order' => 0,
        ]);

        $this->actingAs($user)->post(route('templates.documents.store', $template), [
            'document_title' => 'Protected template contract',
            'access_password' => 'shared-secret',
            'access_password_confirmation' => 'shared-secret',
            'access_password_hint' => 'Shared in chat',
            'assignees' => [
                'Client' => [
                    'name' => 'Jane Doe',
                    'email' => 'jane@example.com',
                ],
            ],
        ])->assertRedirect();

        $document = Document::query()->where('title', 'Protected template contract')->first();
        $this->assertNotNull($document);
        $this->assertTrue($document->hasAccessPassword());
        $this->assertTrue(Hash::check('shared-secret', (string) $document->access_password_hash));
        $this->assertSame('Shared in chat', $document->access_password_hint);
    }

    public function test_use_template_propagates_account_verified_signing_method_and_links_user(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $owner = User::factory()->create();
        $linkedSigner = User::factory()->signer()->organizationMember()->create([
            'organization_id' => $owner->organization_id,
            'email' => 'linked.signer@example.com',
        ]);

        $pdf = UploadedFile::fake()->create('base.pdf', 50, 'application/pdf');
        $storedPath = $pdf->store('templates', 'public');

        $template = Template::factory()->for($owner)->create([
            'name' => 'Account verified contract',
            'files' => [$storedPath],
            'signing_method' => TemplateSigningMethod::AccountVerified,
        ]);

        TemplateSigner::query()->create([
            'template_id' => $template->id,
            'role_name' => 'Client',
            'role_type' => TemplateRoleType::Signer,
            'signing_order' => 0,
        ]);

        $this->actingAs($owner)->post(route('templates.documents.store', $template), [
            'document_title' => 'Verified account contract',
            'assignees' => [
                'Client' => [
                    'name' => 'Linked Signer',
                    'email' => 'linked.signer@example.com',
                ],
            ],
        ])->assertRedirect();

        $documentSigner = DocumentSigner::query()->first();
        $this->assertNotNull($documentSigner);
        $this->assertSame(SigningMethod::AccountVerified, $documentSigner->signing_method);
        $this->assertSame($linkedSigner->id, $documentSigner->user_id);
    }

    public function test_use_template_rejects_account_verified_signer_without_existing_org_account(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $owner = User::factory()->create();
        $pdf = UploadedFile::fake()->create('base.pdf', 50, 'application/pdf');
        $storedPath = $pdf->store('templates', 'public');

        $template = Template::factory()->for($owner)->create([
            'name' => 'Account verified contract',
            'files' => [$storedPath],
            'signing_method' => TemplateSigningMethod::AccountVerified,
        ]);

        TemplateSigner::query()->create([
            'template_id' => $template->id,
            'role_name' => 'Client',
            'role_type' => TemplateRoleType::Signer,
            'signing_order' => 0,
        ]);

        $this->actingAs($owner)->from(route('templates.use', $template))->post(route('templates.documents.store', $template), [
            'document_title' => 'Verified account contract',
            'assignees' => [
                'Client' => [
                    'name' => 'External Signer',
                    'email' => 'external.signer@example.com',
                ],
            ],
        ])->assertRedirect(route('templates.use', $template))
            ->assertSessionHasErrors(['assignees.Client.email']);
    }

    public function test_use_template_propagates_pki_certificate_signing_method(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $owner = User::factory()->create();
        $pdf = UploadedFile::fake()->create('base.pdf', 50, 'application/pdf');
        $storedPath = $pdf->store('templates', 'public');

        $template = Template::factory()->for($owner)->create([
            'name' => 'PKI contract',
            'files' => [$storedPath],
            'signing_method' => TemplateSigningMethod::PkiCertificate,
        ]);

        TemplateSigner::query()->create([
            'template_id' => $template->id,
            'role_name' => 'Client',
            'role_type' => TemplateRoleType::Signer,
            'signing_order' => 0,
        ]);

        $this->actingAs($owner)->post(route('templates.documents.store', $template), [
            'document_title' => 'PKI template contract',
            'assignees' => [
                'Client' => [
                    'name' => 'Jane Doe',
                    'email' => 'jane@example.com',
                ],
            ],
        ])->assertRedirect();

        $documentSigner = DocumentSigner::query()->first();
        $this->assertNotNull($documentSigner);
        $this->assertSame(SigningMethod::PkiCertificate, $documentSigner->signing_method);
        $this->assertNull($documentSigner->user_id);
    }

    public function test_use_template_creates_approver_and_recipient_participants(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $owner = User::factory()->create();
        $pdf = UploadedFile::fake()->create('base.pdf', 50, 'application/pdf');
        $storedPath = $pdf->store('templates', 'public');

        $template = Template::factory()->for($owner)->create([
            'name' => 'Approval workflow contract',
            'files' => [$storedPath],
            'signing_method' => TemplateSigningMethod::EmailLink,
        ]);

        TemplateSigner::query()->create([
            'template_id' => $template->id,
            'role_name' => 'Reviewer',
            'role_type' => TemplateRoleType::Approver,
            'signing_order' => 1,
        ]);
        TemplateSigner::query()->create([
            'template_id' => $template->id,
            'role_name' => 'Client',
            'role_type' => TemplateRoleType::Signer,
            'signing_order' => 2,
        ]);
        TemplateSigner::query()->create([
            'template_id' => $template->id,
            'role_name' => 'Archive',
            'role_type' => TemplateRoleType::Recipient,
            'signing_order' => 3,
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

        $this->actingAs($owner)->post(route('templates.documents.store', $template), [
            'document_title' => 'Approval workflow contract',
            'assignees' => [
                'Reviewer' => ['name' => 'Legal Reviewer', 'email' => 'reviewer@example.com'],
                'Client' => ['name' => 'Jane Doe', 'email' => 'jane@example.com'],
                'Archive' => ['name' => 'Records Team', 'email' => 'records@example.com'],
            ],
        ])->assertRedirect();

        $document = Document::query()->where('title', 'Approval workflow contract')->firstOrFail();
        $this->assertDatabaseHas('document_signers', [
            'document_id' => $document->id,
            'role_name' => 'Reviewer',
            'role_type' => TemplateRoleType::Approver->value,
        ]);
        $this->assertDatabaseHas('document_signers', [
            'document_id' => $document->id,
            'role_name' => 'Client',
            'role_type' => TemplateRoleType::Signer->value,
        ]);
        $this->assertDatabaseHas('document_signers', [
            'document_id' => $document->id,
            'role_name' => 'Archive',
            'role_type' => TemplateRoleType::Recipient->value,
        ]);
        $this->assertSame(1, $document->signatureFields()->count());
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
            'signing_method' => TemplateSigningMethod::EmailLink,
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
