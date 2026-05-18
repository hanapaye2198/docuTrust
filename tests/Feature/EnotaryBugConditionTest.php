<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\NotaryRequestStatus;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\NotarialRegisterEntry;
use App\Models\NotaryCredential;
use App\Models\NotaryJournal;
use App\Models\NotaryRequest;
use App\Models\SignatureField;
use App\Models\User;
use App\Services\SendDocumentForSignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Bug Condition Exploration Test — eNOTARY Document Workflow Defects
 *
 * This test encodes the EXPECTED (correct) behavior for eNOTARY documents.
 * When run on UNFIXED code, these tests SHOULD FAIL — failure proves the bugs exist.
 *
 * **Validates: Requirements 2.1, 2.2, 2.3, 2.4, 2.5**
 */
class EnotaryBugConditionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Updated for new flow: Client creates notary request with case info only (no document upload).
     * System redirects to notary-requests.show.
     *
     * Expected behavior: After creating a notary request, the client should be redirected
     * to the notary request show page.
     *
     * **Validates: Requirements 2.1**
     */
    public function test_client_redirect_after_notary_request_creation_goes_to_notary_requests_show(): void
    {
        $organization = \App\Models\Organization::factory()->create();
        $client = User::factory()->client()->create(['organization_id' => $organization->id]);
        $notary = User::factory()->notary()->create(['organization_id' => $organization->id]);

        $this->actingAs($client);

        // Client only provides case info — no document upload, no signers
        $component = \Livewire\Livewire::test('notary-requests.create')
            ->set('title', 'Test Deed of Sale')
            ->set('requestType', 'acknowledgment')
            ->set('notaryUserId', (string) $notary->id)
            ->set('remarks', 'Please notarize this deed.')
            ->call('save');

        // Get the created notary request
        $request = \App\Models\NotaryRequest::query()
            ->where('user_id', $client->id)
            ->where('title', 'Test Deed of Sale')
            ->first();

        $this->assertNotNull($request, 'Notary request should have been created');
        $this->assertEquals($notary->id, $request->notary_user_id);

        // Expected: client should be redirected to notary-requests.show
        $expectedRoute = route('notary-requests.show', $request, absolute: false);
        $component->assertRedirect($expectedRoute);
    }

    /**
     * Defect 1.2: Non-attorney user accesses DocumentPrepareController::show() for document
     * with notary_request_id set → system returns 200 instead of 403.
     *
     * Expected behavior: Non-attorney users should receive 403 when accessing the prepare
     * page for eNOTARY documents.
     *
     * **Validates: Requirements 2.2**
     */
    public function test_non_attorney_user_gets_403_on_enotary_document_prepare(): void
    {
        $organization = \App\Models\Organization::factory()->create();
        $client = User::factory()->client()->create(['organization_id' => $organization->id]);
        $notary = User::factory()->notary()->create(['organization_id' => $organization->id]);

        $notaryRequest = NotaryRequest::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $client->id,
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::Draft,
        ]);

        $document = Document::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $client->id,
            'notary_request_id' => $notaryRequest->id,
            'status' => DocumentStatus::Draft,
        ]);

        // Add a signer so the prepare page doesn't redirect due to missing signers
        $signer = DocumentSigner::factory()->create([
            'document_id' => $document->id,
        ]);

        // Authenticate as the client (non-attorney) user
        $this->actingAs($client);

        // Access the prepare page for an eNOTARY document as a non-attorney
        // Expected: 403 Forbidden (only the assigned attorney should access this)
        // Bug: returns 200 (allows any user with document update permission)
        $response = $this->get(route('documents.prepare', $document));

        $response->assertStatus(403);
    }

    /**
     * Defect 1.3 (UPDATED): SendDocumentForSignatureService::send() now ALLOWS eNOTARY documents
     * to be sent for signing. The new flow has the attorney send documents to signers.
     *
     * Expected behavior: Calling send() on an eNOTARY document should succeed (transition to Pending).
     *
     * **Validates: New flow — attorney sends eNOTARY docs to signers**
     */
    public function test_send_document_for_signature_succeeds_for_enotary_document(): void
    {
        $organization = \App\Models\Organization::factory()->create();
        $client = User::factory()->client()->create(['organization_id' => $organization->id]);
        $notary = User::factory()->notary()->create(['organization_id' => $organization->id]);

        $notaryRequest = NotaryRequest::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $client->id,
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneyApproved,
        ]);

        $document = Document::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $client->id,
            'notary_request_id' => $notaryRequest->id,
            'status' => DocumentStatus::Draft,
        ]);

        // Set up valid signers and fields so the service doesn't fail for other reasons
        $signer = DocumentSigner::factory()->create([
            'document_id' => $document->id,
            'signing_order' => 1,
        ]);

        SignatureField::factory()->create([
            'document_id' => $document->id,
            'signer_id' => $signer->id,
        ]);

        // Expected: No exception thrown, document transitions to Pending
        app(SendDocumentForSignatureService::class)->send($document);

        $document->refresh();
        $this->assertEquals(DocumentStatus::Pending, $document->status);
        $this->assertNotNull($document->sent_at);
    }

    /**
     * Defect 1.4: NotaryDigitalizationService::digitalize() completes → journal entry
     * lacks attorney_signature_applied and attorney_credential_id.
     *
     * Expected behavior: After digitalization, the journal entry should contain
     * attorney_signature_applied = true and attorney_credential_id in legal_assertions.
     *
     * **Validates: Requirements 2.4**
     */
    public function test_digitalization_records_attorney_signature_and_credential_in_journal(): void
    {
        $organization = \App\Models\Organization::factory()->create();
        $client = User::factory()->client()->create(['organization_id' => $organization->id]);
        $notary = User::factory()->notary()->create(['organization_id' => $organization->id]);

        $credential = NotaryCredential::factory()->create([
            'user_id' => $notary->id,
            'signature_image_path' => 'notary/signatures/attorney-sig.png',
            'seal_image_path' => 'notary/seals/attorney-seal.png',
            'status' => 'active',
        ]);

        $notaryRequest = NotaryRequest::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $client->id,
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneyApproved,
        ]);

        $document = Document::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $client->id,
            'notary_request_id' => $notaryRequest->id,
            'status' => DocumentStatus::Completed,
        ]);

        // Pre-create a DocumentHash with transaction_id so blockchain anchoring is skipped
        \App\Models\DocumentHash::query()->create([
            'document_id' => $document->id,
            'hash' => 'sha256-test-hash-' . $document->id,
            'transaction_id' => 'tx-already-anchored-' . $document->id,
            'created_at' => now(),
        ]);

        // Create a register entry linked to the credential
        NotarialRegisterEntry::factory()->create([
            'notary_request_id' => $notaryRequest->id,
            'notary_credential_id' => $credential->id,
            'document_id' => $document->id,
        ]);

        // Mock services that interact with filesystem to avoid side effects
        $this->mock(\App\Services\NotarySealService::class, function ($mock) {
            $mock->shouldReceive('generateVerificationQrCode')->andReturnNull();
            $mock->shouldReceive('applyNotarySeal')->andReturnNull();
        });
        $this->mock(\App\Services\NotarialCertificateService::class, function ($mock) {
            $mock->shouldReceive('generate')->andReturnNull();
        });
        $this->mock(\App\Services\CompletedDocumentArtifactService::class, function ($mock) {
            $mock->shouldReceive('ensureReady')->andReturnNull();
        });

        // Execute digitalization
        $result = app(\App\Services\NotaryDigitalizationService::class)->digitalize($notaryRequest);

        // Find the journal entry created during digitalization
        $journal = NotaryJournal::query()
            ->where('notary_request_id', $notaryRequest->id)
            ->where('entry_type', 'digitalization_completed')
            ->latest()
            ->first();

        $this->assertNotNull($journal, 'Digitalization should create a journal entry');

        $legalAssertions = $journal->legal_assertions;

        // Expected: journal should record attorney signature was applied
        // Bug: these fields are missing from the journal entry
        $this->assertArrayHasKey('attorney_signature_applied', $legalAssertions,
            'Journal legal_assertions should contain attorney_signature_applied');
        $this->assertTrue($legalAssertions['attorney_signature_applied'],
            'attorney_signature_applied should be true');

        $this->assertArrayHasKey('attorney_credential_id', $legalAssertions,
            'Journal legal_assertions should contain attorney_credential_id');
        $this->assertNotNull($legalAssertions['attorney_credential_id'],
            'attorney_credential_id should not be null');
    }

    /**
     * Defect 1.5 (UPDATED): The sendLinkedDocument() method now ALLOWS the attorney to send
     * eNOTARY documents to signers. Non-attorney users are blocked.
     *
     * Expected behavior: Only the assigned attorney can send eNOTARY documents.
     * Non-attorney users should see an error.
     *
     * **Validates: New flow — only attorney can send**
     */
    public function test_send_linked_document_blocked_for_non_attorney_users(): void
    {
        $organization = \App\Models\Organization::factory()->create();
        $client = User::factory()->client()->create(['organization_id' => $organization->id]);
        $notary = User::factory()->notary()->create(['organization_id' => $organization->id]);

        $notaryRequest = NotaryRequest::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $client->id,
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneyApproved,
        ]);

        $document = Document::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $client->id,
            'notary_request_id' => $notaryRequest->id,
            'status' => DocumentStatus::Draft,
        ]);

        // Set up valid signers and fields
        $signer = DocumentSigner::factory()->create([
            'document_id' => $document->id,
            'signing_order' => 1,
        ]);

        SignatureField::factory()->create([
            'document_id' => $document->id,
            'signer_id' => $signer->id,
        ]);

        // Test as client (non-attorney) — should see error
        $this->actingAs($client);

        $component = \Livewire\Livewire::test('notary-requests.show', ['notaryRequest' => $notaryRequest])
            ->call('sendLinkedDocument', $document->id);

        $component->assertHasErrors('sendDocument' . $document->id);
    }
}
