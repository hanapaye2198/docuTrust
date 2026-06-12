<?php

namespace Tests\Feature;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Enums\EInvoiceStatus;
use App\Enums\NotaryRequestStatus;
use App\Enums\PaymentStatus;
use App\Enums\TemplateRoleType;
use App\Enums\UserRole;
use App\Jobs\RefreshEInvoiceStatusJob;
use App\Jobs\SendDocumentEmailJob;
use App\Jobs\SubmitEInvoiceJob;
use App\Mail\NotaryPaymentReadyMail;
use App\Models\AttorneyNotarialRegistry;
use App\Models\BillingProfile;
use App\Models\Document;
use App\Models\DocumentHash;
use App\Models\DocumentSigner;
use App\Models\EInvoice;
use App\Models\EInvoiceSubmission;
use App\Models\NotarialRegisterEntry;
use App\Models\NotaryCredential;
use App\Models\NotaryRequest;
use App\Models\NotarySigner;
use App\Models\Payment;
use App\Models\SignatureField;
use App\Models\User;
use App\Services\NotarialCertificateService;
use App\Services\NotaryPublicPaymentLinkService;
use App\Services\NotarySealService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Livewire\Volt\Volt as LivewireVolt;
use Tests\TestCase;

class NotaryRequestPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_attorney_can_create_notary_request_from_livewire_page(): void
    {
        $attorney = User::factory()->notary()->create();

        $this->actingAs($attorney);

        LivewireVolt::test('notary-requests.create')
            ->set('title', 'Board resolution acknowledgment')
            ->set('requestType', 'acknowledgment')
            ->set('remarks', 'Bring government ID and board secretary certificate.')
            ->set('wizardStep', 3)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('notary_requests', [
            'title' => 'Board resolution acknowledgment',
            'notary_user_id' => $attorney->id,
            'organization_id' => $attorney->organization_id,
        ]);
    }

    public function test_attorney_wizard_requires_title_before_advancing(): void
    {
        $attorney = User::factory()->notary()->create();

        $this->actingAs($attorney);

        LivewireVolt::test('notary-requests.create')
            ->set('title', '')
            ->call('nextStep')
            ->assertHasErrors(['title'])
            ->assertSet('wizardStep', 1);
    }

    public function test_attorney_can_advance_wizard_and_open_case_without_optional_steps(): void
    {
        $attorney = User::factory()->notary()->create();

        $this->actingAs($attorney);

        LivewireVolt::test('notary-requests.create')
            ->set('title', 'SPA — Greenfield Lot 5')
            ->set('requestType', 'acknowledgment')
            ->call('nextStep')
            ->assertSet('wizardStep', 2)
            ->call('skipDocumentStep')
            ->assertSet('wizardStep', 3)
            ->call('skipPartiesStep')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('notary_requests', [
            'title' => 'SPA — Greenfield Lot 5',
            'notary_user_id' => $attorney->id,
        ]);
    }

    public function test_attorney_wizard_can_attach_pdf_on_document_step(): void
    {
        Storage::fake(config('filesystems.docutrust_disk', 'local'));

        $attorney = User::factory()->notary()->create();

        $this->actingAs($attorney);

        $file = UploadedFile::fake()->create('deed-of-sale.pdf', 120, 'application/pdf');

        LivewireVolt::test('notary-requests.create')
            ->set('title', 'Deed of sale — Lot 5')
            ->set('requestType', 'acknowledgment')
            ->call('nextStep')
            ->assertSet('wizardStep', 2)
            ->set('caseDocument', $file)
            ->call('skipPartiesStep')
            ->assertHasNoErrors()
            ->assertRedirect();

        $request = NotaryRequest::query()->where('title', 'Deed of sale — Lot 5')->first();

        $this->assertNotNull($request);
        $this->assertNotNull($request->document_path);
        Storage::disk(config('filesystems.docutrust_disk', 'local'))->assertExists($request->document_path);

        $this->assertDatabaseHas('documents', [
            'notary_request_id' => $request->id,
            'title' => 'Deed of sale — Lot 5',
            'status' => DocumentStatus::Draft->value,
        ]);
    }

    public function test_attorney_wizard_rejects_partial_signer_row(): void
    {
        $attorney = User::factory()->notary()->create();

        $this->actingAs($attorney);

        LivewireVolt::test('notary-requests.create')
            ->set('title', 'Affidavit of loss')
            ->set('requestType', 'acknowledgment')
            ->set('wizardStep', 3)
            ->set('signers', [
                [
                    'full_name' => 'Juan Dela Cruz',
                    'email' => '',
                    'phone' => '',
                    'address' => '',
                    'role' => 'signer',
                ],
            ])
            ->call('save')
            ->assertHasErrors(['signers.0.full_name']);
    }

    public function test_notary_index_only_shows_assigned_requests(): void
    {
        $notary = User::factory()->create([
            'role' => UserRole::Notary,
        ]);

        $otherNotary = User::factory()->for($notary->organization)->create([
            'role' => UserRole::Notary,
        ]);

        $requester = User::factory()->for($notary->organization)->create();

        NotaryRequest::factory()->for($requester)->create([
            'notary_user_id' => $notary->id,
            'title' => 'Visible request',
        ]);

        NotaryRequest::factory()->for($requester)->create([
            'notary_user_id' => $otherNotary->id,
            'title' => 'Hidden request',
        ]);

        $this->actingAs($notary)
            ->get(route('notary.requests.index'))
            ->assertOk()
            ->assertSee('Visible request')
            ->assertDontSee('Hidden request');
    }

    public function test_notary_request_index_shows_queue_state_badges_and_filtering(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create();

        $blockedRequest = NotaryRequest::factory()->for($admin)->create([
            'title' => 'Blocked request',
        ]);
        Document::factory()->for($admin)->create([
            'notary_request_id' => $blockedRequest->id,
            'title' => 'Blocked doc',
            'status' => DocumentStatus::Draft,
        ]);

        $readyRequest = NotaryRequest::factory()->for($admin)->create([
            'title' => 'Ready request',
        ]);
        Storage::disk('local')->put('documents/ready.pdf', '%PDF-1.4 ready');
        $readyDocument = Document::factory()->for($admin)->create([
            'notary_request_id' => $readyRequest->id,
            'title' => 'Ready doc',
            'file_path' => 'documents/ready.pdf',
            'status' => DocumentStatus::Draft,
        ]);
        $readySigner = DocumentSigner::factory()->for($readyDocument)->create([
            'signing_order' => 1,
        ]);
        SignatureField::factory()->for($readyDocument)->create([
            'signer_id' => $readySigner->id,
        ]);

        $pendingRequest = NotaryRequest::factory()->for($admin)->create([
            'title' => 'Pending request',
        ]);
        Document::factory()->for($admin)->create([
            'notary_request_id' => $pendingRequest->id,
            'title' => 'Pending doc',
            'status' => DocumentStatus::Pending,
        ]);

        $this->actingAs($admin)
            ->get(route('notary-requests.index'))
            ->assertOk()
            ->assertSee('Blocked request')
            ->assertSee('Ready request')
            ->assertSee('Pending request')
            ->assertSee('Needs attention');

        $this->actingAs($admin)
            ->get(route('notary-requests.index', ['queue' => 'ready_to_send']))
            ->assertOk()
            ->assertSee('Ready request')
            ->assertDontSee('Blocked request')
            ->assertDontSee('Pending request');
    }

    public function test_notary_request_index_shows_trust_state_badges_and_filtering(): void
    {
        $admin = User::factory()->create();

        $missingCertificateRequest = NotaryRequest::factory()->for($admin)->create([
            'title' => 'Missing certificate request',
        ]);
        $documentWithMissingCertificate = Document::factory()->for($admin)->create([
            'notary_request_id' => $missingCertificateRequest->id,
            'title' => 'Completed missing cert',
            'status' => DocumentStatus::Completed,
            'certificate_path' => null,
            'final_pdf_path' => 'documents/final-cert.pdf',
        ]);
        DocumentHash::query()->create([
            'document_id' => $documentWithMissingCertificate->id,
            'hash' => hash('sha256', 'missing-cert'),
            'transaction_id' => '0xmissingcert',
            'created_at' => now(),
        ]);

        $missingBlockchainRequest = NotaryRequest::factory()->for($admin)->create([
            'title' => 'Missing blockchain request',
        ]);
        $documentWithMissingBlockchain = Document::factory()->for($admin)->create([
            'notary_request_id' => $missingBlockchainRequest->id,
            'title' => 'Completed missing blockchain',
            'status' => DocumentStatus::Completed,
            'certificate_path' => 'certificates/completed.pdf',
            'final_pdf_path' => 'documents/final-chain.pdf',
        ]);
        DocumentHash::query()->create([
            'document_id' => $documentWithMissingBlockchain->id,
            'hash' => hash('sha256', 'missing-chain'),
            'transaction_id' => null,
            'created_at' => now(),
        ]);

        $trustReadyRequest = NotaryRequest::factory()->for($admin)->create([
            'title' => 'Trust ready request',
        ]);
        $trustReadyDocument = Document::factory()->for($admin)->create([
            'notary_request_id' => $trustReadyRequest->id,
            'title' => 'Completed trust ready',
            'status' => DocumentStatus::Completed,
            'certificate_path' => 'certificates/trust-ready.pdf',
            'final_pdf_path' => 'documents/final-trust.pdf',
        ]);
        DocumentHash::query()->create([
            'document_id' => $trustReadyDocument->id,
            'hash' => hash('sha256', 'trust-ready'),
            'transaction_id' => '0xtrustready',
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('notary-requests.index'))
            ->assertOk()
            ->assertSee('Missing certificate request')
            ->assertSee('Missing blockchain request')
            ->assertSee('Trust ready request')
            ->assertSee('Trust ready');

        $this->actingAs($admin)
            ->get(route('notary-requests.index', ['trust' => 'missing_certificate']))
            ->assertOk()
            ->assertSee('Missing certificate request')
            ->assertDontSee('Missing blockchain request')
            ->assertDontSee('Trust ready request');
    }

    public function test_admin_can_attach_document_to_notary_request_from_show_page(): void
    {
        $admin = User::factory()->create();
        $request = NotaryRequest::factory()->for($admin)->create();
        $document = Document::factory()->for($admin)->create([
            'status' => DocumentStatus::Draft,
            'title' => 'Attachable lease',
        ]);

        $this->actingAs($admin);

        LivewireVolt::test('notary-requests.show', ['notaryRequest' => $request])
            ->set('attachDocumentId', (string) $document->id)
            ->call('attachDocument')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'notary_request_id' => $request->id,
        ]);

        $this->assertDatabaseHas('notary_journals', [
            'notary_request_id' => $request->id,
            'entry_type' => 'document_attached',
        ]);
    }

    public function test_attaching_document_to_enotary_request_syncs_existing_request_signers(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
        ]);

        NotarySigner::factory()->for($request, 'notaryRequest')->create([
            'full_name' => 'First Party',
            'email' => 'first@example.test',
        ]);
        NotarySigner::factory()->for($request, 'notaryRequest')->create([
            'full_name' => 'Second Party',
            'email' => 'second@example.test',
        ]);

        $document = Document::factory()->for($notary)->create([
            'status' => DocumentStatus::Draft,
            'title' => 'Attachable packet',
        ]);

        $this->actingAs($notary);

        Livewire::test('notary-requests.show', ['notaryRequest' => $request])
            ->set('attachDocumentId', (string) $document->id)
            ->call('attachDocument')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('document_signers', [
            'document_id' => $document->id,
            'email' => 'first@example.test',
        ]);
        $this->assertDatabaseHas('document_signers', [
            'document_id' => $document->id,
            'email' => 'second@example.test',
        ]);
    }

    public function test_admin_can_upload_document_directly_from_notary_request_page(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create();
        $request = NotaryRequest::factory()->for($admin)->create();
        $file = UploadedFile::fake()->create('notary-packet.pdf', 120, 'application/pdf');

        $this->actingAs($admin);

        LivewireVolt::test('notary-requests.show', ['notaryRequest' => $request])
            ->set('newDocumentTitle', 'Uploaded via request page')
            ->set('newDocumentFile', $file)
            ->call('createDocument')
            ->assertHasNoErrors();

        $document = Document::query()->where('title', 'Uploaded via request page')->first();

        $this->assertNotNull($document);
        $this->assertSame($request->id, $document->notary_request_id);
        $this->assertSame($admin->id, $document->user_id);
        Storage::disk('local')->assertExists($document->file_path);
    }

    public function test_admin_can_send_linked_document_for_signature_from_notary_request_page(): void
    {
        Storage::fake('local');
        Queue::fake();

        // Only the assigned attorney (notary) can send eNOTARY documents
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
        ]);

        Storage::disk('local')->put('documents/sendable.pdf', '%PDF-1.4 test pdf');

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'title' => 'Sendable packet',
            'file_path' => 'documents/sendable.pdf',
            'status' => DocumentStatus::Draft,
        ]);

        $signer = DocumentSigner::factory()->for($document)->create([
            'signing_order' => 1,
        ]);

        SignatureField::factory()->for($document)->create([
            'signer_id' => $signer->id,
        ]);

        $this->actingAs($notary);

        LivewireVolt::test('notary-requests.show', ['notaryRequest' => $request])
            ->call('sendLinkedDocument', $document->id)
            ->assertHasNoErrors();

        $document->refresh();
        $this->assertSame(DocumentStatus::Pending, $document->status);
        $this->assertNotNull($document->sent_at);

        $signer->refresh();
        Queue::assertPushed(SendDocumentEmailJob::class, function (SendDocumentEmailJob $job) use ($document, $signer): bool {
            return $job->documentId === $document->id
                && $job->signerId === $signer->id
                && $job->recipientEmail === $signer->email
                && $job->type === SendDocumentEmailJob::TYPE_SENT_TO_SIGNER
                && is_string($job->signUrl)
                && str_contains($job->signUrl, (string) $signer->access_token);
        });
    }

    public function test_adding_request_signer_backfills_existing_enotary_document_signers(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
        ]);
        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Draft,
        ]);

        DocumentSigner::factory()->for($document)->create([
            'name' => 'Existing Signer',
            'email' => 'existing@example.test',
            'signing_order' => 1,
        ]);

        $this->actingAs($notary);

        Livewire::test('notary-requests.show', ['notaryRequest' => $request])
            ->set('newSignerName', 'Late Added Signer')
            ->set('newSignerEmail', 'late@example.test')
            ->set('newSignerPhone', '')
            ->set('newSignerAddress', '')
            ->set('newSignerRole', 'signer')
            ->call('addSigner')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('document_signers', [
            'document_id' => $document->id,
            'email' => 'late@example.test',
            'name' => 'Late Added Signer',
        ]);
    }

    public function test_attorney_wizard_rejects_duplicate_signer_emails_case_insensitively(): void
    {
        $attorney = User::factory()->notary()->create();

        $this->actingAs($attorney);

        LivewireVolt::test('notary-requests.create')
            ->set('title', 'Affidavit with duplicate emails')
            ->set('requestType', 'acknowledgment')
            ->set('wizardStep', 3)
            ->set('signers', [
                [
                    'full_name' => 'Juan Dela Cruz',
                    'email' => 'party@example.test',
                    'phone' => '',
                    'address' => '',
                    'role' => 'signer',
                ],
                [
                    'full_name' => 'Maria Santos',
                    'email' => 'PARTY@example.test',
                    'phone' => '',
                    'address' => '',
                    'role' => 'witness',
                ],
            ])
            ->call('save')
            ->assertHasErrors(['signers.1.email']);
    }

    public function test_case_page_rejects_duplicate_signer_email_case_insensitively(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
        ]);

        NotarySigner::factory()->for($request, 'notaryRequest')->create([
            'full_name' => 'Existing Signer',
            'email' => 'existing@example.test',
        ]);

        $this->actingAs($notary);

        Livewire::test('notary-requests.show', ['notaryRequest' => $request])
            ->set('newSignerName', 'Duplicate Signer')
            ->set('newSignerEmail', 'Existing@example.test')
            ->set('newSignerPhone', '')
            ->set('newSignerAddress', '')
            ->set('newSignerRole', 'signer')
            ->call('addSigner')
            ->assertHasErrors(['newSignerEmail']);
    }

    public function test_removing_request_signer_cleans_up_matching_draft_document_signer_and_fields(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
        ]);
        $requestSigner = NotarySigner::factory()->for($request, 'notaryRequest')->create([
            'full_name' => 'Removable Signer',
            'email' => 'remove-me@example.test',
        ]);
        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Draft,
        ]);
        $documentSigner = DocumentSigner::factory()->for($document)->create([
            'name' => 'Removable Signer',
            'email' => 'remove-me@example.test',
            'signing_order' => 1,
        ]);
        SignatureField::factory()->for($document)->create([
            'signer_id' => $documentSigner->id,
        ]);

        $this->actingAs($notary);

        Livewire::test('notary-requests.show', ['notaryRequest' => $request])
            ->call('removeSigner', $requestSigner->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('notary_signers', [
            'id' => $requestSigner->id,
        ]);
        $this->assertDatabaseMissing('document_signers', [
            'id' => $documentSigner->id,
        ]);
        $this->assertDatabaseMissing('signature_fields', [
            'signer_id' => $documentSigner->id,
        ]);
    }

    public function test_linked_document_participant_summary_is_visible_on_notary_request_page(): void
    {
        $admin = User::factory()->create();
        $request = NotaryRequest::factory()->for($admin)->create();

        $document = Document::factory()->for($admin)->create([
            'notary_request_id' => $request->id,
            'title' => 'Participant summary packet',
            'status' => DocumentStatus::Draft,
        ]);

        DocumentSigner::factory()->for($document)->create([
            'role_type' => TemplateRoleType::Signer,
            'name' => 'Primary Signer',
            'status' => DocumentSignerStatus::Signed,
        ]);

        DocumentSigner::factory()->for($document)->create([
            'role_type' => TemplateRoleType::Approver,
            'name' => 'Legal Approver',
            'status' => DocumentSignerStatus::Approved,
        ]);

        DocumentSigner::factory()->for($document)->create([
            'role_type' => TemplateRoleType::Recipient,
            'name' => 'Records Recipient',
            'status' => DocumentSignerStatus::Pending,
        ]);

        $this->actingAs($admin)
            ->get(route('notary-requests.show', $request))
            ->assertOk()
            ->assertSee('Participant summary packet')
            ->assertSee('1 signer')
            ->assertSee('1 approver')
            ->assertSee('1 recipient')
            ->assertSee('1 pending')
            ->assertSee('1 signed')
            ->assertSee('1 approved');
    }

    public function test_linked_document_blocking_reason_is_visible_on_notary_request_page(): void
    {
        $admin = User::factory()->create();
        $request = NotaryRequest::factory()->for($admin)->create();

        Document::factory()->for($admin)->create([
            'notary_request_id' => $request->id,
            'title' => 'Blocked packet',
            'status' => DocumentStatus::Draft,
        ]);

        $this->actingAs($admin)
            ->get(route('notary-requests.show', $request))
            ->assertOk()
            ->assertSee('Blocked packet')
            ->assertSee('Add at least one signer or approver.');
    }

    public function test_next_recommended_action_is_not_shown_for_enotary_documents(): void
    {
        $admin = User::factory()->create();
        $request = NotaryRequest::factory()->for($admin)->create();

        Document::factory()->for($admin)->create([
            'notary_request_id' => $request->id,
            'title' => 'Needs participants first',
            'status' => DocumentStatus::Draft,
        ]);

        // eNOTARY documents no longer show "Next recommended action" — the attorney manages the flow
        $this->actingAs($admin)
            ->get(route('notary-requests.show', $request))
            ->assertOk()
            ->assertDontSee('Next recommended action');
    }

    public function test_notary_draft_request_hides_review_and_register_actions_until_workflow_advances(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
        ]);

        $this->actingAs($notary)
            ->get(route('notary.requests.show', $request))
            ->assertOk()
            ->assertSee('Case workflow')
            ->assertSee('Documents')
            ->assertSee('Parties')
            ->assertSee('Settlement')
            ->assertSee('Signers sign')
            ->assertSee('Video conference')
            ->assertSee('Attorney signed')
            ->assertSee('Set notarial fee')
            ->assertSee('Pay notarial fee')
            ->assertSee('Attorney personal seal')
            ->assertSee('Notarial register entry')
            ->assertSee('Official register entry')
            ->assertSee('Attorney review')
            ->assertSee('Available after the video session, attorney signing, register entry, and client payment (if required) are complete.')
            ->assertDontSee('Complete attorney review')
            ->assertSee('Official notarial register')
            ->assertSee('Available after you sign the document, complete the register entry (with O.R. if a fee applies), collect payment, and upload your personal seal.')
            ->assertDontSee('Create register entry');
    }

    public function test_notary_cannot_open_register_entry_page_before_attorney_approval(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
        ]);

        $this->actingAs($notary)
            ->get(route('notary.register-entry', $request))
            ->assertForbidden();
    }

    public function test_notary_can_save_settlement_fee_before_payment(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Completed,
        ]);

        DocumentSigner::factory()->for($document)->create([
            'name' => $notary->name,
            'email' => $notary->email,
            'user_id' => $notary->id,
            'role_type' => TemplateRoleType::Signer,
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);

        $this->actingAs($notary);

        LivewireVolt::test('notary-requests.show', ['notaryRequest' => $request])
            ->set('settlementFee', '500.00')
            ->call('saveSettlementFee')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('attorney_notarial_registries', [
            'notary_request_id' => $request->id,
            'fees' => 500.00,
        ]);

        $draft = AttorneyNotarialRegistry::query()->where('notary_request_id', $request->id)->first();
        $this->assertNotNull($draft);
        $this->assertNull($draft->registry_fields_completed_at);
        $this->assertNull($draft->official_receipt_no);
    }

    public function test_notary_can_save_settlement_fee_via_http_post(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Completed,
        ]);

        DocumentSigner::factory()->for($document)->create([
            'name' => $notary->name,
            'email' => $notary->email,
            'user_id' => $notary->id,
            'role_type' => TemplateRoleType::Signer,
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);

        $this->actingAs($notary)
            ->post(route('notary.requests.settlement-fee', $request), [
                'settlement_fee' => '1.00',
            ])
            ->assertRedirect(route('notary.requests.show', ['notaryRequest' => $request, 'tab' => 'closing']));

        $this->assertDatabaseHas('attorney_notarial_registries', [
            'notary_request_id' => $request->id,
            'fees' => 1.00,
        ]);
    }

    public function test_settlement_fee_ignores_query_param_and_uses_saved_amount(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
        ]);

        AttorneyNotarialRegistry::factory()->create([
            'notary_request_id' => $request->id,
            'fees' => 0,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Completed,
        ]);

        DocumentSigner::factory()->for($document)->create([
            'name' => $notary->name,
            'email' => $notary->email,
            'user_id' => $notary->id,
            'role_type' => TemplateRoleType::Signer,
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);

        $this->actingAs($notary);

        Livewire::withQueryParams(['settlementFee' => '1'])
            ->test('notary-requests.show', ['notaryRequest' => $request])
            ->assertSet('settlementFee', '0.00')
            ->assertSee(__('Current fee: PHP :amount', ['amount' => number_format(0, 2)]))
            ->assertDontSee(__('Unsaved amount: PHP :amount. Click Save fee to apply it.', ['amount' => number_format(1, 2)]));
    }

    public function test_client_case_page_shows_next_step_and_friendly_status(): void
    {
        $client = User::factory()->create();
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($client)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::SessionInProgress,
        ]);

        $this->actingAs($client);

        LivewireVolt::test('notary-requests.show', ['notaryRequest' => $request])
            ->assertSee(__('Your next step'))
            ->assertSee(__('Session in progress'))
            ->assertSee(__('Your progress'))
            ->assertSee(__('Video verification'))
            ->assertDontSee(__('Workflow progress'));
    }

    public function test_notarized_client_sees_completion_panel(): void
    {
        $client = User::factory()->create();
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($client)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::Notarized,
            'completed_at' => now(),
        ]);

        $document = Document::factory()->for($client)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Completed,
        ]);

        $this->actingAs($client);

        LivewireVolt::test('notary-requests.show', ['notaryRequest' => $request])
            ->assertSee(__('Notarization complete'))
            ->assertSee(__('Download PDF'));
    }

    public function test_attorney_settlement_tab_shows_pending_badge_when_fee_is_due(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Completed,
        ]);

        DocumentSigner::factory()->for($document)->create([
            'name' => $notary->name,
            'email' => $notary->email,
            'user_id' => $notary->id,
            'role_type' => TemplateRoleType::Signer,
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);

        $this->actingAs($notary);

        LivewireVolt::test('notary-requests.show', ['notaryRequest' => $request])
            ->assertSee(__('Settlement checklist'))
            ->assertSee(__('Set notarial fee'));
    }

    public function test_saving_settlement_fee_scrolls_to_payment_section(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Completed,
        ]);

        DocumentSigner::factory()->for($document)->create([
            'name' => $notary->name,
            'email' => $notary->email,
            'user_id' => $notary->id,
            'role_type' => TemplateRoleType::Signer,
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);

        $this->actingAs($notary);

        LivewireVolt::test('notary-requests.show', ['notaryRequest' => $request])
            ->set('activeTab', 'closing')
            ->set('settlementFee', '500.00')
            ->call('saveSettlementFee')
            ->assertHasNoErrors()
            ->assertDispatched('scroll-to-section', id: 'section-payment', reset: true);
    }

    public function test_opening_settlement_tab_focuses_current_settlement_section(): void
    {
        $notary = User::factory()->notaryWithoutCredential()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Completed,
        ]);

        DocumentSigner::factory()->for($document)->create([
            'name' => $notary->name,
            'email' => $notary->email,
            'user_id' => $notary->id,
            'role_type' => TemplateRoleType::Signer,
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);

        NotaryCredential::factory()->for($notary)->create([
            'seal_image_path' => 'seals/settlement-scroll-seal.png',
        ]);

        $this->actingAs($notary);

        LivewireVolt::test('notary-requests.show', ['notaryRequest' => $request])
            ->call('setActiveTab', 'closing')
            ->assertDispatched('scroll-to-section', id: 'section-settlement-start', reset: true);
    }

    public function test_attorney_closing_tab_shows_settlement_sub_nav(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Completed,
        ]);

        DocumentSigner::factory()->for($document)->create([
            'name' => $notary->name,
            'email' => $notary->email,
            'user_id' => $notary->id,
            'role_type' => TemplateRoleType::Signer,
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);

        $this->actingAs($notary);

        LivewireVolt::test('notary-requests.show', ['notaryRequest' => $request])
            ->set('activeTab', 'closing')
            ->assertSee(__('Settlement sections'))
            ->assertSee(__('Fee'))
            ->assertSee(__('Pay'))
            ->assertSee(__('Register'));
    }

    public function test_notary_cannot_open_registry_before_payment_when_fee_applies(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Completed,
        ]);

        DocumentSigner::factory()->for($document)->create([
            'name' => $notary->name,
            'email' => $notary->email,
            'user_id' => $notary->id,
            'role_type' => TemplateRoleType::Signer,
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);

        AttorneyNotarialRegistry::factory()->create([
            'notary_request_id' => $request->id,
            'fees' => 500,
        ]);

        $this->actingAs($notary);

        LivewireVolt::test('notary.attorney-registry', ['notaryRequest' => $request])
            ->assertRedirect(route('notary.requests.show', ['notaryRequest' => $request, 'tab' => 'closing']));
    }

    public function test_notary_can_save_attorney_registry_draft_after_attorney_signing_and_payment(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Completed,
        ]);

        DocumentSigner::factory()->for($document)->create([
            'name' => $notary->name,
            'email' => $notary->email,
            'user_id' => $notary->id,
            'role_type' => TemplateRoleType::Signer,
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);

        AttorneyNotarialRegistry::factory()->create([
            'notary_request_id' => $request->id,
            'fees' => 500,
        ]);

        Payment::query()->create([
            'organization_id' => $notary->organization_id,
            'notary_request_id' => $request->id,
            'payer_user_id' => $request->user_id,
            'created_by_user_id' => $notary->id,
            'provider' => 'gatewayhub',
            'provider_payment_id' => 'payment-paid-1',
            'gateway' => 'gcash',
            'reference' => 'REF-PAID-1',
            'amount' => 500,
            'currency' => 'PHP',
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        $this->actingAs($notary);

        LivewireVolt::test('notary.attorney-registry', ['notaryRequest' => $request])
            ->set('title', 'Affidavit of Loss Registry')
            ->set('parties', [
                ['name' => 'Juan Dela Cruz', 'address' => 'Davao City'],
            ])
            ->set('competentEvidence', [
                ['person_name' => 'Juan Dela Cruz', 'id_type' => 'Passport', 'id_number' => 'P12345'],
            ])
            ->set('notarialActType', 'acknowledgment')
            ->set('officialReceiptNo', 'OR-123')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('notary.requests.show', ['notaryRequest' => $request, 'tab' => 'closing']));

        $draft = AttorneyNotarialRegistry::query()->where('notary_request_id', $request->id)->first();
        $this->assertNotNull($draft);
        $this->assertSame('OR-123', $draft->official_receipt_no);
        $this->assertNotNull($draft->registry_fields_completed_at);
    }

    public function test_notary_registry_save_rechecks_payment_before_proceeding(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Completed,
        ]);

        DocumentSigner::factory()->for($document)->create([
            'name' => $notary->name,
            'email' => $notary->email,
            'user_id' => $notary->id,
            'role_type' => TemplateRoleType::Signer,
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);

        $this->actingAs($notary);

        $component = LivewireVolt::test('notary.attorney-registry', ['notaryRequest' => $request])
            ->set('title', 'Affidavit of Support Registry')
            ->set('parties', [
                ['name' => 'Juan Dela Cruz', 'address' => 'Davao City'],
            ])
            ->set('competentEvidence', [
                ['person_name' => 'Juan Dela Cruz', 'id_type' => 'Passport', 'id_number' => 'P12345'],
            ])
            ->set('notarialActType', 'acknowledgment');

        AttorneyNotarialRegistry::factory()->create([
            'notary_request_id' => $request->id,
            'fees' => 500,
        ]);

        $component
            ->call('save')
            ->assertRedirect(route('notary.requests.show', ['notaryRequest' => $request, 'tab' => 'closing']));

        $this->assertDatabaseMissing('attorney_notarial_registries', [
            'notary_request_id' => $request->id,
            'title' => 'Affidavit of Support Registry',
            'registry_fields_completed_at' => now(),
        ]);
    }

    public function test_notary_can_access_attorney_registries_index_and_sees_sidebar_link(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'title' => 'SPA for Property Transfer',
        ]);

        AttorneyNotarialRegistry::factory()->create([
            'notary_request_id' => $request->id,
            'title' => 'Registry Title',
            'entry_no' => 'ENT-42',
        ]);

        $this->actingAs($notary)
            ->get(route('notary.attorney-registries.index'))
            ->assertOk()
            ->assertSee('SPA for Property Transfer')
            ->assertSee('ENT-42');

        $this->actingAs($notary)
            ->get(route('notary.dashboard'))
            ->assertOk()
            ->assertSee(__('Notary registry'));
    }

    public function test_notary_can_create_official_register_entry_from_saved_draft(): void
    {
        $this->mock(NotarySealService::class, function ($mock): void {
            $mock->shouldReceive('generateVerificationQrCode')->andReturnNull();
        });
        $this->mock(NotarialCertificateService::class, function ($mock): void {
            $mock->shouldReceive('generate')->andReturnNull();
        });

        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'organization_id' => $notary->organization_id,
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
            'title' => 'Affidavit of loss',
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Completed,
        ]);

        DocumentSigner::factory()->for($document)->create([
            'name' => $notary->name,
            'email' => $notary->email,
            'user_id' => $notary->id,
            'role_type' => TemplateRoleType::Signer,
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);

        NotaryCredential::query()
            ->where('user_id', $notary->id)
            ->update(['seal_image_path' => 'seals/test-seal.png']);

        AttorneyNotarialRegistry::factory()->create([
            'notary_request_id' => $request->id,
            'title' => 'Affidavit of loss',
            'notarial_act_type' => 'acknowledgment',
            'fees' => 0,
            'registry_fields_completed_at' => now(),
            'parties' => [
                ['name' => 'Client Name', 'address' => 'Davao City'],
            ],
            'competent_evidence' => [
                ['person_name' => 'Client Name', 'id_type' => 'Passport', 'id_number' => 'P12345'],
            ],
        ]);

        $this->actingAs($notary);

        LivewireVolt::test('notary.register-entry', ['notaryRequest' => $request])
            ->assertSee('Confirm official register entry')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('notary.requests.show', ['notaryRequest' => $request, 'tab' => 'closing']));

        $entry = NotarialRegisterEntry::query()->where('notary_request_id', $request->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame('Affidavit of loss', $entry->document_title);
        $this->assertSame('acknowledgment', $entry->notarial_act_type);
    }

    public function test_client_sees_payment_required_banner_after_register_entry_is_created(): void
    {
        $client = User::factory()->enotarySigner()->create();
        $notary = User::factory()->for($client->organization)->notary()->create();

        $request = NotaryRequest::factory()->for($client)->create([
            'organization_id' => $client->organization_id,
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
        ]);

        $credential = NotaryCredential::factory()->for($notary)->create();
        NotarialRegisterEntry::factory()->create([
            'notary_request_id' => $request->id,
            'notary_credential_id' => $credential->id,
            'fees' => 500.00,
        ]);

        $this->actingAs($client)
            ->get(route('notary-requests.show', $request))
            ->assertOk()
            ->assertSee('Payment required before notarization can continue')
            ->assertSee('Complete payment below to continue.');
    }

    public function test_client_sees_expired_payment_message_and_regenerate_call_to_action(): void
    {
        config(['services.gatewayhub.api_key' => 'test-gatewayhub-key']);

        Http::fake([
            'https://gatewayhub.io/api/gateways/enabled' => Http::response([
                'success' => true,
                'data' => [
                    'gateways' => [
                        ['code' => 'gcash', 'name' => 'Gcash'],
                    ],
                    'count' => 1,
                ],
                'error' => null,
            ], 200),
        ]);

        $client = User::factory()->enotarySigner()->create();
        $notary = User::factory()->for($client->organization)->notary()->create();

        $request = NotaryRequest::factory()->for($client)->create([
            'organization_id' => $client->organization_id,
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
        ]);

        $credential = NotaryCredential::factory()->for($notary)->create();
        $entry = NotarialRegisterEntry::factory()->create([
            'notary_request_id' => $request->id,
            'notary_credential_id' => $credential->id,
            'fees' => 500.00,
        ]);

        Payment::query()->create([
            'organization_id' => $client->organization_id,
            'notary_request_id' => $request->id,
            'notarial_register_entry_id' => $entry->id,
            'payer_user_id' => $client->id,
            'provider' => 'gatewayhub',
            'provider_payment_id' => 'expired-payment-1',
            'provider_transaction_id' => 'expired-payment-1',
            'gateway' => 'gcash',
            'reference' => 'EXPIRED-REQ-'.$request->id,
            'amount' => 500.00,
            'currency' => 'PHP',
            'status' => PaymentStatus::Pending->value,
            'qr_data' => '000201...',
            'expires_at' => now()->subMinutes(5),
        ]);

        $this->actingAs($client)
            ->get(route('notary-requests.show', $request))
            ->assertOk()
            ->assertSee('This payment link has expired. Generate a new payment to continue.')
            ->assertSee('Generate new payment');
    }

    public function test_client_sees_einvoice_status_after_payment_is_recorded(): void
    {
        $client = User::factory()->enotarySigner()->create();
        $notary = User::factory()->for($client->organization)->notary()->create();

        $request = NotaryRequest::factory()->for($client)->create([
            'organization_id' => $client->organization_id,
            'notary_user_id' => $notary->id,
        ]);

        $payment = Payment::query()->create([
            'organization_id' => $client->organization_id,
            'notary_request_id' => $request->id,
            'payer_user_id' => $client->id,
            'provider' => 'gatewayhub',
            'provider_payment_id' => 'paid-payment-1',
            'provider_transaction_id' => 'paid-payment-1',
            'gateway' => 'gcash',
            'reference' => 'PAID-REQ-'.$request->id,
            'amount' => 500.00,
            'currency' => 'PHP',
            'status' => PaymentStatus::Paid->value,
            'paid_at' => now(),
        ]);

        EInvoice::query()->create([
            'organization_id' => $client->organization_id,
            'notary_request_id' => $request->id,
            'payment_id' => $payment->id,
            'status' => EInvoiceStatus::Draft->value,
            'invoice_number' => 'INV-20260520-PAIDREQ',
            'currency' => 'PHP',
            'total_amount' => 500.00,
            'issue_date' => now(),
            'document_title' => 'Affidavit of Support',
        ]);

        $this->actingAs($client)
            ->get(route('notary-requests.show', $request))
            ->assertOk()
            ->assertSee('E-invoice')
            ->assertSee('INV-20260520-PAIDREQ')
            ->assertSee('The internal invoice record is ready and awaiting EIS submission setup.');
    }

    public function test_admin_can_queue_draft_einvoice_when_billing_profile_is_ready(): void
    {
        Queue::fake();

        $admin = User::factory()->create();
        $request = NotaryRequest::factory()->for($admin)->create([
            'organization_id' => $admin->organization_id,
        ]);

        $payment = Payment::query()->create([
            'organization_id' => $admin->organization_id,
            'notary_request_id' => $request->id,
            'payer_user_id' => $admin->id,
            'provider' => 'gatewayhub',
            'provider_payment_id' => 'queued-payment-1',
            'provider_transaction_id' => 'queued-payment-1',
            'gateway' => 'gcash',
            'reference' => 'QUEUE-REQ-'.$request->id,
            'amount' => 500.00,
            'currency' => 'PHP',
            'status' => PaymentStatus::Paid->value,
            'paid_at' => now(),
        ]);

        $profile = BillingProfile::query()->create([
            'organization_id' => $admin->organization_id,
            'registered_name' => 'DocuTrust Test Seller',
            'tin' => '123-456-789-000',
            'branch_code' => '000',
            'email' => 'billing@example.test',
            'address_line' => '123 Test Street',
            'city' => 'Davao City',
            'state' => 'Davao del Sur',
            'postal_code' => '8000',
            'country_code' => 'PH',
            'eis_environment' => 'sandbox',
            'eis_accreditation_id' => 'ACCRED-1',
            'eis_application_id' => 'APP-1',
            'eis_username' => 'eis-user',
            'eis_password' => 'eis-pass',
            'eis_certificate_id' => 'CERT-1',
            'is_active' => true,
        ]);

        $invoice = EInvoice::query()->create([
            'organization_id' => $admin->organization_id,
            'billing_profile_id' => $profile->id,
            'notary_request_id' => $request->id,
            'payment_id' => $payment->id,
            'status' => EInvoiceStatus::Draft->value,
            'invoice_number' => 'INV-20260520-QUEUE',
            'currency' => 'PHP',
            'total_amount' => 500.00,
            'issue_date' => now(),
            'seller_name' => 'DocuTrust Test Seller',
            'seller_tin' => '123-456-789-000',
            'seller_branch_code' => '000',
            'seller_address' => '123 Test Street, Davao City, Davao del Sur, 8000, PH',
            'seller_email' => 'billing@example.test',
            'buyer_name' => 'Demo Admin',
            'buyer_email' => $admin->email,
            'document_title' => 'Queue test invoice',
        ]);

        $this->actingAs($admin);

        LivewireVolt::test('notary-requests.show', ['notaryRequest' => $request])
            ->call('queueLatestEInvoice')
            ->assertHasNoErrors();

        $invoice->refresh();

        $this->assertSame(EInvoiceStatus::Queued, $invoice->status);
        $this->assertNotNull($invoice->queued_at);
        $this->assertDatabaseHas('einvoice_submissions', [
            'einvoice_id' => $invoice->id,
            'status' => EInvoiceStatus::Queued->value,
        ]);

        $submission = EInvoiceSubmission::query()->where('einvoice_id', $invoice->id)->latest('id')->first();
        $this->assertNotNull($submission);
        $this->assertIsArray($submission->request_payload);
        $this->assertSame('INV-20260520-QUEUE', $submission->request_payload['invoice_number'] ?? null);

        Queue::assertPushed(SubmitEInvoiceJob::class, function (SubmitEInvoiceJob $job) use ($invoice): bool {
            return $job->einvoiceId === $invoice->id;
        });
    }

    public function test_admin_can_submit_queued_einvoice_to_eis(): void
    {
        Queue::fake();

        Http::fake([
            'https://eis.test/api/authentication' => Http::response([
                'data' => [
                    'authToken' => 'auth-token-1',
                    'secretKey' => 'c2VjcmV0LXNlY3JldC1zZWNyZXQtc2VjcmV0LXNlY3JldA==',
                ],
            ], 200),
            'https://eis.test/api/invoices' => Http::response([
                'data' => [
                    'submitId' => 'submit-123',
                ],
            ], 200),
        ]);

        $publicKeyPath = base_path('jaas_public.pem');
        $privateKeyPath = $this->ensureEisSigningPrivateKeyPath();

        config()->set('services.eis.base_url', 'https://eis.test');
        config()->set('services.eis.public_key_path', $publicKeyPath);
        config()->set('services.eis.signing_private_key_path', $privateKeyPath);
        config()->set('services.eis.submit_endpoint', '/api/invoices');

        $admin = User::factory()->create();
        $request = NotaryRequest::factory()->for($admin)->create([
            'organization_id' => $admin->organization_id,
        ]);

        $payment = Payment::query()->create([
            'organization_id' => $admin->organization_id,
            'notary_request_id' => $request->id,
            'payer_user_id' => $admin->id,
            'provider' => 'gatewayhub',
            'provider_payment_id' => 'submit-payment-1',
            'provider_transaction_id' => 'submit-payment-1',
            'gateway' => 'gcash',
            'reference' => 'SUBMIT-REQ-'.$request->id,
            'amount' => 750.00,
            'currency' => 'PHP',
            'status' => PaymentStatus::Paid->value,
            'paid_at' => now(),
        ]);

        $profile = BillingProfile::query()->create([
            'organization_id' => $admin->organization_id,
            'registered_name' => 'DocuTrust Test Seller',
            'tin' => '123-456-789-000',
            'branch_code' => '000',
            'email' => 'billing@example.test',
            'address_line' => '123 Test Street',
            'city' => 'Davao City',
            'state' => 'Davao del Sur',
            'postal_code' => '8000',
            'country_code' => 'PH',
            'eis_environment' => 'sandbox',
            'eis_accreditation_id' => 'ACCRED-1',
            'eis_application_id' => 'APP-1',
            'eis_username' => 'eis-user',
            'eis_password' => 'eis-pass',
            'eis_certificate_id' => 'CERT-1',
            'is_active' => true,
        ]);

        $invoice = EInvoice::query()->create([
            'organization_id' => $admin->organization_id,
            'billing_profile_id' => $profile->id,
            'notary_request_id' => $request->id,
            'payment_id' => $payment->id,
            'status' => EInvoiceStatus::Queued->value,
            'queued_at' => now(),
            'invoice_number' => 'INV-20260520-SUBMIT',
            'currency' => 'PHP',
            'total_amount' => 750.00,
            'issue_date' => now(),
            'seller_name' => 'DocuTrust Test Seller',
            'seller_tin' => '123-456-789-000',
            'seller_branch_code' => '000',
            'seller_address' => '123 Test Street, Davao City, Davao del Sur, 8000, PH',
            'seller_email' => 'billing@example.test',
            'buyer_name' => 'Demo Admin',
            'buyer_email' => $admin->email,
            'document_title' => 'Submit test invoice',
        ]);

        $this->actingAs($admin);

        LivewireVolt::test('notary-requests.show', ['notaryRequest' => $request])
            ->call('submitLatestEInvoice')
            ->assertHasNoErrors();

        $invoice->refresh();

        $this->assertSame(EInvoiceStatus::Processing, $invoice->status);
        $this->assertSame('submit-123', $invoice->submit_id);
        $this->assertNotNull($invoice->submitted_at);

        $submission = EInvoiceSubmission::query()
            ->where('einvoice_id', $invoice->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($submission);
        $this->assertSame(EInvoiceStatus::Submitted->value, $submission->status);
        $this->assertSame('submit-123', $submission->submit_id);
        $this->assertIsArray($submission->request_payload);

        Queue::assertPushed(RefreshEInvoiceStatusJob::class, function (RefreshEInvoiceStatusJob $job) use ($invoice): bool {
            return $job->einvoiceId === $invoice->id;
        });
    }

    public function test_admin_can_refresh_processing_einvoice_status_from_eis(): void
    {
        Http::fake([
            'https://eis.test/api/authentication' => Http::response([
                'data' => [
                    'authToken' => 'auth-token-1',
                    'secretKey' => 'c2VjcmV0LXNlY3JldC1zZWNyZXQtc2VjcmV0LXNlY3JldA==',
                ],
            ], 200),
            'https://eis.test/api/inquiry*' => Http::response([
                'data' => [
                    'status' => 'accepted',
                    'eisUniqueId' => '20260520CERT0001CTRL0001',
                ],
            ], 200),
        ]);

        $publicKeyPath = base_path('jaas_public.pem');

        config()->set('services.eis.base_url', 'https://eis.test');
        config()->set('services.eis.public_key_path', $publicKeyPath);
        config()->set('services.eis.inquiry_endpoint', '/api/inquiry');

        $admin = User::factory()->create();
        $request = NotaryRequest::factory()->for($admin)->create([
            'organization_id' => $admin->organization_id,
        ]);

        $payment = Payment::query()->create([
            'organization_id' => $admin->organization_id,
            'notary_request_id' => $request->id,
            'payer_user_id' => $admin->id,
            'provider' => 'gatewayhub',
            'provider_payment_id' => 'inquiry-payment-1',
            'provider_transaction_id' => 'inquiry-payment-1',
            'gateway' => 'gcash',
            'reference' => 'INQUIRY-REQ-'.$request->id,
            'amount' => 900.00,
            'currency' => 'PHP',
            'status' => PaymentStatus::Paid->value,
            'paid_at' => now(),
        ]);

        $profile = BillingProfile::query()->create([
            'organization_id' => $admin->organization_id,
            'registered_name' => 'DocuTrust Test Seller',
            'tin' => '123-456-789-000',
            'branch_code' => '000',
            'email' => 'billing@example.test',
            'address_line' => '123 Test Street',
            'city' => 'Davao City',
            'state' => 'Davao del Sur',
            'postal_code' => '8000',
            'country_code' => 'PH',
            'eis_environment' => 'sandbox',
            'eis_accreditation_id' => 'ACCRED-1',
            'eis_application_id' => 'APP-1',
            'eis_username' => 'eis-user',
            'eis_password' => 'eis-pass',
            'eis_certificate_id' => 'CERT-1',
            'is_active' => true,
        ]);

        $invoice = EInvoice::query()->create([
            'organization_id' => $admin->organization_id,
            'billing_profile_id' => $profile->id,
            'notary_request_id' => $request->id,
            'payment_id' => $payment->id,
            'status' => EInvoiceStatus::Processing->value,
            'submit_id' => 'submit-123',
            'submitted_at' => now(),
            'invoice_number' => 'INV-20260520-INQUIRY',
            'currency' => 'PHP',
            'total_amount' => 900.00,
            'issue_date' => now(),
            'seller_name' => 'DocuTrust Test Seller',
            'seller_tin' => '123-456-789-000',
            'seller_branch_code' => '000',
            'seller_address' => '123 Test Street, Davao City, Davao del Sur, 8000, PH',
            'seller_email' => 'billing@example.test',
            'buyer_name' => 'Demo Admin',
            'buyer_email' => $admin->email,
            'document_title' => 'Inquiry test invoice',
        ]);

        $this->actingAs($admin);

        LivewireVolt::test('notary-requests.show', ['notaryRequest' => $request])
            ->call('refreshLatestEInvoiceStatus')
            ->assertHasNoErrors();

        $invoice->refresh();

        $this->assertSame(EInvoiceStatus::Accepted, $invoice->status);
        $this->assertSame('20260520CERT0001CTRL0001', $invoice->eis_unique_id);
        $this->assertNotNull($invoice->accepted_at);

        $submission = EInvoiceSubmission::query()
            ->where('einvoice_id', $invoice->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($submission);
        $this->assertSame(EInvoiceStatus::Accepted->value, $submission->status);
        $this->assertNotNull($submission->resolved_at);
    }

    public function test_admin_can_refresh_processing_einvoice_to_rejected_from_eis(): void
    {
        Http::fake([
            'https://eis.test/api/authentication' => Http::response([
                'data' => [
                    'authToken' => 'auth-token-1',
                    'secretKey' => 'c2VjcmV0LXNlY3JldC1zZWNyZXQtc2VjcmV0LXNlY3JldA==',
                ],
            ], 200),
            'https://eis.test/api/inquiry*' => Http::response([
                'data' => [
                    'status' => 'rejected',
                    'errorMessage' => 'Buyer TIN is invalid.',
                ],
            ], 200),
        ]);

        $publicKeyPath = base_path('jaas_public.pem');

        config()->set('services.eis.base_url', 'https://eis.test');
        config()->set('services.eis.public_key_path', $publicKeyPath);
        config()->set('services.eis.inquiry_endpoint', '/api/inquiry');

        $admin = User::factory()->create();
        $request = NotaryRequest::factory()->for($admin)->create([
            'organization_id' => $admin->organization_id,
        ]);

        $payment = Payment::query()->create([
            'organization_id' => $admin->organization_id,
            'notary_request_id' => $request->id,
            'payer_user_id' => $admin->id,
            'provider' => 'gatewayhub',
            'provider_payment_id' => 'inquiry-reject-payment-1',
            'provider_transaction_id' => 'inquiry-reject-payment-1',
            'gateway' => 'gcash',
            'reference' => 'INQUIRY-REJECT-REQ-'.$request->id,
            'amount' => 910.00,
            'currency' => 'PHP',
            'status' => PaymentStatus::Paid->value,
            'paid_at' => now(),
        ]);

        $profile = BillingProfile::query()->create([
            'organization_id' => $admin->organization_id,
            'registered_name' => 'DocuTrust Test Seller',
            'tin' => '123-456-789-000',
            'branch_code' => '000',
            'email' => 'billing@example.test',
            'address_line' => '123 Test Street',
            'city' => 'Davao City',
            'state' => 'Davao del Sur',
            'postal_code' => '8000',
            'country_code' => 'PH',
            'eis_environment' => 'sandbox',
            'eis_accreditation_id' => 'ACCRED-1',
            'eis_application_id' => 'APP-1',
            'eis_username' => 'eis-user',
            'eis_password' => 'eis-pass',
            'eis_certificate_id' => 'CERT-1',
            'is_active' => true,
        ]);

        $invoice = EInvoice::query()->create([
            'organization_id' => $admin->organization_id,
            'billing_profile_id' => $profile->id,
            'notary_request_id' => $request->id,
            'payment_id' => $payment->id,
            'status' => EInvoiceStatus::Processing->value,
            'submit_id' => 'submit-rejected-123',
            'submitted_at' => now(),
            'invoice_number' => 'INV-20260520-INQREJ',
            'currency' => 'PHP',
            'total_amount' => 910.00,
            'issue_date' => now(),
            'seller_name' => 'DocuTrust Test Seller',
            'seller_tin' => '123-456-789-000',
            'seller_branch_code' => '000',
            'seller_address' => '123 Test Street, Davao City, Davao del Sur, 8000, PH',
            'seller_email' => 'billing@example.test',
            'buyer_name' => 'Demo Admin',
            'buyer_email' => $admin->email,
            'document_title' => 'Inquiry rejected invoice',
        ]);

        $this->actingAs($admin);

        LivewireVolt::test('notary-requests.show', ['notaryRequest' => $request])
            ->call('refreshLatestEInvoiceStatus')
            ->assertHasNoErrors();

        $invoice->refresh();

        $this->assertSame(EInvoiceStatus::Rejected, $invoice->status);
        $this->assertSame('Buyer TIN is invalid.', $invoice->error_message);
        $this->assertNotNull($invoice->rejected_at);

        $submission = EInvoiceSubmission::query()
            ->where('einvoice_id', $invoice->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($submission);
        $this->assertSame(EInvoiceStatus::Rejected->value, $submission->status);
        $this->assertNotNull($submission->resolved_at);
    }

    public function test_admin_can_generate_missing_certificate_from_notary_request_page(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create();
        $request = NotaryRequest::factory()->for($admin)->create();

        Storage::disk('local')->put('documents/completed-certificate.pdf', '%PDF-1.4 completed certificate test');

        $document = Document::factory()->for($admin)->create([
            'notary_request_id' => $request->id,
            'title' => 'Completed missing certificate',
            'status' => DocumentStatus::Completed,
            'final_pdf_path' => 'documents/completed-certificate.pdf',
            'certificate_path' => null,
        ]);

        $this->actingAs($admin);

        LivewireVolt::test('notary-requests.show', ['notaryRequest' => $request])
            ->call('generateDocumentCertificate', $document->id)
            ->assertHasNoErrors();

        $document->refresh();
        $this->assertNotNull($document->certificate_path);
        Storage::disk('local')->assertExists($document->certificate_path);

        $this->assertDatabaseHas('document_hashes', [
            'document_id' => $document->id,
        ]);
    }

    public function test_admin_can_refresh_missing_blockchain_proof_from_notary_request_page(): void
    {
        Storage::fake('local');
        Http::fake([
            'https://blockchain.test/anchor' => Http::response([
                'transactionHash' => '0xrefreshedproof',
            ], 200),
        ]);

        config()->set('services.blockchain.base_url', 'https://blockchain.test');

        $admin = User::factory()->create();
        $request = NotaryRequest::factory()->for($admin)->create();

        Storage::disk('local')->put('documents/completed-blockchain.pdf', '%PDF-1.4 completed blockchain test');

        $document = Document::factory()->for($admin)->create([
            'notary_request_id' => $request->id,
            'title' => 'Completed missing blockchain proof',
            'status' => DocumentStatus::Completed,
            'final_pdf_path' => 'documents/completed-blockchain.pdf',
            'certificate_path' => 'certificates/already-present.pdf',
        ]);

        DocumentHash::query()->create([
            'document_id' => $document->id,
            'hash' => hash('sha256', '%PDF-1.4 completed blockchain test'),
            'transaction_id' => null,
            'created_at' => now(),
        ]);

        $this->actingAs($admin);

        LivewireVolt::test('notary-requests.show', ['notaryRequest' => $request])
            ->call('refreshBlockchainProof', $document->id)
            ->assertHasNoErrors();

        $document->refresh();
        $this->assertSame('0xrefreshedproof', $document->documentHash?->transaction_id);
    }

    public function test_notarized_request_verify_links_use_notary_verification_route(): void
    {
        $client = User::factory()->enotarySigner()->create();
        $request = NotaryRequest::factory()->for($client)->create([
            'status' => NotaryRequestStatus::Notarized,
            'completed_at' => now(),
        ]);

        $document = Document::factory()->for($client)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Completed,
        ]);

        $entry = NotarialRegisterEntry::factory()->create([
            'notary_request_id' => $request->id,
            'document_id' => $document->id,
            'qr_verification_token' => 'verify-token-123',
        ]);

        $this->actingAs($client)
            ->get(route('notary-requests.show', $request))
            ->assertOk()
            ->assertSee(route('notary.verify', ['token' => $entry->qr_verification_token]), false)
            ->assertDontSee(route('verify.index').'?token='.$entry->qr_verification_token, false);
    }

    public function test_notarial_certificate_view_uses_secure_disk_qr_path(): void
    {
        Storage::fake('local');

        $notary = User::factory()->notary()->create();
        $credential = NotaryCredential::factory()->create([
            'user_id' => $notary->id,
        ]);
        $request = NotaryRequest::factory()->for($notary)->create();
        $entry = NotarialRegisterEntry::factory()->create([
            'notary_request_id' => $request->id,
            'notary_credential_id' => $credential->id,
            'qr_verification_token' => 'verify-token-456',
            'qr_code_path' => 'notary/qr/verify-token-456.png',
        ]);

        Storage::disk('local')->put($entry->qr_code_path, 'fake-qr-image');

        $rendered = view('certificates.notarial', [
            'entry' => $entry,
            'credential' => $credential->fresh('user'),
            'qrCodeImagePath' => Storage::disk('local')->path($entry->qr_code_path),
        ])->render();

        $this->assertStringContainsString(
            Storage::disk('local')->path($entry->qr_code_path),
            $rendered
        );
        $this->assertStringNotContainsString(
            storage_path('app/'.$entry->qr_code_path),
            $rendered
        );
    }

    public function test_notary_request_show_allows_switching_to_closing_tab(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::Submitted,
        ]);

        Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Draft,
        ]);

        $this->actingAs($notary);

        LivewireVolt::test('notary-requests.show', ['notaryRequest' => $request])
            ->call('setActiveTab', 'closing')
            ->assertSet('activeTab', 'closing');
    }

    public function test_notary_request_open_payment_section_sets_closing_tab(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Completed,
        ]);

        DocumentSigner::factory()->for($document)->create([
            'name' => $notary->name,
            'email' => $notary->email,
            'user_id' => $notary->id,
            'role_type' => TemplateRoleType::Signer,
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);

        AttorneyNotarialRegistry::factory()->create([
            'notary_request_id' => $request->id,
            'fees' => 500.00,
        ]);

        $this->actingAs($notary);

        $paymentPageUrl = app(NotaryPublicPaymentLinkService::class)->paymentPageUrl($request);

        LivewireVolt::test('notary-requests.show', ['notaryRequest' => $request])
            ->assertSee(__('Open payment'))
            ->assertSee(__('View payment'))
            ->assertSeeHtml('href="'.e($paymentPageUrl).'"')
            ->set('activeTab', 'closing')
            ->call('openPaymentSection')
            ->assertSet('activeTab', 'closing')
            ->assertDispatched('scroll-to-section', id: 'section-payment', reset: true);
    }

    public function test_notary_request_open_payment_section_resends_payment_email_for_active_pending_payment(): void
    {
        Mail::fake();

        $client = User::factory()->enotarySigner()->create();
        $notary = User::factory()->for($client->organization)->notary()->create();

        $request = NotaryRequest::factory()->for($client)->create([
            'organization_id' => $client->organization_id,
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
            'title' => 'Settlement reminder request',
        ]);

        AttorneyNotarialRegistry::factory()->create([
            'notary_request_id' => $request->id,
            'fees' => 1500.00,
        ]);

        $payment = Payment::query()->create([
            'organization_id' => $request->organization_id,
            'notary_request_id' => $request->id,
            'payer_user_id' => $client->id,
            'created_by_user_id' => $notary->id,
            'provider' => 'gatewayhub',
            'provider_payment_id' => 'payment-open-section-1',
            'provider_transaction_id' => 'payment-open-section-1',
            'gateway' => 'gcash',
            'reference' => 'NREQ-OPEN-SECTION-1',
            'amount' => 1500.00,
            'currency' => 'PHP',
            'status' => PaymentStatus::Pending,
            'checkout_url' => 'https://gatewayhub.test/checkout/open-section',
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($notary);

        LivewireVolt::test('notary-requests.show', ['notaryRequest' => $request])
            ->call('openPaymentSection')
            ->assertSet('activeTab', 'closing')
            ->assertHasNoErrors();

        Mail::assertQueued(NotaryPaymentReadyMail::class, function (NotaryPaymentReadyMail $mail) use ($request, $payment): bool {
            return $mail->notaryRequest->is($request)
                && $mail->payment->is($payment);
        });
    }

    public function test_create_gateway_payment_resends_payment_ready_email_when_pending_payment_exists(): void
    {
        Mail::fake();

        $client = User::factory()->enotarySigner()->create();
        $notary = User::factory()->for($client->organization)->notary()->create();

        $request = NotaryRequest::factory()->for($client)->create([
            'organization_id' => $client->organization_id,
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
            'title' => 'Payment follow-up request',
        ]);

        AttorneyNotarialRegistry::factory()->create([
            'notary_request_id' => $request->id,
            'fees' => 1250.00,
        ]);

        $payment = Payment::query()->create([
            'organization_id' => $request->organization_id,
            'notary_request_id' => $request->id,
            'payer_user_id' => $client->id,
            'created_by_user_id' => $notary->id,
            'provider' => 'gatewayhub',
            'provider_payment_id' => 'payment-existing-1',
            'provider_transaction_id' => 'payment-existing-1',
            'gateway' => 'gcash',
            'reference' => 'NREQ-EXISTING-1',
            'amount' => 1250.00,
            'currency' => 'PHP',
            'status' => PaymentStatus::Pending,
            'checkout_url' => 'https://gatewayhub.test/checkout/existing',
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($notary);
        $recipientEmail = 'client-payment@example.test';

        LivewireVolt::test('notary-requests.show', ['notaryRequest' => $request])
            ->set('enabledPaymentGateways', [['code' => 'gcash', 'name' => 'Gcash']])
            ->set('paymentGateway', 'gcash')
            ->set('paymentRecipientEmail', $recipientEmail)
            ->call('createGatewayPayment')
            ->assertHasNoErrors();

        Mail::assertQueued(NotaryPaymentReadyMail::class, function (NotaryPaymentReadyMail $mail) use ($request, $payment, $recipientEmail): bool {
            return $mail->notaryRequest->is($request)
                && $mail->payment->is($payment)
                && $mail->hasTo($recipientEmail);
        });
    }

    public function test_notary_can_email_payment_page_to_client_via_http_post(): void
    {
        Mail::fake();

        Http::fake([
            'https://gatewayhub.io/api/payments' => Http::response([
                'success' => true,
                'data' => [
                    'payment_id' => 'payment-http-post-1',
                    'transaction_id' => 'payment-http-post-1',
                    'gateway' => 'gcash',
                    'amount' => 1250,
                    'currency' => 'PHP',
                    'status' => 'pending',
                    'qr_data' => '000201-http-post',
                    'expires_at' => now()->addMinutes(30)->toIso8601String(),
                    'redirect_url' => null,
                    'checkout_url' => 'https://gatewayhub.test/checkout/http-post',
                ],
                'error' => null,
            ], 200),
        ]);

        config()->set('services.gatewayhub.api_key', 'test-key');

        $client = User::factory()->enotarySigner()->create();
        $notary = User::factory()->for($client->organization)->notary()->create();

        $request = NotaryRequest::factory()->for($client)->create([
            'organization_id' => $client->organization_id,
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
            'title' => 'Payment email request',
        ]);

        AttorneyNotarialRegistry::factory()->create([
            'notary_request_id' => $request->id,
            'fees' => 1250.00,
        ]);

        $recipientEmail = 'client-payment@example.test';

        $this->actingAs($notary)
            ->post(route('notary.requests.payment-link', $request), [
                'payment_gateway' => 'gcash',
                'recipient_email' => $recipientEmail,
            ])
            ->assertRedirect(route('notary.requests.show', ['notaryRequest' => $request, 'tab' => 'closing', 'section' => 'payment']));

        $payment = Payment::query()->where('notary_request_id', $request->id)->latest('id')->first();
        $this->assertNotNull($payment);
        $this->assertSame('gcash', $payment->gateway);

        Mail::assertQueued(NotaryPaymentReadyMail::class, function (NotaryPaymentReadyMail $mail) use ($request, $payment, $recipientEmail): bool {
            return $mail->notaryRequest->is($request)
                && $mail->payment->is($payment)
                && $mail->hasTo($recipientEmail);
        });
    }

    public function test_resend_payment_link_to_client_regenerates_expired_payment_and_emails_new_link(): void
    {
        Mail::fake();

        Http::fake([
            'https://gatewayhub.io/api/payments' => Http::response([
                'success' => true,
                'data' => [
                    'payment_id' => 'payment-regenerated-1',
                    'transaction_id' => 'payment-regenerated-1',
                    'gateway' => 'gcash',
                    'amount' => 1250,
                    'currency' => 'PHP',
                    'status' => 'pending',
                    'qr_data' => '000201-regenerated',
                    'expires_at' => now()->addMinutes(30)->toIso8601String(),
                    'redirect_url' => null,
                    'checkout_url' => 'https://gatewayhub.test/checkout/regenerated',
                ],
                'error' => null,
            ], 200),
        ]);

        config()->set('services.gatewayhub.api_key', 'test-key');

        $client = User::factory()->enotarySigner()->create();
        $notary = User::factory()->for($client->organization)->notary()->create();

        $request = NotaryRequest::factory()->for($client)->create([
            'organization_id' => $client->organization_id,
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
            'title' => 'Expired payment resend request',
        ]);

        AttorneyNotarialRegistry::factory()->create([
            'notary_request_id' => $request->id,
            'fees' => 1250.00,
        ]);

        Payment::query()->create([
            'organization_id' => $request->organization_id,
            'notary_request_id' => $request->id,
            'payer_user_id' => $client->id,
            'created_by_user_id' => $notary->id,
            'provider' => 'gatewayhub',
            'provider_payment_id' => 'payment-expired-1',
            'provider_transaction_id' => 'payment-expired-1',
            'gateway' => 'gcash',
            'reference' => 'NREQ-EXPIRED-1',
            'amount' => 1250.00,
            'currency' => 'PHP',
            'status' => PaymentStatus::Pending,
            'checkout_url' => 'https://gatewayhub.test/checkout/expired',
            'expires_at' => now()->subMinute(),
        ]);

        $this->actingAs($notary);
        $recipientEmail = 'client-payment@example.test';

        LivewireVolt::test('notary-requests.show', ['notaryRequest' => $request])
            ->set('enabledPaymentGateways', [['code' => 'gcash', 'name' => 'Gcash']])
            ->set('paymentGateway', 'gcash')
            ->set('paymentRecipientEmail', $recipientEmail)
            ->call('resendPaymentLinkToClient')
            ->assertHasNoErrors();

        $replacementPayment = Payment::query()
            ->where('notary_request_id', $request->id)
            ->where('provider_payment_id', 'payment-regenerated-1')
            ->first();

        $this->assertNotNull($replacementPayment);

        Mail::assertQueued(NotaryPaymentReadyMail::class, function (NotaryPaymentReadyMail $mail) use ($request, $replacementPayment, $recipientEmail): bool {
            return $mail->notaryRequest->is($request)
                && $mail->payment->is($replacementPayment)
                && $mail->hasTo($recipientEmail);
        });
    }

    public function test_notary_must_choose_payment_email_recipient_before_sending(): void
    {
        Mail::fake();

        $client = User::factory()->enotarySigner()->create();
        $notary = User::factory()->for($client->organization)->notary()->create();

        $request = NotaryRequest::factory()->for($client)->create([
            'organization_id' => $client->organization_id,
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
            'title' => 'Missing payment recipient request',
        ]);

        AttorneyNotarialRegistry::factory()->create([
            'notary_request_id' => $request->id,
            'fees' => 1250.00,
        ]);

        $this->actingAs($notary)
            ->from(route('notary.requests.show', ['notaryRequest' => $request, 'tab' => 'closing', 'section' => 'payment']))
            ->post(route('notary.requests.payment-link', $request), [
                'payment_gateway' => 'gcash',
                'recipient_email' => '',
            ])
            ->assertRedirect(route('notary.requests.show', ['notaryRequest' => $request, 'tab' => 'closing', 'section' => 'payment']))
            ->assertSessionHasErrors(['recipient_email']);

        Mail::assertNothingQueued();
    }

    public function test_payment_ready_email_points_to_public_payment_page(): void
    {
        $client = User::factory()->enotarySigner()->create();
        $notary = User::factory()->for($client->organization)->notary()->create();

        $request = NotaryRequest::factory()->for($client)->create([
            'organization_id' => $client->organization_id,
            'notary_user_id' => $notary->id,
            'title' => 'Checkout link request',
        ]);

        $payment = Payment::query()->create([
            'organization_id' => $request->organization_id,
            'notary_request_id' => $request->id,
            'payer_user_id' => $client->id,
            'created_by_user_id' => $notary->id,
            'provider' => 'gatewayhub',
            'provider_payment_id' => 'payment-email-link-1',
            'provider_transaction_id' => 'payment-email-link-1',
            'gateway' => 'gcash',
            'reference' => 'NREQ-EMAIL-LINK-1',
            'amount' => 999.00,
            'currency' => 'PHP',
            'status' => PaymentStatus::Pending,
            'checkout_url' => 'https://gatewayhub.test/checkout/direct-email',
            'expires_at' => now()->addHour(),
        ]);

        $html = (new NotaryPaymentReadyMail($request->fresh(['notary']), $payment))->render();
        $this->assertStringContainsString(
            e(url('/notary/payment/'.$request->id)),
            $html
        );
        $this->assertStringContainsString('signature=', $html);
        $this->assertStringContainsString('Pay Now', $html);
    }

    public function test_notary_admin_sees_temporary_testing_payment_link_for_pending_checkout(): void
    {
        $client = User::factory()->enotarySigner()->create();
        $notaryAdmin = User::factory()->for($client->organization)->notaryAdmin()->create();
        $notary = User::factory()->for($client->organization)->notary()->create();

        $request = NotaryRequest::factory()->for($client)->create([
            'organization_id' => $client->organization_id,
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
            'title' => 'Admin payment test request',
        ]);

        AttorneyNotarialRegistry::factory()->create([
            'notary_request_id' => $request->id,
            'fees' => 1250.00,
        ]);

        Payment::query()->create([
            'organization_id' => $request->organization_id,
            'notary_request_id' => $request->id,
            'payer_user_id' => $client->id,
            'created_by_user_id' => $notary->id,
            'provider' => 'gatewayhub',
            'provider_payment_id' => 'payment-admin-link-1',
            'provider_transaction_id' => 'payment-admin-link-1',
            'gateway' => 'gcash',
            'reference' => 'NREQ-ADMIN-LINK-1',
            'amount' => 1250.00,
            'currency' => 'PHP',
            'status' => PaymentStatus::Pending,
            'checkout_url' => 'https://gatewayhub.test/checkout/admin-temporary',
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($notaryAdmin)
            ->get(route('notary-requests.show', $request))
            ->assertOk()
            ->assertSee('Payment email link preview')
            ->assertSee(url('/notary/payment/'.$request->id))
            ->assertSee('signature=')
            ->assertSee('Temporary testing link')
            ->assertSee('Open temporary payment link')
            ->assertSee('https://gatewayhub.test/checkout/admin-temporary');
    }

    public function test_public_payment_page_is_accessible_without_login(): void
    {
        $client = User::factory()->enotarySigner()->create();
        $notary = User::factory()->for($client->organization)->notary()->create();

        $request = NotaryRequest::factory()->for($client)->create([
            'organization_id' => $client->organization_id,
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
            'title' => 'Public payment request',
        ]);

        AttorneyNotarialRegistry::factory()->create([
            'notary_request_id' => $request->id,
            'fees' => 1250.00,
        ]);

        $this->get(app(NotaryPublicPaymentLinkService::class)->paymentPageUrl($request))
            ->assertOk()
            ->assertSee('Pay notarial fee')
            ->assertSee('Continue to payment');
    }

    public function test_public_payment_page_can_create_checkout_without_login(): void
    {
        Http::fake([
            'https://gatewayhub.io/api/payments' => Http::response([
                'success' => true,
                'data' => [
                    'payment_id' => 'payment-public-create-1',
                    'transaction_id' => 'payment-public-create-1',
                    'gateway' => 'gcash',
                    'amount' => 1250,
                    'currency' => 'PHP',
                    'status' => 'pending',
                    'qr_data' => '000201-public-create',
                    'expires_at' => now()->addMinutes(30)->toIso8601String(),
                    'redirect_url' => null,
                    'checkout_url' => 'https://gatewayhub.test/checkout/public-created',
                ],
                'error' => null,
            ], 200),
            'https://gatewayhub.io/api/gateways/enabled' => Http::response([
                'data' => [
                    'gateways' => [
                        ['code' => 'gcash', 'name' => 'Gcash'],
                    ],
                ],
            ], 200),
        ]);

        config()->set('services.gatewayhub.api_key', 'test-key');

        $client = User::factory()->enotarySigner()->create();
        $notary = User::factory()->for($client->organization)->notary()->create();

        $request = NotaryRequest::factory()->for($client)->create([
            'organization_id' => $client->organization_id,
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
            'title' => 'Public checkout request',
        ]);

        AttorneyNotarialRegistry::factory()->create([
            'notary_request_id' => $request->id,
            'fees' => 1250.00,
        ]);

        $showUrl = app(NotaryPublicPaymentLinkService::class)->paymentPageUrl($request);
        parse_str((string) parse_url($showUrl, PHP_URL_QUERY), $query);

        $postUrl = URL::temporarySignedRoute(
            'public.notary.payment.checkout',
            Carbon::createFromTimestamp((int) ($query['expires'] ?? now()->addDays(7)->timestamp)),
            ['notaryRequest' => $request->id],
        );

        $response = $this->post($postUrl, [
            'payment_gateway' => 'gcash',
        ]);

        $response->assertRedirect();

        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('Payment link created. Scan the QR code or open checkout below to continue.')
            ->assertSee('Open checkout')
            ->assertSee('https://api.qrserver.com/v1/create-qr-code/');

        $this->assertDatabaseHas('payments', [
            'notary_request_id' => $request->id,
            'provider_payment_id' => 'payment-public-create-1',
            'payer_user_id' => $client->id,
            'status' => PaymentStatus::Pending->value,
        ]);
    }

    public function test_public_payment_page_still_loads_when_gateway_lookup_times_out(): void
    {
        Http::fake([
            'https://gatewayhub.io/api/gateways/enabled' => Http::failedConnection(),
        ]);

        config()->set('services.gatewayhub.api_key', 'test-key');

        $client = User::factory()->enotarySigner()->create();
        $notary = User::factory()->for($client->organization)->notary()->create();

        $request = NotaryRequest::factory()->for($client)->create([
            'organization_id' => $client->organization_id,
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
            'title' => 'Gateway timeout request',
        ]);

        AttorneyNotarialRegistry::factory()->create([
            'notary_request_id' => $request->id,
            'fees' => 1250.00,
        ]);

        $this->get(app(NotaryPublicPaymentLinkService::class)->paymentPageUrl($request))
            ->assertOk()
            ->assertSee('Pay notarial fee')
            ->assertSee('Online payment is not configured right now. Please contact your attorney.');
    }

    public function test_client_can_create_gateway_payment_from_email_payment_page(): void
    {
        Http::fake([
            'https://gatewayhub.io/api/payments' => Http::response([
                'success' => true,
                'data' => [
                    'payment_id' => 'payment-client-create-1',
                    'transaction_id' => 'payment-client-create-1',
                    'gateway' => 'gcash',
                    'amount' => 1250,
                    'currency' => 'PHP',
                    'status' => 'pending',
                    'qr_data' => '000201-client-create',
                    'expires_at' => now()->addMinutes(30)->toIso8601String(),
                    'redirect_url' => null,
                    'checkout_url' => 'https://gatewayhub.test/checkout/client-created',
                ],
                'error' => null,
            ], 200),
        ]);

        config()->set('services.gatewayhub.api_key', 'test-key');

        $client = User::factory()->enotarySigner()->create();
        $notary = User::factory()->for($client->organization)->notary()->create();

        $request = NotaryRequest::factory()->for($client)->create([
            'organization_id' => $client->organization_id,
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
            'title' => 'Client creates payment request',
        ]);

        AttorneyNotarialRegistry::factory()->create([
            'notary_request_id' => $request->id,
            'fees' => 1250.00,
        ]);

        NotarySigner::factory()->for($request)->create([
            'email' => $client->email,
        ]);

        $this->actingAs($client);

        LivewireVolt::test('notary-requests.show', ['notaryRequest' => $request])
            ->set('enabledPaymentGateways', [['code' => 'gcash', 'name' => 'Gcash']])
            ->set('paymentGateway', 'gcash')
            ->call('createGatewayPaymentForClient')
            ->assertRedirect('https://gatewayhub.test/checkout/client-created');

        $this->assertDatabaseHas('payments', [
            'notary_request_id' => $request->id,
            'provider_payment_id' => 'payment-client-create-1',
            'payer_user_id' => $client->id,
            'status' => PaymentStatus::Pending->value,
        ]);
    }

    public function test_client_cannot_create_gateway_payment_on_notary_request(): void
    {
        $client = User::factory()->enotarySigner()->create();
        $notary = User::factory()->for($client->organization)->notary()->create();

        $request = NotaryRequest::factory()->for($client)->create([
            'organization_id' => $client->organization_id,
            'notary_user_id' => $notary->id,
        ]);

        AttorneyNotarialRegistry::factory()->create([
            'notary_request_id' => $request->id,
            'fees' => 500.00,
        ]);

        NotarySigner::factory()->for($request)->create([
            'email' => $client->email,
        ]);

        $this->actingAs($client);

        LivewireVolt::test('notary-requests.show', ['notaryRequest' => $request])
            ->set('enabledPaymentGateways', [['code' => 'gcash', 'name' => 'Gcash']])
            ->set('paymentGateway', 'gcash')
            ->call('createGatewayPayment')
            ->assertForbidden();
    }

    private function ensureEisSigningPrivateKeyPath(): string
    {
        $privateKeyPath = storage_path('app/testing/eis-signing-test.key');
        $directory = dirname($privateKeyPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        if (! is_file($privateKeyPath)) {
            $key = openssl_pkey_new([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);

            if ($key === false) {
                $this->fail('Unable to generate an EIS signing private key for tests.');
            }

            openssl_pkey_export_to_file($key, $privateKeyPath);
        }

        return $privateKeyPath;
    }
}
