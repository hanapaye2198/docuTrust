<?php

namespace Tests\Feature;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Enums\NotaryIdentityVerificationStatus;
use App\Enums\NotaryRequestStatus;
use App\Enums\PaymentStatus;
use App\Enums\TemplateRoleType;
use App\Enums\UserRole;
use App\Models\AttorneyNotarialRegistry;
use App\Models\Document;
use App\Models\DocumentHash;
use App\Models\DocumentSigner;
use App\Models\NotarialRegisterEntry;
use App\Models\NotaryCredential;
use App\Models\NotaryIdentityVerification;
use App\Models\NotaryRequest;
use App\Models\NotarySigner;
use App\Models\Payment;
use App\Models\User;
use App\Services\CompletedDocumentArtifactService;
use App\Services\DocumentArchiveService;
use App\Services\DocumentHashService;
use App\Services\GeolocationService;
use App\Services\IdentityVerificationService;
use App\Services\LocationVerificationService;
use App\Services\NotarialCertificateService;
use App\Services\NotarialRegisterService;
use App\Services\NotaryDigitalizationService;
use App\Services\NotaryRequestWorkflowService;
use App\Services\NotarySchedulingService;
use App\Services\NotarySealService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotaryEnotaryFlowTest extends TestCase
{
    use RefreshDatabase;

    private function seedRegisterEntryPrerequisites(
        NotaryRequest $request,
        User $notary,
        NotaryCredential $credential,
        float $fees = 0,
    ): void {
        if ($fees > 0) {
            AttorneyNotarialRegistry::factory()->create([
                'notary_request_id' => $request->id,
                'fees' => $fees,
                'registry_fields_completed_at' => now(),
            ]);

            Payment::query()->create([
                'organization_id' => $request->organization_id,
                'notary_request_id' => $request->id,
                'payer_user_id' => $request->user_id,
                'provider' => 'gatewayhub',
                'provider_payment_id' => 'payment-register-'.$request->id,
                'provider_transaction_id' => 'payment-register-'.$request->id,
                'gateway' => 'gcash',
                'reference' => 'REGISTER-REQ-'.$request->id,
                'amount' => $fees,
                'currency' => 'PHP',
                'status' => PaymentStatus::Paid->value,
                'paid_at' => now(),
            ]);
        } else {
            AttorneyNotarialRegistry::factory()->create([
                'notary_request_id' => $request->id,
                'fees' => 0,
                'registry_fields_completed_at' => now(),
            ]);
        }

        if ($credential->seal_image_path === null || $credential->seal_image_path === '') {
            $credential->forceFill(['seal_image_path' => 'seals/test-seal.png'])->save();
        }

        NotaryCredential::query()
            ->where('user_id', $notary->id)
            ->where('status', 'active')
            ->update(['seal_image_path' => $credential->fresh()->seal_image_path]);
    }

    public function test_full_enotary_8_step_flow(): void
    {
        config(['docutrust.notary.auto_invite_signers_to_video' => false]);

        // Step 1: Create Notary Request
        $requester = User::factory()->create();
        $notary = User::factory()->notaryWithoutCredential()->for($requester->organization)->create();

        $request = NotaryRequest::factory()->for($requester)->create([
            'notary_user_id' => $notary->id,
            'request_type' => 'acknowledgment',
            'title' => 'Affidavit of Self-Adjudication',
        ]);

        $this->assertSame(NotaryRequestStatus::Draft, $request->status);

        $document = Document::factory()->for($requester)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Completed,
        ]);
        DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Signed,
            'signing_order' => 1,
        ]);
        DocumentSigner::factory()->for($document)->create([
            'user_id' => $notary->id,
            'email' => $notary->email,
            'role_type' => TemplateRoleType::Signer,
            'status' => DocumentSignerStatus::Signed,
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

        $workflowService->beginAttorneySigning($request->fresh());
        $request->refresh();
        $this->assertSame(NotaryRequestStatus::AttorneySigning, $request->status);

        // Step 6: Notarial Register Entry
        $credential = NotaryCredential::factory()->for($notary)->create([
            'seal_image_path' => 'seals/enotary-flow-seal.png',
        ]);
        $this->seedRegisterEntryPrerequisites($request, $notary, $credential, 500.00);
        $registerService = app(NotarialRegisterService::class);

        $entry = $registerService->createEntry($request->fresh(), $credential, [
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

        $this->mock(NotarySealService::class, function ($mock) {
            $mock->shouldReceive('generateVerificationQrCode')->andReturnNull();
            $mock->shouldReceive('applyNotarySeal')->andReturn('documents/notarized/mock-final.pdf');
        });
        $this->mock(NotarialCertificateService::class, function ($mock) {
            $mock->shouldReceive('generate')->andReturnNull();
        });
        $this->mock(CompletedDocumentArtifactService::class, function ($mock) use ($document) {
            $mock->shouldReceive('ensureReady')->andReturn($document);
        });

        $workflowService->digitalize($request->fresh());
        $request->refresh();
        $this->assertSame(NotaryRequestStatus::Digitalized, $request->status);

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

    public function test_direct_identity_verification_can_complete_after_location_verification_without_regressing_status(): void
    {
        $requester = User::factory()->create();
        $request = NotaryRequest::factory()->for($requester)->create([
            'status' => NotaryRequestStatus::LocationVerified,
            'location_verified_at' => now(),
        ]);

        app(IdentityVerificationService::class)->verify($request, [
            'id_document_type' => 'passport',
            'id_document_number' => 'P555',
            'id_document_path' => 'identity/passport-late.pdf',
        ]);

        $request->refresh();

        $this->assertSame(NotaryRequestStatus::LocationVerified, $request->status);
        $this->assertNotNull($request->identity_verified_at);
        $this->assertSame('passport', $request->id_document_type);
    }

    public function test_pending_signer_identity_review_can_complete_after_location_verification_without_regressing_status(): void
    {
        $requester = User::factory()->create();
        $notary = User::factory()->for($requester->organization)->create([
            'role' => UserRole::Notary,
        ]);
        $request = NotaryRequest::factory()->for($requester)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::LocationVerified,
            'location_verified_at' => now(),
        ]);
        $signer = NotarySigner::factory()->for($request, 'notaryRequest')->create();
        $reviewer = User::factory()->for($requester->organization)->create();

        $record = NotaryIdentityVerification::query()->create([
            'notary_request_id' => $request->id,
            'notary_signer_id' => $signer->id,
            'id_type' => 'passport',
            'id_number' => 'P-LOC-1',
            'id_image_path' => 'identity/pending-passport.pdf',
            'selfie_image_path' => 'identity/pending-selfie.jpg',
            'verification_status' => NotaryIdentityVerificationStatus::Pending,
        ]);

        app(IdentityVerificationService::class)->approvePendingRecord($reviewer, $record);

        $request->refresh();
        $record->refresh();

        $this->assertSame(NotaryRequestStatus::LocationVerified, $request->status);
        $this->assertNotNull($request->identity_verified_at);
        $this->assertEquals(NotaryIdentityVerificationStatus::Verified, $record->verification_status);
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

    public function test_browser_geo_failure_requires_review_without_failing_request(): void
    {
        $requester = User::factory()->create();
        $request = NotaryRequest::factory()->for($requester)->create([
            'status' => NotaryRequestStatus::Submitted,
        ]);

        $this->partialMock(GeolocationService::class, function ($mock): void {
            $mock->shouldReceive('resolveFromIp')->once()->andReturn([
                'country_code' => 'US',
                'city' => 'Seattle',
                'latitude' => 47.6062,
                'longitude' => -122.3321,
                'is_vpn' => false,
                'is_proxy' => false,
            ]);
        });

        $result = app(LocationVerificationService::class)->evaluateBrowserLocation($request, null, [
            'latitude' => 47.6062,
            'longitude' => -122.3321,
        ]);

        $request->refresh();

        $this->assertFalse($result['success']);
        $this->assertSame(NotaryRequestStatus::LocationReviewRequired, $request->status);
        $this->assertSame('review_required', $request->metadata['location_verification']['result'] ?? null);
        $this->assertDatabaseHas('notary_journals', [
            'notary_request_id' => $request->id,
            'entry_type' => 'location_verification_review_required',
        ]);
    }

    public function test_manual_location_verification_can_still_succeed_after_browser_geo_failure(): void
    {
        $requester = User::factory()->create();
        $request = NotaryRequest::factory()->for($requester)->create([
            'status' => NotaryRequestStatus::Submitted,
        ]);

        $this->partialMock(GeolocationService::class, function ($mock): void {
            $mock->shouldReceive('resolveFromIp')->once()->andReturn([
                'country_code' => 'US',
                'city' => 'Seattle',
                'latitude' => 47.6062,
                'longitude' => -122.3321,
                'is_vpn' => false,
                'is_proxy' => false,
            ]);
        });

        app(LocationVerificationService::class)->evaluateBrowserLocation($request, null, [
            'latitude' => 47.6062,
            'longitude' => -122.3321,
        ]);

        app(LocationVerificationService::class)->markVerified($request->fresh(), [
            'country_code' => 'PH',
            'vpn_detected' => false,
            'ip_address' => '120.28.45.100',
            'source' => 'manual_review',
        ]);

        $request->refresh();

        $this->assertSame(NotaryRequestStatus::LocationVerified, $request->status);
        $this->assertSame('verified', $request->metadata['location_verification']['result'] ?? null);
    }

    public function test_location_verification_after_session_scheduling_does_not_regress_request_status(): void
    {
        $requester = User::factory()->create();
        $request = NotaryRequest::factory()->for($requester)->create([
            'status' => NotaryRequestStatus::SessionScheduled,
        ]);

        app(LocationVerificationService::class)->markVerified($request, [
            'country_code' => 'PH',
            'vpn_detected' => false,
            'ip_address' => '120.28.45.100',
            'source' => 'manual_review',
        ]);

        $request->refresh();

        $this->assertSame(NotaryRequestStatus::SessionScheduled, $request->status);
        $this->assertNotNull($request->location_verified_at);
        $this->assertSame('verified', $request->metadata['location_verification']['result'] ?? null);
    }

    public function test_identity_rejection_moves_request_to_identity_review_required(): void
    {
        $requester = User::factory()->create();
        $reviewer = User::factory()->create();
        $request = NotaryRequest::factory()->for($requester)->create([
            'status' => NotaryRequestStatus::Submitted,
        ]);
        $signer = NotarySigner::factory()->for($request, 'notaryRequest')->create();
        $record = NotaryIdentityVerification::query()->create([
            'notary_request_id' => $request->id,
            'notary_signer_id' => $signer->id,
            'id_type' => 'passport',
            'id_number' => 'REJECT-1',
            'id_image_path' => 'identity/reject.pdf',
            'verification_status' => NotaryIdentityVerificationStatus::Pending,
        ]);

        app(IdentityVerificationService::class)->rejectPendingRecord($reviewer, $record, 'ID image is unreadable');

        $request->refresh();
        $record->refresh();

        $this->assertSame(NotaryRequestStatus::IdentityReviewRequired, $request->status);
        $this->assertEquals(NotaryIdentityVerificationStatus::Rejected, $record->verification_status);
        $this->assertSame('ID image is unreadable', $request->metadata['identity_review_reason'] ?? null);
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

    public function test_notary_credential_expiring_today_remains_active_for_the_day(): void
    {
        $notary = User::factory()->create(['role' => UserRole::Notary]);

        $credential = NotaryCredential::factory()->for($notary)->create([
            'commission_expires_at' => now()->toDateString(),
            'status' => 'active',
        ]);

        $this->assertTrue($credential->isActive());
        $this->assertFalse($credential->isExpired());
    }

    public function test_notarial_register_entry_auto_increments_per_credential_per_year(): void
    {
        $notary = User::factory()->notaryWithoutCredential()->create(['role' => UserRole::Notary]);
        $credential = NotaryCredential::factory()->for($notary)->create([
            'seal_image_path' => 'seals/register-increment-seal.png',
        ]);
        $requester = User::factory()->create();
        $registerService = app(NotarialRegisterService::class);

        $requestOne = NotaryRequest::factory()->for($requester)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
        ]);
        $documentOne = Document::factory()->for($requester)->create([
            'notary_request_id' => $requestOne->id,
            'status' => DocumentStatus::Completed,
        ]);
        DocumentSigner::factory()->for($documentOne)->create([
            'user_id' => $notary->id,
            'email' => $notary->email,
            'role_type' => TemplateRoleType::Signer,
            'status' => DocumentSignerStatus::Signed,
            'signing_order' => 999,
        ]);
        $this->seedRegisterEntryPrerequisites($requestOne, $notary, $credential);

        $entry1 = $registerService->createEntry($requestOne->fresh(), $credential, [
            'document_title' => 'First Document',
            'parties' => [['name' => 'Party A', 'address' => 'Address A']],
            'competent_evidence' => [['person_name' => 'Party A', 'id_type' => 'Passport', 'id_number' => 'P1']],
            'notarial_act_type' => 'acknowledgment',
        ]);

        $requestTwo = NotaryRequest::factory()->for($requester)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
        ]);
        $documentTwo = Document::factory()->for($requester)->create([
            'notary_request_id' => $requestTwo->id,
            'status' => DocumentStatus::Completed,
        ]);
        DocumentSigner::factory()->for($documentTwo)->create([
            'user_id' => $notary->id,
            'email' => $notary->email,
            'role_type' => TemplateRoleType::Signer,
            'status' => DocumentSignerStatus::Signed,
            'signing_order' => 999,
        ]);
        $this->seedRegisterEntryPrerequisites($requestTwo, $notary, $credential);

        $entry2 = $registerService->createEntry($requestTwo->fresh(), $credential, [
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
        config(['docutrust.notary.auto_invite_signers_to_video' => false]);

        $requester = User::factory()->create();
        $notary = User::factory()->for($requester->organization)->create(['role' => UserRole::Notary]);
        $request = NotaryRequest::factory()->for($requester)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::LocationVerified,
        ]);

        $document = Document::factory()->for($requester)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Completed,
        ]);
        DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Signed,
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
            'status' => DocumentStatus::Pending,
        ]);
        DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Signed,
            'signing_order' => 1,
        ]);
        DocumentSigner::factory()->for($document)->create([
            'user_id' => $notary->id,
            'email' => $notary->email,
            'status' => DocumentSignerStatus::Signed,
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
            $this->assertSame(DocumentStatus::Pending, $document->fresh()->status);
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

    public function test_digitalization_seals_the_completed_signed_pdf_and_refreshes_stale_archive_path(): void
    {
        config()->set('filesystems.disks.archive_testing', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/archive-testing-enotary'),
            'throw' => false,
        ]);
        config()->set('filesystems.docutrust_archive_disk', 'archive_testing');

        Storage::fake('local');
        Storage::fake('archive_testing');

        $requester = User::factory()->create();
        $notary = User::factory()->for($requester->organization)->create([
            'role' => UserRole::Notary,
        ]);
        $credential = NotaryCredential::factory()->create([
            'user_id' => $notary->id,
            'status' => 'active',
            'seal_image_path' => 'notary/seals/seal.png',
        ]);

        $request = NotaryRequest::factory()->for($requester)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneyApproved,
        ]);

        $document = Document::factory()->for($requester)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Completed,
            'file_path' => 'documents/source.pdf',
            'prepared_pdf_path' => 'documents/prepared.pdf',
            'final_pdf_path' => null,
            'archive_storage_disk' => 'archive_testing',
            'archive_document_path' => 'archives/documents/stale-final.pdf',
            'archived_at' => now(),
        ]);

        Storage::disk('local')->put('documents/source.pdf', '%PDF-1.4 source');
        Storage::disk('local')->put('documents/prepared.pdf', '%PDF-1.4 prepared');
        Storage::disk('local')->put('documents/generated/final-before-seal.pdf', '%PDF-1.4 final before seal');
        Storage::disk('local')->put('documents/notarized/final-after-seal.pdf', '%PDF-1.4 final after seal');
        Storage::disk('local')->put('certificates/final-before-seal.pdf', '%PDF-1.4 certificate');
        Storage::disk('archive_testing')->put('archives/documents/stale-final.pdf', '%PDF-1.4 stale archived final');

        NotarialRegisterEntry::factory()->create([
            'notary_request_id' => $request->id,
            'notary_credential_id' => $credential->id,
            'document_id' => $document->id,
        ]);

        $sealSourcePath = null;

        $this->mock(NotarySealService::class, function ($mock) use (&$sealSourcePath) {
            $mock->shouldReceive('generateVerificationQrCode')->andReturnNull();
            $mock->shouldReceive('applyNotarySeal')->once()->andReturnUsing(function (string $sourcePath) use (&$sealSourcePath) {
                $sealSourcePath = $sourcePath;

                return 'documents/notarized/final-after-seal.pdf';
            });
        });
        $this->mock(NotarialCertificateService::class, function ($mock) {
            $mock->shouldReceive('generate')->andReturnNull();
        });
        $this->mock(CompletedDocumentArtifactService::class, function ($mock) {
            $mock->shouldReceive('ensureReady')->andReturnUsing(function (Document $document) {
                $document->forceFill([
                    'final_pdf_path' => 'documents/generated/final-before-seal.pdf',
                    'certificate_path' => 'certificates/final-before-seal.pdf',
                    'archive_storage_disk' => 'archive_testing',
                    'archive_document_path' => 'archives/documents/stale-final.pdf',
                    'archived_at' => now(),
                ])->save();

                return $document->fresh();
            });
        });
        $this->mock(DocumentHashService::class, function ($mock) use ($document) {
            $mock->shouldReceive('generateDocumentHash')
                ->once()
                ->with('documents/notarized/final-after-seal.pdf')
                ->andReturn('sealed-hash-'.$document->id);
            $mock->shouldReceive('createOrRefreshForCompletedDocument')
                ->once()
                ->andReturnUsing(function (Document $freshDocument, string $hash) {
                    return DocumentHash::query()->updateOrCreate(
                        ['document_id' => $freshDocument->id],
                        ['hash' => $hash, 'transaction_id' => 'tx-'.$freshDocument->id, 'created_at' => now()]
                    );
                });
        });

        app(NotaryDigitalizationService::class)->digitalize($request);

        $document->refresh();

        $this->assertSame('documents/generated/final-before-seal.pdf', $sealSourcePath);
        $this->assertSame('documents/notarized/final-after-seal.pdf', $document->final_pdf_path);
        $this->assertNotSame('archives/documents/stale-final.pdf', $document->archive_document_path);
        $this->assertNotNull($document->archive_document_path);
        $this->assertTrue(Storage::disk('archive_testing')->exists($document->archive_document_path));
        $this->assertDatabaseHas('document_hashes', [
            'document_id' => $document->id,
            'hash' => 'sealed-hash-'.$document->id,
            'transaction_id' => 'tx-'.$document->id,
        ]);
    }

    public function test_archive_service_prefers_current_final_pdf_when_archive_disk_matches_working_disk(): void
    {
        Storage::fake('local');

        $requester = User::factory()->create();
        $document = Document::factory()->for($requester)->create([
            'status' => DocumentStatus::Completed,
            'final_pdf_path' => 'documents/generated/current-final.pdf',
            'archive_storage_disk' => 'local',
            'archive_document_path' => 'documents/notarized/stale-final.pdf',
            'archived_at' => now(),
        ]);

        Storage::disk('local')->put('documents/generated/current-final.pdf', '%PDF-1.4 current final');
        Storage::disk('local')->put('documents/notarized/stale-final.pdf', '%PDF-1.4 stale final');

        $archived = app(DocumentArchiveService::class)->archiveCompletedDocument($document);

        $this->assertNotNull($archived);
        $this->assertSame('documents/generated/current-final.pdf', $archived->archive_document_path);
    }
}
