<?php

namespace Tests\Feature;

use App\Enums\NotaryRequestStatus;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\NotarialRegisterEntry;
use App\Models\NotaryCredential;
use App\Models\NotaryRequest;
use App\Models\User;
use App\Services\GeolocationService;
use App\Services\IdentityVerificationService;
use App\Services\LocationVerificationService;
use App\Services\NotarialRegisterService;
use App\Services\NotaryRequestWorkflowService;
use App\Services\NotarySchedulingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotaryEnotaryFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_enotary_8_step_flow(): void
    {
        // Step 1: Create Notary Request
        $requester = User::factory()->create();
        $notary = User::factory()->for($requester->organization)->create([
            'role' => UserRole::Notary,
        ]);

        $request = NotaryRequest::factory()->for($requester)->create([
            'notary_user_id' => $notary->id,
            'request_type' => 'acknowledgment',
            'title' => 'Affidavit of Self-Adjudication',
        ]);

        $this->assertSame(NotaryRequestStatus::Draft, $request->status);

        $document = Document::factory()->for($requester)->create([
            'notary_request_id' => $request->id,
            'status' => \App\Enums\DocumentStatus::Completed,
        ]);
        DocumentSigner::factory()->for($document)->create([
            'status' => \App\Enums\DocumentSignerStatus::Signed,
            'signing_order' => 1,
        ]);
        DocumentSigner::factory()->for($document)->create([
            'user_id' => $notary->id,
            'email' => $notary->email,
            'status' => \App\Enums\DocumentSignerStatus::Signed,
            'signing_order' => 999,
        ]);

        // Submit the request
        $workflowService = app(NotaryRequestWorkflowService::class);
        $workflowService->submit($request);
        $request->refresh();
        $this->assertSame(NotaryRequestStatus::Submitted, $request->status);

        // Step 2: Identity Verification
        $identityService = app(IdentityVerificationService::class);
        $identityService->verify($request, [
            'id_document_type' => 'passport',
            'id_document_number' => 'P1030912OB',
            'id_document_path' => 'identity/passport-scan.pdf',
            'selfie_path' => 'identity/selfie.jpg',
            'otp_verified' => true,
        ]);
        $request->refresh();
        $this->assertSame(NotaryRequestStatus::IdentityVerified, $request->status);
        $this->assertSame('passport', $request->id_document_type);
        $this->assertSame('P1030912OB', $request->id_document_number);
        $this->assertNotNull($request->identity_verified_at);

        // Step 3: Location Verification (Philippines Only)
        $locationService = app(LocationVerificationService::class);
        $locationService->markVerified($request, [
            'ip_address' => '120.28.45.100',
            'country_code' => 'PH',
            'latitude' => 7.0731,
            'longitude' => 125.6128,
            'vpn_detected' => false,
            'source' => 'ip_geolocation',
        ]);
        $request->refresh();
        $this->assertSame(NotaryRequestStatus::LocationVerified, $request->status);
        $this->assertSame('PH', $request->location_country_code);
        $this->assertNotNull($request->location_verified_at);
        $this->assertFalse($request->location_vpn_detected);

        $this->assertDatabaseHas('notary_geo_logs', [
            'notary_request_id' => $request->id,
            'verification_status' => 'passed',
        ]);

        // Step 4: Schedule Session
        $schedulingService = app(NotarySchedulingService::class);
        $session = $schedulingService->schedule(
            $request,
            now()->addHour(),
            'manual',
            'https://meet.example.test/notary-session-123'
        );
        $request->refresh();
        $this->assertSame(NotaryRequestStatus::SessionScheduled, $request->status);
        $this->assertSame('scheduled', $session->status);

        // Step 5: Live Video Verification
        $session = $schedulingService->start($session);
        $request->refresh();
        $this->assertSame(NotaryRequestStatus::SessionInProgress, $request->status);
        $this->assertSame('in_progress', $session->status);

        $session = $schedulingService->complete($session, [
            'face_matches_id' => true,
            'id_valid_not_expired' => true,
            'signer_conscious_aware' => true,
            'signer_agrees_voluntarily' => true,
            'signer_in_philippines' => true,
            'id_shown_on_camera' => true,
        ]);
        $request->refresh();
        $this->assertSame(NotaryRequestStatus::SessionCompleted, $request->status);
        $this->assertSame('completed', $session->status);
        $this->assertNotNull($session->verification_checklist);
        $this->assertTrue($session->verification_checklist['face_matches_id']);

        // Step 6: Notarial Register Entry
        $credential = NotaryCredential::factory()->for($notary)->create();
        $registerService = app(NotarialRegisterService::class);

        $entry = $registerService->createEntry($request, $credential, [
            'document_title' => 'Affidavit of Self-Adjudication',
            'document_description' => 'Transfer of property rights',
            'parties' => [
                ['name' => 'Alejandra Marie M. Sencio', 'address' => '123 Rizal St., Davao City, Davao del Sur'],
            ],
            'witnesses' => [],
            'competent_evidence' => [
                ['person_name' => 'Alejandra Marie M. Sencio', 'id_type' => 'Passport', 'id_number' => 'P1030912OB'],
            ],
            'notarial_act_type' => 'acknowledgment',
            'fees' => 500.00,
            'official_receipt_number' => 'CR: 0001234',
        ]);

        $this->assertInstanceOf(NotarialRegisterEntry::class, $entry);
        $this->assertSame(1, $entry->entry_number);
        $this->assertSame((int) now()->format('Y'), $entry->entry_year);
        $this->assertSame('acknowledgment', $entry->notarial_act_type);
        $this->assertSame(500.00, (float) $entry->fees);
        $this->assertNotNull($entry->qr_verification_token);
        $this->assertNotNull($entry->notarized_at);

        // Attorney approval
        $workflowService->approve($request->fresh(), [
            'identity_matched' => true,
            'voluntary_consent' => true,
            'jurisdiction_valid' => true,
        ]);
        $request->refresh();
        $this->assertSame(NotaryRequestStatus::AttorneyApproved, $request->status);

        // Verify journal entry was created
        $this->assertDatabaseHas('notary_journals', [
            'notary_request_id' => $request->id,
            'entry_type' => 'register_entry_created',
        ]);
    }

    public function test_identity_verification_requires_submitted_status(): void
    {
        $requester = User::factory()->create();
        $request = NotaryRequest::factory()->for($requester)->create([
            'status' => NotaryRequestStatus::Draft,
        ]);

        $this->expectException(\RuntimeException::class);

        app(IdentityVerificationService::class)->verify($request, [
            'id_document_type' => 'passport',
            'id_document_number' => 'P123',
            'id_document_path' => 'identity/test.pdf',
        ]);
    }

    public function test_location_verification_rejects_non_philippines_location(): void
    {
        $requester = User::factory()->create();
        $request = NotaryRequest::factory()->for($requester)->create([
            'status' => NotaryRequestStatus::Submitted,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Philippines');

        app(LocationVerificationService::class)->markVerified($request, [
            'country_code' => 'US',
            'vpn_detected' => false,
        ]);
    }

    public function test_location_verification_rejects_vpn_usage(): void
    {
        $requester = User::factory()->create();
        $request = NotaryRequest::factory()->for($requester)->create([
            'status' => NotaryRequestStatus::Submitted,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('VPN');

        app(LocationVerificationService::class)->markVerified($request, [
            'country_code' => 'PH',
            'vpn_detected' => true,
        ]);
    }

    public function test_reject_cannot_reject_notarized_request(): void
    {
        $requester = User::factory()->create();
        $request = NotaryRequest::factory()->for($requester)->create([
            'status' => NotaryRequestStatus::Notarized,
        ]);

        $this->expectException(\RuntimeException::class);

        app(NotaryRequestWorkflowService::class)->reject($request, 'Too late');
    }

    public function test_notary_credential_tracks_commission_expiry(): void
    {
        $notary = User::factory()->create(['role' => UserRole::Notary]);

        $activeCredential = NotaryCredential::factory()->for($notary)->create([
            'commission_expires_at' => now()->addYear(),
            'status' => 'active',
        ]);

        $expiredCredential = NotaryCredential::factory()->for($notary)->create([
            'commission_expires_at' => now()->subMonth(),
            'status' => 'expired',
        ]);

        $this->assertTrue($activeCredential->isActive());
        $this->assertFalse($activeCredential->isExpired());
        $this->assertFalse($expiredCredential->isActive());
        $this->assertTrue($expiredCredential->isExpired());
    }

    public function test_notarial_register_entry_auto_increments_per_credential_per_year(): void
    {
        $notary = User::factory()->create(['role' => UserRole::Notary]);
        $credential = NotaryCredential::factory()->for($notary)->create();
        $requester = User::factory()->create();
        $request = NotaryRequest::factory()->for($requester)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneyApproved,
        ]);

        $registerService = app(NotarialRegisterService::class);

        $entry1 = $registerService->createEntry($request, $credential, [
            'document_title' => 'First Document',
            'parties' => [['name' => 'Party A', 'address' => 'Address A']],
            'competent_evidence' => [['person_name' => 'Party A', 'id_type' => 'Passport', 'id_number' => 'P1']],
            'notarial_act_type' => 'acknowledgment',
        ]);

        $entry2 = $registerService->createEntry($request, $credential, [
            'document_title' => 'Second Document',
            'parties' => [['name' => 'Party B', 'address' => 'Address B']],
            'competent_evidence' => [['person_name' => 'Party B', 'id_type' => 'PhilID', 'id_number' => 'PH2']],
            'notarial_act_type' => 'jurat',
        ]);

        $this->assertSame(1, $entry1->entry_number);
        $this->assertSame(2, $entry2->entry_number);
    }

    public function test_notarial_register_rejects_expired_credential(): void
    {
        $notary = User::factory()->create(['role' => UserRole::Notary]);
        $credential = NotaryCredential::factory()->expired()->for($notary)->create();
        $requester = User::factory()->create();
        $request = NotaryRequest::factory()->for($requester)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneyApproved,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('expired');

        app(NotarialRegisterService::class)->createEntry($request, $credential, [
            'document_title' => 'Test',
            'parties' => [['name' => 'Party', 'address' => 'Addr']],
            'competent_evidence' => [['person_name' => 'Party', 'id_type' => 'ID', 'id_number' => '123']],
            'notarial_act_type' => 'acknowledgment',
        ]);
    }

    public function test_verification_portal_returns_entry_by_token(): void
    {
        $notary = User::factory()->create(['role' => UserRole::Notary]);
        $credential = NotaryCredential::factory()->for($notary)->create();
        $requester = User::factory()->create();
        $request = NotaryRequest::factory()->for($requester)->create([
            'notary_user_id' => $notary->id,
        ]);

        $entry = NotarialRegisterEntry::factory()->create([
            'notary_request_id' => $request->id,
            'notary_credential_id' => $credential->id,
            'qr_verification_token' => 'test-token-123',
        ]);

        $registerService = app(NotarialRegisterService::class);
        $found = $registerService->findByVerificationToken('test-token-123');

        $this->assertNotNull($found);
        $this->assertSame($entry->id, $found->id);
    }

    public function test_mark_failed_transitions_request_to_failed_state(): void
    {
        $requester = User::factory()->create();
        $request = NotaryRequest::factory()->for($requester)->create([
            'status' => NotaryRequestStatus::Submitted,
        ]);

        $request->markFailed('Signer did not appear for session');
        $request->refresh();

        $this->assertSame(NotaryRequestStatus::Failed, $request->status);
        $this->assertSame('Signer did not appear for session', $request->metadata['failure_reason']);
    }

    public function test_session_confirmation_records_signer_acceptance(): void
    {
        $requester = User::factory()->create();
        $notary = User::factory()->for($requester->organization)->create(['role' => UserRole::Notary]);
        $request = NotaryRequest::factory()->for($requester)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::LocationVerified,
        ]);

        $document = Document::factory()->for($requester)->create([
            'notary_request_id' => $request->id,
            'status' => \App\Enums\DocumentStatus::Completed,
        ]);
        DocumentSigner::factory()->for($document)->create([
            'status' => \App\Enums\DocumentSignerStatus::Signed,
            'signing_order' => 1,
        ]);

        $schedulingService = app(NotarySchedulingService::class);
        $session = $schedulingService->schedule($request, now()->addHour(), 'manual');

        $session = $schedulingService->confirmSession($session);

        $this->assertTrue($session->signer_confirmed);
        $this->assertNotNull($session->signer_confirmed_at);
        $this->assertDatabaseHas('notary_journals', [
            'notary_request_id' => $request->id,
            'entry_type' => 'session_confirmed',
        ]);
    }

    public function test_digitalization_requires_completed_documents_and_never_force_completes_them(): void
    {
        $requester = User::factory()->create();
        $notary = User::factory()->for($requester->organization)->create(['role' => UserRole::Notary]);
        $request = NotaryRequest::factory()->for($requester)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::SessionCompleted,
        ]);

        $document = Document::factory()->for($requester)->create([
            'notary_request_id' => $request->id,
            'status' => \App\Enums\DocumentStatus::Pending,
        ]);
        DocumentSigner::factory()->for($document)->create([
            'status' => \App\Enums\DocumentSignerStatus::Signed,
            'signing_order' => 1,
        ]);
        DocumentSigner::factory()->for($document)->create([
            'user_id' => $notary->id,
            'email' => $notary->email,
            'status' => \App\Enums\DocumentSignerStatus::Signed,
            'signing_order' => 999,
        ]);

        $credential = NotaryCredential::factory()->for($notary)->create();
        NotarialRegisterEntry::factory()->create([
            'notary_request_id' => $request->id,
            'notary_credential_id' => $credential->id,
            'document_id' => $document->id,
        ]);

        $this->expectException(\RuntimeException::class);

        try {
            app(NotaryRequestWorkflowService::class)->digitalize($request);
        } finally {
            $this->assertSame(\App\Enums\DocumentStatus::Pending, $document->fresh()->status);
        }
    }

    public function test_geolocation_service_detects_private_ips(): void
    {
        $geoService = app(GeolocationService::class);

        $result = $geoService->resolveFromIp('192.168.1.1');
        $this->assertNull($result['country_code']);

        $result = $geoService->resolveFromIp('127.0.0.1');
        $this->assertNull($result['country_code']);
    }
}
