<?php

namespace Tests\Feature;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Enums\TemplateRoleType;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\DocumentHash;
use App\Models\DocumentSigner;
use App\Models\NotaryRequest;
use App\Models\SignatureField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt as LivewireVolt;
use Tests\TestCase;

class NotaryRequestPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_notary_request_from_livewire_page(): void
    {
        $admin = User::factory()->create();
        $notary = User::factory()->for($admin->organization)->create([
            'role' => UserRole::Notary,
        ]);

        $this->actingAs($admin);

        // Admin (non-notary) only provides case info — no signers or documents
        LivewireVolt::test('notary-requests.create')
            ->set('title', 'Board resolution acknowledgment')
            ->set('requestType', 'acknowledgment')
            ->set('notaryUserId', (string) $notary->id)
            ->set('remarks', 'Bring government ID and board secretary certificate.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('notary_requests', [
            'title' => 'Board resolution acknowledgment',
            'notary_user_id' => $notary->id,
            'organization_id' => $admin->organization_id,
        ]);
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
            ->assertSee('Workflow')
            ->assertSee('Upload &amp; send', false)
            ->assertSee('Signers sign')
            ->assertSee('Video conference')
            ->assertSee('Attorney signs')
            ->assertSee('Register entry')
            ->assertSee('Digital notarization')
            ->assertSee('Notary review')
            ->assertSee('Notary review is available only after identity, location, or session verification has started.')
            ->assertDontSee('Approve request')
            ->assertSee('Notarial register')
            ->assertSee('Register entry creation becomes available after attorney approval.')
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
}
