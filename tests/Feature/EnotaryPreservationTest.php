<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\NotaryRequestStatus;
use App\Models\Document;
use App\Models\DocumentHash;
use App\Models\DocumentSigner;
use App\Models\NotarialRegisterEntry;
use App\Models\NotaryCredential;
use App\Models\NotaryJournal;
use App\Models\NotaryRequest;
use App\Models\Organization;
use App\Models\SignatureField;
use App\Models\User;
use App\Services\CompletedDocumentArtifactService;
use App\Services\DocumentPdfStampingService;
use App\Services\NotarialCertificateService;
use App\Services\NotaryDigitalizationService;
use App\Services\NotarySealService;
use App\Services\SendDocumentForSignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Preservation Property Tests — Standard Document Workflow Unchanged
 *
 * These tests capture the baseline behavior of the system for standard documents
 * (where notary_request_id IS NULL). They MUST PASS on both unfixed and fixed code,
 * confirming that the eNOTARY bugfix does not regress standard document workflows.
 *
 * **Validates: Requirements 3.1, 3.2, 3.5, 3.6**
 */
class EnotaryPreservationTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────────────
    // Property: Standard Document Prepare Access (Requirement 3.1, 3.6)
    // For all documents where notary_request_id IS NULL, any user passing
    // DocumentPolicy::update() can access documents.prepare (200 response)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Standard document: document owner can access the prepare page.
     *
     * **Validates: Requirements 3.1, 3.6**
     */
    public function test_document_owner_can_access_prepare_page_for_standard_document(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create(['organization_id' => $organization->id]);

        $document = Document::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'notary_request_id' => null,
            'status' => DocumentStatus::Draft,
        ]);

        // Add a signer so the prepare page doesn't redirect
        DocumentSigner::factory()->create([
            'document_id' => $document->id,
        ]);

        $this->actingAs($owner);

        $response = $this->get(route('documents.prepare', $document));

        $response->assertStatus(200);
    }

    /**
     * Standard document: org admin (different user) can access the prepare page.
     *
     * **Validates: Requirements 3.1, 3.6**
     */
    public function test_org_admin_can_access_prepare_page_for_standard_document(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create(['organization_id' => $organization->id]);
        $admin = User::factory()->admin()->create(['organization_id' => $organization->id]);

        $document = Document::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'notary_request_id' => null,
            'status' => DocumentStatus::Draft,
        ]);

        // Add a signer so the prepare page doesn't redirect
        DocumentSigner::factory()->create([
            'document_id' => $document->id,
        ]);

        $this->actingAs($admin);

        $response = $this->get(route('documents.prepare', $document));

        $response->assertStatus(200);
    }

    /**
     * Standard document: client user who owns the document can access prepare page.
     *
     * **Validates: Requirements 3.1, 3.6**
     */
    public function test_client_owner_can_access_prepare_page_for_standard_document(): void
    {
        $organization = Organization::factory()->create();
        $client = User::factory()->client()->create(['organization_id' => $organization->id]);

        $document = Document::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $client->id,
            'notary_request_id' => null,
            'status' => DocumentStatus::Draft,
        ]);

        // Add a signer so the prepare page doesn't redirect
        DocumentSigner::factory()->create([
            'document_id' => $document->id,
        ]);

        $this->actingAs($client);

        $response = $this->get(route('documents.prepare', $document));

        $response->assertStatus(200);
    }

    /**
     * Standard document: notary_admin user in same org can access prepare page for their own document.
     *
     * **Validates: Requirements 3.1, 3.6**
     */
    public function test_notary_admin_can_access_prepare_page_for_own_standard_document(): void
    {
        $organization = Organization::factory()->create();
        $notaryAdmin = User::factory()->notaryAdmin()->create(['organization_id' => $organization->id]);

        $document = Document::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $notaryAdmin->id,
            'notary_request_id' => null,
            'status' => DocumentStatus::Draft,
        ]);

        // Add a signer so the prepare page doesn't redirect
        DocumentSigner::factory()->create([
            'document_id' => $document->id,
        ]);

        $this->actingAs($notaryAdmin);

        $response = $this->get(route('documents.prepare', $document));

        $response->assertStatus(200);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Property: Standard Document Send (Requirement 3.2)
    // For all standard documents in Draft status with valid participants and
    // fields, SendDocumentForSignatureService::send() succeeds and transitions
    // to Pending
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Standard document: send() transitions document from Draft to Pending.
     *
     * **Validates: Requirements 3.2**
     */
    public function test_send_service_transitions_standard_document_from_draft_to_pending(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create(['organization_id' => $organization->id]);

        $document = Document::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'notary_request_id' => null,
            'status' => DocumentStatus::Draft,
        ]);

        $signer = DocumentSigner::factory()->create([
            'document_id' => $document->id,
            'signing_order' => 1,
        ]);

        SignatureField::factory()->create([
            'document_id' => $document->id,
            'signer_id' => $signer->id,
        ]);

        // Mock the PDF stamping service to avoid filesystem operations
        $this->mock(DocumentPdfStampingService::class, function ($mock) {
            $mock->shouldReceive('generatePreparedPdf')->andReturn('documents/prepared.pdf');
        });

        $service = app(SendDocumentForSignatureService::class);
        $service->send($document);

        $document->refresh();
        $this->assertEquals(DocumentStatus::Pending, $document->status);
        $this->assertNotNull($document->sent_at);
    }

    /**
     * Standard document: send() assigns access tokens to signers.
     *
     * **Validates: Requirements 3.2**
     */
    public function test_send_service_assigns_access_tokens_to_signers_for_standard_document(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create(['organization_id' => $organization->id]);

        $document = Document::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'notary_request_id' => null,
            'status' => DocumentStatus::Draft,
        ]);

        $signer = DocumentSigner::factory()->create([
            'document_id' => $document->id,
            'signing_order' => 1,
            'access_token' => null,
        ]);

        SignatureField::factory()->create([
            'document_id' => $document->id,
            'signer_id' => $signer->id,
        ]);

        // Mock the PDF stamping service to avoid filesystem operations
        $this->mock(DocumentPdfStampingService::class, function ($mock) {
            $mock->shouldReceive('generatePreparedPdf')->andReturn('documents/prepared.pdf');
        });

        $service = app(SendDocumentForSignatureService::class);
        $service->send($document);

        $signer->refresh();
        $this->assertNotNull($signer->access_token);
        $this->assertNotNull($signer->expires_at);
    }

    /**
     * Standard document: send() throws when document is not in Draft status.
     *
     * **Validates: Requirements 3.2**
     */
    public function test_send_service_throws_for_non_draft_standard_document(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create(['organization_id' => $organization->id]);

        $document = Document::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'notary_request_id' => null,
            'status' => DocumentStatus::Pending,
        ]);

        $this->expectException(\RuntimeException::class);

        $service = app(SendDocumentForSignatureService::class);
        $service->send($document);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Property: Digitalization Existing Behavior (Requirement 3.5)
    // For all notary requests processed through digitalize(), seal is applied,
    // QR codes generated, certificates generated, blockchain anchored, and
    // journal entry created with documents_processed and
    // register_entries_processed counts
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Digitalization: creates journal entry with documents_processed and
     * register_entries_processed counts.
     *
     * **Validates: Requirements 3.5**
     */
    public function test_digitalization_creates_journal_entry_with_processing_counts(): void
    {
        $organization = Organization::factory()->create();
        $client = User::factory()->client()->create(['organization_id' => $organization->id]);
        $notary = User::factory()->notary()->create(['organization_id' => $organization->id]);

        $credential = NotaryCredential::factory()->create([
            'user_id' => $notary->id,
            'seal_image_path' => 'notary/seals/seal.png',
            'signature_image_path' => 'notary/signatures/sig.png',
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

        // Pre-create a DocumentHash with transaction_id so blockchain anchoring is satisfied
        DocumentHash::query()->create([
            'document_id' => $document->id,
            'hash' => 'sha256-test-hash-'.$document->id,
            'transaction_id' => 'tx-already-anchored-'.$document->id,
            'created_at' => now(),
        ]);

        // Create register entry linked to the credential
        NotarialRegisterEntry::factory()->create([
            'notary_request_id' => $notaryRequest->id,
            'notary_credential_id' => $credential->id,
            'document_id' => $document->id,
        ]);

        // Mock services that interact with filesystem
        $this->mock(NotarySealService::class, function ($mock) {
            $mock->shouldReceive('generateVerificationQrCode')->andReturnNull();
            $mock->shouldReceive('applyNotarySeal')->andReturnNull();
        });
        $this->mock(NotarialCertificateService::class, function ($mock) {
            $mock->shouldReceive('generate')->andReturnNull();
        });
        $this->mock(CompletedDocumentArtifactService::class, function ($mock) {
            $mock->shouldReceive('ensureReady')->andReturnNull();
        });

        // Execute digitalization
        app(NotaryDigitalizationService::class)->digitalize($notaryRequest);

        // Verify journal entry was created
        $journal = NotaryJournal::query()
            ->where('notary_request_id', $notaryRequest->id)
            ->where('entry_type', 'digitalization_completed')
            ->latest()
            ->first();

        $this->assertNotNull($journal, 'Digitalization should create a journal entry');
        $this->assertEquals($notary->id, $journal->notary_user_id);

        $legalAssertions = $journal->legal_assertions;
        $this->assertArrayHasKey('documents_processed', $legalAssertions);
        $this->assertArrayHasKey('register_entries_processed', $legalAssertions);
        $this->assertArrayHasKey('completed_at', $legalAssertions);
        $this->assertEquals(1, $legalAssertions['documents_processed']);
        $this->assertEquals(1, $legalAssertions['register_entries_processed']);
    }

    /**
     * Digitalization: invokes seal application for each document.
     *
     * **Validates: Requirements 3.5**
     */
    public function test_digitalization_applies_seal_to_documents(): void
    {
        $organization = Organization::factory()->create();
        $client = User::factory()->client()->create(['organization_id' => $organization->id]);
        $notary = User::factory()->notary()->create(['organization_id' => $organization->id]);

        $credential = NotaryCredential::factory()->create([
            'user_id' => $notary->id,
            'seal_image_path' => 'notary/seals/seal.png',
            'signature_image_path' => 'notary/signatures/sig.png',
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

        // Pre-create a DocumentHash with transaction_id
        DocumentHash::query()->create([
            'document_id' => $document->id,
            'hash' => 'sha256-test-hash-'.$document->id,
            'transaction_id' => 'tx-already-anchored-'.$document->id,
            'created_at' => now(),
        ]);

        NotarialRegisterEntry::factory()->create([
            'notary_request_id' => $notaryRequest->id,
            'notary_credential_id' => $credential->id,
            'document_id' => $document->id,
        ]);

        // Mock services - verify seal is applied once during digital notarization
        $sealApplied = false;
        $this->mock(NotarySealService::class, function ($mock) use (&$sealApplied) {
            $mock->shouldReceive('generateVerificationQrCode')->andReturnNull();
            $mock->shouldReceive('applyNotarySeal')->once()->andReturnUsing(function () use (&$sealApplied) {
                $sealApplied = true;

                return 'documents/notarized/mock-final.pdf';
            });
        });
        $this->mock(NotarialCertificateService::class, function ($mock) {
            $mock->shouldReceive('generate')->andReturnNull();
        });
        $this->mock(CompletedDocumentArtifactService::class, function ($mock) {
            $mock->shouldReceive('ensureReady')->andReturnNull();
        });

        app(NotaryDigitalizationService::class)->digitalize($notaryRequest);

        $this->assertTrue($sealApplied, 'Notary seal should be applied to documents during digitalization');
    }

    /**
     * Digitalization: generates QR codes for register entries.
     *
     * **Validates: Requirements 3.5**
     */
    public function test_digitalization_generates_qr_codes_for_register_entries(): void
    {
        $organization = Organization::factory()->create();
        $client = User::factory()->client()->create(['organization_id' => $organization->id]);
        $notary = User::factory()->notary()->create(['organization_id' => $organization->id]);

        $credential = NotaryCredential::factory()->create([
            'user_id' => $notary->id,
            'seal_image_path' => 'notary/seals/seal.png',
            'signature_image_path' => 'notary/signatures/sig.png',
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

        DocumentHash::query()->create([
            'document_id' => $document->id,
            'hash' => 'sha256-test-hash-'.$document->id,
            'transaction_id' => 'tx-already-anchored-'.$document->id,
            'created_at' => now(),
        ]);

        // Create entry without QR code path to trigger generation
        NotarialRegisterEntry::factory()->create([
            'notary_request_id' => $notaryRequest->id,
            'notary_credential_id' => $credential->id,
            'document_id' => $document->id,
            'qr_code_path' => null,
        ]);

        // Mock services - verify QR generation is called
        $qrGenerated = false;
        $this->mock(NotarySealService::class, function ($mock) use (&$qrGenerated) {
            $mock->shouldReceive('generateVerificationQrCode')->once()->andReturnUsing(function () use (&$qrGenerated) {
                $qrGenerated = true;
            });
            $mock->shouldReceive('applyNotarySeal')->andReturnNull();
        });
        $this->mock(NotarialCertificateService::class, function ($mock) {
            $mock->shouldReceive('generate')->andReturnNull();
        });
        $this->mock(CompletedDocumentArtifactService::class, function ($mock) {
            $mock->shouldReceive('ensureReady')->andReturnNull();
        });

        app(NotaryDigitalizationService::class)->digitalize($notaryRequest);

        $this->assertTrue($qrGenerated, 'QR code should be generated for register entries during digitalization');
    }

    /**
     * Digitalization: generates certificates for register entries.
     *
     * **Validates: Requirements 3.5**
     */
    public function test_digitalization_generates_certificates_for_register_entries(): void
    {
        $organization = Organization::factory()->create();
        $client = User::factory()->client()->create(['organization_id' => $organization->id]);
        $notary = User::factory()->notary()->create(['organization_id' => $organization->id]);

        $credential = NotaryCredential::factory()->create([
            'user_id' => $notary->id,
            'seal_image_path' => 'notary/seals/seal.png',
            'signature_image_path' => 'notary/signatures/sig.png',
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

        DocumentHash::query()->create([
            'document_id' => $document->id,
            'hash' => 'sha256-test-hash-'.$document->id,
            'transaction_id' => 'tx-already-anchored-'.$document->id,
            'created_at' => now(),
        ]);

        NotarialRegisterEntry::factory()->create([
            'notary_request_id' => $notaryRequest->id,
            'notary_credential_id' => $credential->id,
            'document_id' => $document->id,
        ]);

        // Mock services - verify certificate generation is called
        $certificateGenerated = false;
        $this->mock(NotarySealService::class, function ($mock) {
            $mock->shouldReceive('generateVerificationQrCode')->andReturnNull();
            $mock->shouldReceive('applyNotarySeal')->andReturnNull();
        });
        $this->mock(NotarialCertificateService::class, function ($mock) use (&$certificateGenerated) {
            $mock->shouldReceive('generate')->once()->andReturnUsing(function () use (&$certificateGenerated) {
                $certificateGenerated = true;
            });
        });
        $this->mock(CompletedDocumentArtifactService::class, function ($mock) {
            $mock->shouldReceive('ensureReady')->andReturnNull();
        });

        app(NotaryDigitalizationService::class)->digitalize($notaryRequest);

        $this->assertTrue($certificateGenerated, 'Certificates should be generated for register entries during digitalization');
    }

    /**
     * Digitalization: ensures blockchain anchoring for completed documents.
     *
     * **Validates: Requirements 3.5**
     */
    public function test_digitalization_ensures_blockchain_anchoring(): void
    {
        $organization = Organization::factory()->create();
        $client = User::factory()->client()->create(['organization_id' => $organization->id]);
        $notary = User::factory()->notary()->create(['organization_id' => $organization->id]);

        $credential = NotaryCredential::factory()->create([
            'user_id' => $notary->id,
            'seal_image_path' => 'notary/seals/seal.png',
            'signature_image_path' => 'notary/signatures/sig.png',
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

        // Pre-create a DocumentHash WITH transaction_id (already anchored)
        DocumentHash::query()->create([
            'document_id' => $document->id,
            'hash' => 'sha256-test-hash-'.$document->id,
            'transaction_id' => 'tx-blockchain-proof-'.$document->id,
            'created_at' => now(),
        ]);

        NotarialRegisterEntry::factory()->create([
            'notary_request_id' => $notaryRequest->id,
            'notary_credential_id' => $credential->id,
            'document_id' => $document->id,
        ]);

        $this->mock(NotarySealService::class, function ($mock) {
            $mock->shouldReceive('generateVerificationQrCode')->andReturnNull();
            $mock->shouldReceive('applyNotarySeal')->andReturnNull();
        });
        $this->mock(NotarialCertificateService::class, function ($mock) {
            $mock->shouldReceive('generate')->andReturnNull();
        });
        $this->mock(CompletedDocumentArtifactService::class, function ($mock) {
            $mock->shouldReceive('ensureReady')->andReturnNull();
        });

        // Should not throw - blockchain anchoring is already done
        $result = app(NotaryDigitalizationService::class)->digitalize($notaryRequest);

        // Verify the document hash still has its transaction_id (blockchain proof preserved)
        $hash = DocumentHash::query()->where('document_id', $document->id)->first();
        $this->assertNotNull($hash);
        $this->assertNotNull($hash->transaction_id);
        $this->assertStringContainsString('tx-blockchain-proof-', $hash->transaction_id);
    }

    /**
     * Digitalization: handles multiple documents and register entries correctly.
     *
     * **Validates: Requirements 3.5**
     */
    public function test_digitalization_handles_multiple_documents_and_entries(): void
    {
        $organization = Organization::factory()->create();
        $client = User::factory()->client()->create(['organization_id' => $organization->id]);
        $notary = User::factory()->notary()->create(['organization_id' => $organization->id]);

        $credential = NotaryCredential::factory()->create([
            'user_id' => $notary->id,
            'seal_image_path' => 'notary/seals/seal.png',
            'signature_image_path' => 'notary/signatures/sig.png',
            'status' => 'active',
        ]);

        $notaryRequest = NotaryRequest::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $client->id,
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneyApproved,
        ]);

        // Create 2 documents
        $documents = [];
        for ($i = 0; $i < 2; $i++) {
            $doc = Document::factory()->create([
                'organization_id' => $organization->id,
                'user_id' => $client->id,
                'notary_request_id' => $notaryRequest->id,
                'status' => DocumentStatus::Completed,
            ]);

            DocumentHash::query()->create([
                'document_id' => $doc->id,
                'hash' => 'sha256-test-hash-'.$doc->id,
                'transaction_id' => 'tx-anchored-'.$doc->id,
                'created_at' => now(),
            ]);

            NotarialRegisterEntry::factory()->create([
                'notary_request_id' => $notaryRequest->id,
                'notary_credential_id' => $credential->id,
                'document_id' => $doc->id,
            ]);

            $documents[] = $doc;
        }

        $this->mock(NotarySealService::class, function ($mock) {
            $mock->shouldReceive('generateVerificationQrCode')->andReturnNull();
            $mock->shouldReceive('applyNotarySeal')->andReturnNull();
        });
        $this->mock(NotarialCertificateService::class, function ($mock) {
            $mock->shouldReceive('generate')->andReturnNull();
        });
        $this->mock(CompletedDocumentArtifactService::class, function ($mock) {
            $mock->shouldReceive('ensureReady')->andReturnNull();
        });

        app(NotaryDigitalizationService::class)->digitalize($notaryRequest);

        // Verify journal entry counts reflect multiple documents/entries
        $journal = NotaryJournal::query()
            ->where('notary_request_id', $notaryRequest->id)
            ->where('entry_type', 'digitalization_completed')
            ->latest()
            ->first();

        $this->assertNotNull($journal);
        $this->assertEquals(2, $journal->legal_assertions['documents_processed']);
        $this->assertEquals(2, $journal->legal_assertions['register_entries_processed']);
    }
}
