<?php

namespace Tests\Feature;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Enums\SignatureFieldType;
use App\Enums\SigningMethod;
use App\Enums\TemplateRoleType;
use App\Events\SignRequestReceived;
use App\Livewire\DocumentSignersManager;
use App\Models\Contact;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\SignatureField;
use App\Models\TrustAuthorizationSession;
use App\Models\User;
use App\Support\AuthSession;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Livewire\Volt\Volt as LivewireVolt;
use Tests\TestCase;

class DocumentSignerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function putValidPdf(string $path): void
    {
        Storage::disk('local')->put($path, Pdf::loadHTML('<h1>DocuTrust</h1>')->output());
    }

    public function test_sign_page_loads_for_signer(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Pending]);
        $signer = DocumentSigner::factory()->for($document)->create(['status' => DocumentSignerStatus::Pending]);

        $this->get(route('sign.show', $signer->access_token))
            ->assertOk()
            ->assertSee($document->title);
    }

    public function test_remote_managed_sign_page_shows_trust_authorization_panel(): void
    {
        config()->set('docutrust.pki.signing_backend', 'remote_managed');
        config()->set('services.remote_signing.provider_name', 'trust_service_provider');

        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Pending]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
            'signing_method' => SigningMethod::PkiCertificate,
            'remote_credential_id' => 'credential-ui-001',
        ]);

        SignatureField::factory()->for($document)->create([
            'signer_id' => $signer->id,
            'type' => SignatureFieldType::Signature,
            'position_data' => [
                'x' => 0.2,
                'y' => 0.2,
                'width' => 0.2,
                'height' => 0.05,
            ],
        ]);

        TrustAuthorizationSession::factory()->for($signer, 'signer')->create([
            'provider_name' => 'trust_service_provider',
            'credential_id' => 'credential-ui-001',
            'status' => 'pending',
            'authorization_reference' => 'handle-ui-001',
            'completed_at' => null,
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->get(route('sign.show', $signer->access_token))
            ->assertOk()
            ->assertSee('Trust authorization')
            ->assertSee('credential-ui-001')
            ->assertSee('handle-ui-001')
            ->assertSee('Start authorization');
    }

    public function test_remote_managed_legacy_sign_page_shows_trust_authorization_state(): void
    {
        config()->set('docutrust.pki.signing_backend', 'remote_managed');
        config()->set('services.remote_signing.provider_name', 'trust_service_provider');

        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Pending]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
            'signing_method' => SigningMethod::PkiCertificate,
            'remote_credential_id' => 'credential-legacy-ui-001',
        ]);

        $this->get(route('sign.show', $signer->access_token))
            ->assertOk()
            ->assertSee('Trust authorization')
            ->assertSee('credential-legacy-ui-001')
            ->assertSee('Start authorization')
            ->assertSee('Start trust authorization to enable cloud signing for this document.');
    }

    public function test_pades_sign_page_shows_csc_livewire_authorization_components(): void
    {
        config()->set('signature.pades_enabled', true);

        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Pending]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
            'signing_method' => SigningMethod::PkiCertificate,
            'remote_credential_id' => 'credential-csc-ui-001',
        ]);

        $this->get(route('sign.show', $signer->access_token))
            ->assertOk()
            ->assertSee('CSC cloud credentials')
            ->assertSee('Connect CSC Credentials')
            ->assertSee('Awaiting authorization...');
    }

    public function test_signer_csc_authorize_endpoint_returns_oauth_redirect_url(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Pending]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
        ]);

        $this->postJson(route('sign.csc.authorize', ['token' => $signer->access_token]))
            ->assertOk()
            ->assertJson([
                'status' => 'redirect_required',
            ])
            ->assertJsonPath('redirect_url', route('csc.oauth.redirect').'?'.http_build_query([
                'document_id' => $document->id,
                'signer_id' => $signer->id,
            ]));
    }

    public function test_signing_updates_signer_and_completes_document_when_only_one_signer(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Pending]);
        $signer = DocumentSigner::factory()->for($document)->create(['status' => DocumentSignerStatus::Pending]);

        $this->post(route('sign.store', $signer))
            ->assertRedirect(route('sign.show', $signer->access_token));

        $signer->refresh();
        $this->assertSame(DocumentSignerStatus::Signed, $signer->status);
        $this->assertNotNull($signer->signed_at);

        $document->refresh();
        $this->assertSame(DocumentStatus::Completed, $document->status);
    }

    public function test_remote_managed_legacy_sign_requires_active_trust_authorization(): void
    {
        config()->set('docutrust.pki.signing_backend', 'remote_managed');
        config()->set('services.remote_signing.provider_name', 'trust_service_provider');

        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Pending]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
            'signing_method' => SigningMethod::PkiCertificate,
            'remote_credential_id' => 'credential-legacy-001',
        ]);

        $this->post(route('sign.store', $signer))
            ->assertRedirect(route('sign.show', $signer->access_token))
            ->assertSessionHas('error', 'Start trust authorization before completing your assigned fields.');

        $signer->refresh();
        $document->refresh();
        $this->assertSame(DocumentSignerStatus::Pending, $signer->status);
        $this->assertSame(DocumentStatus::Pending, $document->status);
    }

    public function test_remote_managed_legacy_sign_completes_with_active_trust_authorization(): void
    {
        config()->set('docutrust.pki.signing_backend', 'remote_managed');
        config()->set('services.remote_signing.provider_name', 'trust_service_provider');

        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Pending]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
            'signing_method' => SigningMethod::PkiCertificate,
            'remote_credential_id' => 'credential-legacy-002',
        ]);

        TrustAuthorizationSession::factory()->for($signer, 'signer')->create([
            'provider_name' => 'trust_service_provider',
            'credential_id' => 'credential-legacy-002',
            'authorization_reference' => 'auth-legacy-002',
            'sad' => 'sad-legacy-002',
        ]);

        $this->post(route('sign.store', $signer))
            ->assertRedirect(route('sign.show', $signer->access_token));

        $signer->refresh();
        $document->refresh();
        $this->assertSame(DocumentSignerStatus::Signed, $signer->status);
        $this->assertNotNull($signer->signed_at);
        $this->assertSame(DocumentStatus::Completed, $document->status);
    }

    public function test_multiple_signers_stays_pending_until_all_sign(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Pending]);
        $a = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
            'email' => 'a@example.com',
        ]);
        $b = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
            'email' => 'b@example.com',
        ]);

        $this->post(route('sign.store', $a))->assertRedirect();

        $document->refresh();
        $this->assertSame(DocumentStatus::Pending, $document->status);

        $this->post(route('sign.store', $b))->assertRedirect();

        $document->refresh();
        $this->assertSame(DocumentStatus::Completed, $document->status);
    }

    public function test_sign_is_forbidden_when_document_not_pending(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Draft]);
        $signer = DocumentSigner::factory()->for($document)->create();

        $this->post(route('sign.store', $signer))
            ->assertForbidden()
            ->assertSee('Link expired or invalid');
    }

    public function test_sequential_signer_is_blocked_until_previous_signer_completes(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Pending]);
        DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
            'name' => 'Alice Example',
            'signing_order' => 1,
        ]);
        $secondSigner = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
            'signing_order' => 2,
        ]);

        $this->post(route('sign.store', $secondSigner))
            ->assertRedirect(route('sign.show', $secondSigner->access_token))
            ->assertSessionHas('error', 'You cannot sign yet. Waiting for signer 1 (Alice Example) to finish first.');
    }

    public function test_sequential_signer_can_sign_after_previous_signer_completes(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Pending]);
        $firstSigner = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
            'signing_order' => 1,
        ]);
        $secondSigner = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
            'signing_order' => 2,
        ]);

        $this->post(route('sign.store', $firstSigner))->assertRedirect(route('sign.show', $firstSigner->access_token));
        $this->post(route('sign.store', $secondSigner))->assertRedirect(route('sign.show', $secondSigner->access_token));

        $secondSigner->refresh();
        $this->assertSame(DocumentSignerStatus::Signed, $secondSigner->status);
    }

    public function test_sign_page_shows_clear_sequential_block_message(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Pending]);
        DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
            'name' => 'Alice Example',
            'signing_order' => 1,
        ]);
        $secondSigner = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
            'name' => 'Bob Example',
            'signing_order' => 2,
        ]);

        $this->get(route('sign.show', $secondSigner->access_token))
            ->assertOk()
            ->assertSee('You cannot sign yet. Waiting for signer 1 (Alice Example) to finish first.');
    }

    public function test_parallel_signers_can_sign_in_any_order(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create([
            'status' => DocumentStatus::Pending,
            'signing_workflow' => Document::SIGNING_WORKFLOW_PARALLEL,
        ]);
        $firstSigner = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
            'signing_order' => null,
        ]);
        $secondSigner = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
            'signing_order' => null,
        ]);

        $this->post(route('sign.store', $secondSigner))->assertRedirect(route('sign.show', $secondSigner->access_token));
        $this->post(route('sign.store', $firstSigner))->assertRedirect(route('sign.show', $firstSigner->access_token));

        $firstSigner->refresh();
        $secondSigner->refresh();

        $this->assertSame(DocumentSignerStatus::Signed, $firstSigner->status);
        $this->assertSame(DocumentSignerStatus::Signed, $secondSigner->status);
    }

    public function test_parallel_signer_is_blocked_until_all_approvers_complete(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create([
            'status' => DocumentStatus::Pending,
            'signing_workflow' => Document::SIGNING_WORKFLOW_PARALLEL,
        ]);
        DocumentSigner::factory()->for($document)->create([
            'name' => 'Legal Reviewer',
            'role_type' => TemplateRoleType::Approver,
            'status' => DocumentSignerStatus::Pending,
            'signing_order' => null,
        ]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'name' => 'Business Signer',
            'role_type' => TemplateRoleType::Signer,
            'status' => DocumentSignerStatus::Pending,
            'signing_order' => null,
        ]);

        $this->post(route('sign.store', $signer))
            ->assertRedirect(route('sign.show', $signer->access_token))
            ->assertSessionHas('error', 'You cannot sign yet. Waiting for approver Legal Reviewer to approve first.');
    }

    public function test_approver_can_complete_approval_before_signer(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create([
            'status' => DocumentStatus::Pending,
            'signing_workflow' => Document::SIGNING_WORKFLOW_PARALLEL,
        ]);
        $approver = DocumentSigner::factory()->for($document)->create([
            'name' => 'Legal Reviewer',
            'role_type' => TemplateRoleType::Approver,
            'status' => DocumentSignerStatus::Pending,
        ]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'role_type' => TemplateRoleType::Signer,
            'status' => DocumentSignerStatus::Pending,
        ]);

        $this->post(route('sign.store', $approver))
            ->assertRedirect(route('sign.show', $approver->access_token));

        $approver->refresh();
        $signer->refresh();
        $document->refresh();

        $this->assertSame(DocumentSignerStatus::Approved, $approver->status);
        $this->assertSame(DocumentSignerStatus::Pending, $signer->status);
        $this->assertSame(DocumentStatus::Pending, $document->status);
    }

    public function test_owner_can_switch_signing_workflow_to_parallel(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create([
            'status' => DocumentStatus::Draft,
            'signing_workflow' => Document::SIGNING_WORKFLOW_SEQUENTIAL,
        ]);
        DocumentSigner::factory()->for($document)->create(['signing_order' => 1]);
        DocumentSigner::factory()->for($document)->create(['signing_order' => 2]);

        $this->actingAs($user);

        Livewire::test(DocumentSignersManager::class, ['documentId' => $document->id])
            ->set('signingWorkflow', Document::SIGNING_WORKFLOW_PARALLEL)
            ->call('saveSigningWorkflow')
            ->assertHasNoErrors();

        $document->refresh();
        $this->assertSame(Document::SIGNING_WORKFLOW_PARALLEL, $document->signingWorkflow());
        $this->assertSame(0, $document->documentSigners()->whereNotNull('signing_order')->count());
    }

    public function test_document_can_send_when_sequential_orders_are_unique_but_not_contiguous(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create([
            'status' => DocumentStatus::Draft,
            'signing_workflow' => Document::SIGNING_WORKFLOW_SEQUENTIAL,
        ]);
        $firstSigner = DocumentSigner::factory()->for($document)->create(['signing_order' => 999]);
        $secondSigner = DocumentSigner::factory()->for($document)->create(['signing_order' => 1000]);

        SignatureField::factory()->for($document)->create(['signer_id' => $firstSigner->id]);
        SignatureField::factory()->for($document)->create(['signer_id' => $secondSigner->id]);

        $this->assertTrue($document->fresh(['documentSigners', 'signatureFields'])->canSendForSigning());
    }

    public function test_duplicate_signing_is_blocked(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Pending]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);

        $this->post(route('sign.store', $signer))
            ->assertRedirect(route('sign.show', $signer->access_token))
            ->assertSessionHas('error', 'You have already signed this document.');
    }

    public function test_cancelled_and_declined_documents_cannot_be_signed(): void
    {
        $user = User::factory()->create();
        $cancelledDocument = Document::factory()->for($user)->create(['status' => DocumentStatus::Cancelled]);
        $cancelledSigner = DocumentSigner::factory()->for($cancelledDocument)->create();

        $declinedDocument = Document::factory()->for($user)->create(['status' => DocumentStatus::Declined]);
        $declinedSigner = DocumentSigner::factory()->for($declinedDocument)->create();

        $this->post(route('sign.store', $cancelledSigner))
            ->assertForbidden()
            ->assertSee('Link expired or invalid');

        $this->post(route('sign.store', $declinedSigner))
            ->assertForbidden()
            ->assertSee('Link expired or invalid');
    }

    public function test_owner_can_add_signer_via_livewire(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Draft]);

        $this->actingAs($user);

        Livewire::test(DocumentSignersManager::class, ['documentId' => $document->id])
            ->set('name', 'Jane Doe')
            ->set('email', 'jane@example.com')
            ->set('signingMethod', SigningMethod::EmailLink->value)
            ->call('addSigner')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('document_signers', [
            'document_id' => $document->id,
            'email' => 'jane@example.com',
        ]);

        $this->assertDatabaseHas('contacts', [
            'user_id' => $user->id,
            'email' => 'jane@example.com',
            'name' => 'Jane Doe',
        ]);
    }

    public function test_owner_can_edit_signer_via_livewire(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Draft]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'signing_order' => 1,
        ]);

        $this->actingAs($user);

        Livewire::test(DocumentSignersManager::class, ['documentId' => $document->id])
            ->call('startEditingSigner', $signer->id)
            ->set('editingName', 'Jane Updated')
            ->set('editingEmail', 'updated@example.com')
            ->set('editingSigningMethod', SigningMethod::EmailLink->value)
            ->call('saveSignerEdits')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('document_signers', [
            'id' => $signer->id,
            'name' => 'Jane Updated',
            'email' => 'updated@example.com',
        ]);
    }

    public function test_owner_can_reorder_signers_via_livewire(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Draft]);
        $firstSigner = DocumentSigner::factory()->for($document)->create([
            'name' => 'First',
            'signing_order' => 1,
        ]);
        $secondSigner = DocumentSigner::factory()->for($document)->create([
            'name' => 'Second',
            'signing_order' => 2,
        ]);

        $this->actingAs($user);

        Livewire::test(DocumentSignersManager::class, ['documentId' => $document->id])
            ->call('moveSignerUp', $secondSigner->id)
            ->assertHasNoErrors();

        $firstSigner->refresh();
        $secondSigner->refresh();

        $this->assertSame(2, $firstSigner->signing_order);
        $this->assertSame(1, $secondSigner->signing_order);
    }

    public function test_adding_signer_does_not_duplicate_contact_when_email_already_exists(): void
    {
        $user = User::factory()->create();
        Contact::factory()->for($user)->create([
            'name' => 'Jane',
            'email' => 'jane@example.com',
        ]);
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Draft]);

        $this->actingAs($user);

        Livewire::test(DocumentSignersManager::class, ['documentId' => $document->id])
            ->set('name', 'Jane Doe')
            ->set('email', 'jane@example.com')
            ->set('signingMethod', SigningMethod::EmailLink->value)
            ->call('addSigner')
            ->assertHasNoErrors();

        $this->assertSame(1, Contact::query()->where('user_id', $user->id)->where('email', 'jane@example.com')->count());
    }

    public function test_contact_suggestions_populate_while_typing_signer_name(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Draft]);
        Contact::factory()->for($user)->create([
            'name' => 'Alice Wonder',
            'email' => 'alice@example.com',
        ]);

        $this->actingAs($user);

        $component = Livewire::test(DocumentSignersManager::class, ['documentId' => $document->id])
            ->set('name', 'Ali');

        $this->assertCount(1, $component->get('contactSuggestions'));
    }

    public function test_owner_can_add_account_verified_signer_when_matching_user_exists(): void
    {
        $owner = User::factory()->create();
        $linkedUser = User::factory()->signer()->create([
            'organization_id' => $owner->organization_id,
            'email' => 'member@example.com',
        ]);
        $document = Document::factory()->for($owner)->create(['status' => DocumentStatus::Draft]);

        $this->actingAs($owner);

        Livewire::test(DocumentSignersManager::class, ['documentId' => $document->id])
            ->set('name', 'Member User')
            ->set('email', 'member@example.com')
            ->set('signingMethod', SigningMethod::AccountVerified->value)
            ->call('addSigner')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('document_signers', [
            'document_id' => $document->id,
            'email' => 'member@example.com',
            'signing_method' => SigningMethod::AccountVerified->value,
            'user_id' => $linkedUser->id,
        ]);
    }

    public function test_owner_can_add_recipient_participant_via_livewire(): void
    {
        $owner = User::factory()->create();
        $document = Document::factory()->for($owner)->create(['status' => DocumentStatus::Draft]);

        $this->actingAs($owner);

        Livewire::test(DocumentSignersManager::class, ['documentId' => $document->id])
            ->set('name', 'Records Team')
            ->set('email', 'records@example.com')
            ->set('roleType', TemplateRoleType::Recipient->value)
            ->set('signingMethod', SigningMethod::EmailLink->value)
            ->call('addSigner')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('document_signers', [
            'document_id' => $document->id,
            'email' => 'records@example.com',
            'role_type' => TemplateRoleType::Recipient->value,
        ]);
    }

    public function test_owner_cannot_add_account_verified_signer_without_matching_user(): void
    {
        $owner = User::factory()->create();
        $document = Document::factory()->for($owner)->create(['status' => DocumentStatus::Draft]);

        $this->actingAs($owner);

        Livewire::test(DocumentSignersManager::class, ['documentId' => $document->id])
            ->set('name', 'Missing User')
            ->set('email', 'missing@example.com')
            ->set('signingMethod', SigningMethod::AccountVerified->value)
            ->call('addSigner')
            ->assertHasErrors(['signingMethod']);
    }

    public function test_owner_can_send_for_signature_from_document_page(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $path = 'documents/send-for-signature-source.pdf';
        $this->putValidPdf($path);
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Draft, 'file_path' => $path]);
        $signer = DocumentSigner::factory()->for($document)->create();
        SignatureField::query()->create([
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'type' => SignatureFieldType::Signature,
            'position_data' => [
                'x' => 0.1,
                'y' => 0.1,
                'width' => 0.2,
                'height' => 0.05,
            ],
        ]);

        $this->actingAs($user);

        LivewireVolt::test('documents.show', ['document' => $document])
            ->call('sendForSignature')
            ->assertHasNoErrors();

        $document->refresh();
        $this->assertSame(DocumentStatus::Pending, $document->status);
        $this->assertNotNull($document->sent_at);
        $this->assertNotNull($document->prepared_pdf_path);
        $this->assertTrue(Storage::disk('local')->exists($document->prepared_pdf_path));

        $signer->refresh();
        $this->assertNotNull($signer->access_token);
        $this->assertNotNull($signer->expires_at);
    }

    public function test_owner_can_send_for_signature_from_prepare_page(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $path = 'documents/send-from-prepare-source.pdf';
        $this->putValidPdf($path);
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Draft, 'file_path' => $path]);
        $signer = DocumentSigner::factory()->for($document)->create();
        SignatureField::query()->create([
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'type' => SignatureFieldType::Signature,
            'position_data' => [
                'x' => 0.1,
                'y' => 0.1,
                'width' => 0.2,
                'height' => 0.05,
            ],
        ]);

        $this->actingAs($user)
            ->post(route('documents.send', $document))
            ->assertRedirect(route('documents.show', $document))
            ->assertSessionHas('status', 'Document sent for signature.');

        $document->refresh();
        $signer->refresh();

        $this->assertSame(DocumentStatus::Pending, $document->status);
        $this->assertNotNull($document->prepared_pdf_path);
        $this->assertTrue(Storage::disk('local')->exists($document->prepared_pdf_path));
        $this->assertNotNull($signer->access_token);
        $this->assertNotNull($signer->expires_at);
    }

    public function test_document_cannot_be_sent_when_any_signer_has_no_signature_fields(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Draft]);
        $firstSigner = DocumentSigner::factory()->for($document)->create(['name' => 'First Signer']);
        $secondSigner = DocumentSigner::factory()->for($document)->create(['name' => 'Second Signer']);
        $originalSecondSignerToken = $secondSigner->access_token;
        $originalSecondSignerExpiry = $secondSigner->expires_at?->toDateTimeString();
        SignatureField::query()->create([
            'document_id' => $document->id,
            'signer_id' => $firstSigner->id,
            'type' => SignatureFieldType::Signature,
            'position_data' => [
                'x' => 0.1,
                'y' => 0.1,
                'width' => 0.2,
                'height' => 0.05,
            ],
        ]);

        $this->actingAs($user);

        LivewireVolt::test('documents.show', ['document' => $document])
            ->call('sendForSignature')
            ->assertHasErrors(['send']);

        $document->refresh();
        $this->assertSame(DocumentStatus::Draft, $document->status);
        $secondSigner->refresh();
        $this->assertSame($originalSecondSignerToken, $secondSigner->access_token);
        $this->assertSame($originalSecondSignerExpiry, $secondSigner->expires_at?->toDateTimeString());
    }

    public function test_signer_can_start_trust_authorization_session(): void
    {
        config()->set('docutrust.pki.signing_backend', 'remote_managed');
        config()->set('services.remote_signing.base_url', 'https://remote-signing.test');
        config()->set('services.remote_signing.provider_name', 'trust_service_provider');

        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Pending]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
            'signing_method' => SigningMethod::PkiCertificate,
            'remote_credential_id' => 'credential-start-001',
        ]);

        Http::fake([
            'https://remote-signing.test/csc/v2/credentials/authorize' => Http::response([
                'handle' => 'handle-001',
            ], 202),
        ]);

        $this->postJson(route('sign.trust.authorize', $signer), [
            'num_signatures' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('session.status', 'pending')
            ->assertJsonPath('session.authorization_reference', 'handle-001');

        $this->assertDatabaseHas('trust_authorization_sessions', [
            'document_signer_id' => $signer->id,
            'provider_name' => 'trust_service_provider',
            'credential_id' => 'credential-start-001',
            'status' => 'pending',
            'authorization_reference' => 'handle-001',
        ]);
    }

    public function test_signer_can_poll_trust_authorization_session(): void
    {
        config()->set('docutrust.pki.signing_backend', 'remote_managed');
        config()->set('services.remote_signing.base_url', 'https://remote-signing.test');
        config()->set('services.remote_signing.provider_name', 'trust_service_provider');

        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Pending]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
            'signing_method' => SigningMethod::PkiCertificate,
        ]);
        $session = TrustAuthorizationSession::factory()->for($signer, 'signer')->create([
            'provider_name' => 'trust_service_provider',
            'status' => 'pending',
            'authorization_reference' => 'handle-002',
            'sad' => null,
            'completed_at' => null,
        ]);

        Http::fake([
            'https://remote-signing.test/csc/v2/credentials/authorizeCheck' => Http::response([
                'SAD' => 'sad-token-002',
                'expiresIn' => 600,
            ], 200),
        ]);

        $this->getJson(route('sign.trust.authorize.poll', [
            'token' => $signer->access_token,
            'session' => $session->id,
        ]))
            ->assertOk()
            ->assertJsonPath('session.status', 'authorized');

        $session->refresh();
        $this->assertSame('authorized', $session->status);
        $this->assertSame('sad-token-002', $session->sad);
        $this->assertNotNull($session->completed_at);
    }

    public function test_account_verified_signer_public_link_redirects_guest_to_login(): void
    {
        $owner = User::factory()->create();
        $linkedUser = User::factory()->signer()->organizationMember()->create([
            'organization_id' => $owner->organization_id,
        ]);
        $document = Document::factory()->for($owner)->create(['status' => DocumentStatus::Pending]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'email' => $linkedUser->email,
            'signing_method' => SigningMethod::AccountVerified,
            'user_id' => $linkedUser->id,
        ]);

        $this->get(route('sign.show', $signer->access_token))
            ->assertRedirect(route('login'));
    }

    public function test_account_verified_signer_public_link_rejects_wrong_authenticated_user(): void
    {
        $owner = User::factory()->create();
        $linkedUser = User::factory()->signer()->organizationMember()->create([
            'organization_id' => $owner->organization_id,
        ]);
        $wrongUser = User::factory()->signer()->create([
            'organization_id' => $owner->organization_id,
        ]);
        $document = Document::factory()->for($owner)->create(['status' => DocumentStatus::Pending]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'email' => $linkedUser->email,
            'signing_method' => SigningMethod::AccountVerified,
            'user_id' => $linkedUser->id,
        ]);

        $this->actingAs($wrongUser)
            ->get(route('sign.show', $signer->access_token))
            ->assertForbidden()
            ->assertSee('Sign in with the assigned DocuTrust account to access this document.');
    }

    public function test_account_verified_signer_can_open_authenticated_signing_route(): void
    {
        $owner = User::factory()->create();
        $linkedUser = User::factory()->signer()->organizationMember()->create([
            'organization_id' => $owner->organization_id,
        ]);
        $document = Document::factory()->for($owner)->create(['status' => DocumentStatus::Pending]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'name' => $linkedUser->name,
            'email' => $linkedUser->email,
            'signing_method' => SigningMethod::AccountVerified,
            'user_id' => $linkedUser->id,
        ]);

        $this->actingAs($linkedUser)
            ->withSession([AuthSession::TWO_FACTOR_PASSED => true])
            ->get(route('sign.account.show', ['signerId' => $signer->id]))
            ->assertOk()
            ->assertSee($document->title);
    }

    public function test_account_verified_signer_can_complete_signing_through_authenticated_route(): void
    {
        $owner = User::factory()->create();
        $linkedUser = User::factory()->signer()->organizationMember()->create([
            'organization_id' => $owner->organization_id,
        ]);
        $document = Document::factory()->for($owner)->create(['status' => DocumentStatus::Pending]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'name' => $linkedUser->name,
            'email' => $linkedUser->email,
            'signing_method' => SigningMethod::AccountVerified,
            'user_id' => $linkedUser->id,
            'status' => DocumentSignerStatus::Pending,
        ]);

        $this->actingAs($linkedUser)
            ->withSession([AuthSession::TWO_FACTOR_PASSED => true])
            ->post(route('sign.account.store', ['signerId' => $signer->id]))
            ->assertRedirect(route('sign.account.show', ['signerId' => $signer->id]));

        $signer->refresh();
        $document->refresh();
        $this->assertSame(DocumentSignerStatus::Signed, $signer->status);
        $this->assertSame(DocumentStatus::Completed, $document->status);
    }

    public function test_signer_documents_index_shows_assigned_documents(): void
    {
        $owner = User::factory()->create();
        $linkedUser = User::factory()->signer()->organizationMember()->create([
            'organization_id' => $owner->organization_id,
        ]);
        $assignedDocument = Document::factory()->for($owner)->create([
            'title' => 'Assigned Contract',
            'status' => DocumentStatus::Pending,
        ]);
        $otherDocument = Document::factory()->for($owner)->create([
            'title' => 'Unassigned Contract',
            'status' => DocumentStatus::Pending,
        ]);

        DocumentSigner::factory()->for($assignedDocument)->create([
            'email' => $linkedUser->email,
            'signing_method' => SigningMethod::AccountVerified,
            'user_id' => $linkedUser->id,
        ]);
        DocumentSigner::factory()->for($otherDocument)->create();

        $this->actingAs($linkedUser)
            ->withSession([AuthSession::TWO_FACTOR_PASSED => true])
            ->get(route('documents.index'))
            ->assertOk()
            ->assertSee('Assigned Contract')
            ->assertDontSee('Unassigned Contract')
            ->assertSee('Sign');
    }

    public function test_sign_requests_index_shows_modern_request_inbox(): void
    {
        $owner = User::factory()->create();
        $linkedUser = User::factory()->signer()->organizationMember()->create([
            'organization_id' => $owner->organization_id,
        ]);

        $pendingDocument = Document::factory()->for($owner)->create([
            'title' => 'Pending Service Agreement',
            'status' => DocumentStatus::Pending,
            'sent_at' => now()->subHour(),
        ]);
        $completedDocument = Document::factory()->for($owner)->create([
            'title' => 'Signed Lease Addendum',
            'status' => DocumentStatus::Completed,
            'sent_at' => now()->subDays(2),
        ]);

        DocumentSigner::factory()->for($pendingDocument)->create([
            'name' => $linkedUser->name,
            'email' => $linkedUser->email,
            'signing_method' => SigningMethod::AccountVerified,
            'user_id' => $linkedUser->id,
            'status' => DocumentSignerStatus::Pending,
            'expires_at' => now()->addDay(),
        ]);
        DocumentSigner::factory()->for($completedDocument)->create([
            'name' => $linkedUser->name,
            'email' => $linkedUser->email,
            'signing_method' => SigningMethod::AccountVerified,
            'user_id' => $linkedUser->id,
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now()->subDay(),
        ]);

        $this->actingAs($linkedUser)
            ->withSession([AuthSession::TWO_FACTOR_PASSED => true])
            ->get(route('sign-requests.index'))
            ->assertOk()
            ->assertSee('Signing inbox')
            ->assertSee('Active queue')
            ->assertSee('Pending Service Agreement')
            ->assertSee('Signed Lease Addendum')
            ->assertSee('Expires soon')
            ->assertSee('Account verified');
    }

    public function test_sign_requests_index_updates_when_realtime_notification_is_received(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create();
        /** @var User $linkedUser */
        $linkedUser = User::factory()->signer()->organizationMember()->create([
            'organization_id' => $owner->organization_id,
        ]);
        $document = Document::factory()->for($owner)->create([
            'title' => 'Realtime Signing Packet',
            'status' => DocumentStatus::Pending,
            'sent_at' => now(),
        ]);

        DocumentSigner::factory()->for($document)->create([
            'name' => $linkedUser->name,
            'email' => $linkedUser->email,
            'signing_method' => SigningMethod::AccountVerified,
            'user_id' => $linkedUser->id,
            'status' => DocumentSignerStatus::Pending,
        ]);

        $this->actingAs($linkedUser);

        LivewireVolt::test('sign-requests.index')
            ->dispatch('sign-request-received')
            ->assertSee('New sign request received. Your inbox has been updated.')
            ->assertSee('Realtime Signing Packet');
    }

    public function test_sign_request_received_broadcast_targets_signer_private_channel(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create(['name' => 'Document Owner']);
        /** @var User $linkedUser */
        $linkedUser = User::factory()->signer()->organizationMember()->create([
            'organization_id' => $owner->organization_id,
        ]);
        $document = Document::factory()->for($owner)->create([
            'title' => 'Board Resolution',
            'status' => DocumentStatus::Pending,
        ]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'signing_method' => SigningMethod::AccountVerified,
            'user_id' => $linkedUser->id,
            'status' => DocumentSignerStatus::Pending,
        ]);

        $event = new SignRequestReceived($signer);

        $this->assertSame('private-App.Models.User.'.$linkedUser->id, $event->broadcastOn()->name);
        $this->assertSame('sign.request.received', $event->broadcastAs());
        $this->assertSame('Board Resolution', $event->broadcastWith()['title']);
        $this->assertSame(route('sign-requests.index'), $event->broadcastWith()['url']);
    }

    public function test_document_cannot_be_sent_when_account_verified_signer_is_not_linked(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $path = 'documents/account-verified-source.pdf';
        $this->putValidPdf($path);
        $document = Document::factory()->for($owner)->create(['status' => DocumentStatus::Draft, 'file_path' => $path]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'signing_method' => SigningMethod::AccountVerified,
            'user_id' => null,
        ]);
        SignatureField::query()->create([
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'type' => SignatureFieldType::Signature,
            'position_data' => [
                'x' => 0.1,
                'y' => 0.1,
                'width' => 0.2,
                'height' => 0.05,
            ],
        ]);

        $this->actingAs($owner)
            ->post(route('documents.send', $document))
            ->assertRedirect(route('documents.prepare', $document))
            ->assertSessionHas('error', 'Signer '.$signer->name.' must be linked to a verified DocuTrust account before sending.');
    }
}
