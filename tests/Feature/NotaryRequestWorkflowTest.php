<?php

namespace Tests\Feature;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Enums\NotaryRequestStatus;
use App\Enums\PaymentStatus;
use App\Enums\TemplateRoleType;
use App\Models\AttorneyNotarialRegistry;
use App\Models\Document;
use App\Models\DocumentHash;
use App\Models\DocumentSigner;
use App\Models\NotarialRegisterEntry;
use App\Models\NotaryCredential;
use App\Models\NotaryRequest;
use App\Models\Payment;
use App\Models\User;
use App\Services\NotaryRequestWorkflowService;
use App\Services\NotarySchedulingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotaryRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_notary_request_inherits_requester_organization(): void
    {
        $requester = User::factory()->create();

        $request = NotaryRequest::factory()->for($requester)->create();

        $this->assertSame($requester->organization_id, $request->organization_id);
    }

    public function test_notary_request_can_be_scheduled_approved_and_finalized(): void
    {
        config(['docutrust.notary.auto_invite_signers_to_video' => false]);

        $requester = User::factory()->create();
        $notary = User::factory()->create();

        $request = NotaryRequest::factory()->for($requester)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::LocationVerified,
        ]);

        $document = Document::factory()->for($requester)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Completed,
            'final_pdf_path' => 'documents/finalized.pdf',
            'certificate_path' => 'certificates/finalized.pdf',
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
        DocumentHash::query()->create([
            'document_id' => $document->id,
            'hash' => hash('sha256', 'notary-finalization'),
            'transaction_id' => '0xnotaryfinal',
            'created_at' => now(),
        ]);

        $schedulingService = app(NotarySchedulingService::class);
        $workflowService = app(NotaryRequestWorkflowService::class);

        $session = $schedulingService->schedule(
            $request,
            now()->addHour(),
            'manual',
            'https://example.test/notary-session'
        );

        $this->assertSame('scheduled', $session->status);
        $this->assertSame(NotaryRequestStatus::SessionScheduled, $request->fresh()->status);

        $session = $schedulingService->start($session);
        $this->assertSame(NotaryRequestStatus::SessionInProgress, $request->fresh()->status);

        $session = $schedulingService->complete($session, [
            'face_matches_id' => true,
            'id_valid_not_expired' => true,
            'signer_conscious_aware' => true,
            'signer_agrees_voluntarily' => true,
            'signer_in_philippines' => true,
            'id_shown_on_camera' => true,
        ]);
        $this->assertSame('completed', $session->status);
        $this->assertSame(NotaryRequestStatus::SessionCompleted, $request->fresh()->status);

        $workflowService->beginAttorneySigning($request->fresh());
        $this->assertSame(NotaryRequestStatus::AttorneySigning, $request->fresh()->status);

        $credential = NotaryCredential::factory()->for($notary)->create([
            'seal_image_path' => 'seals/workflow-seal.png',
        ]);

        NotarialRegisterEntry::factory()->create([
            'notary_request_id' => $request->id,
            'notary_credential_id' => $credential->id,
            'document_id' => $document->id,
            'fees' => 500.00,
        ]);

        Payment::query()->create([
            'organization_id' => $request->organization_id,
            'notary_request_id' => $request->id,
            'payer_user_id' => $requester->id,
            'provider' => 'gatewayhub',
            'provider_payment_id' => 'payment-workflow-1',
            'provider_transaction_id' => 'payment-workflow-1',
            'gateway' => 'gcash',
            'reference' => 'WORKFLOW-REQ-'.$request->id,
            'amount' => 500.00,
            'currency' => 'PHP',
            'status' => PaymentStatus::Paid->value,
            'paid_at' => now(),
        ]);

        $workflowService->approve($request->fresh(), [
            'identity_matched' => true,
            'voluntary_consent' => true,
            'jurisdiction_valid' => true,
        ]);

        $this->assertSame(NotaryRequestStatus::AttorneyApproved, $request->fresh()->status);
        $this->assertDatabaseHas('notary_journals', [
            'notary_request_id' => $request->id,
            'entry_type' => 'approval',
        ]);

        $workflowService->digitalize($request->fresh());

        $this->assertSame(NotaryRequestStatus::Digitalized, $request->fresh()->status);

        $workflowService->finalize($request->fresh());

        $this->assertSame(DocumentStatus::Completed, $document->fresh()->status);
        $this->assertSame(NotaryRequestStatus::Notarized, $request->fresh()->status);
        $this->assertNotNull($request->fresh()->completed_at);
    }

    public function test_notary_request_cannot_finalize_when_document_artifacts_are_missing(): void
    {
        $requester = User::factory()->create();
        $notary = User::factory()->create();

        $request = NotaryRequest::factory()->for($requester)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::Digitalized,
        ]);

        Document::factory()->for($requester)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Completed,
            'final_pdf_path' => null,
            'certificate_path' => null,
        ]);

        $workflowService = app(NotaryRequestWorkflowService::class);
        $readiness = $workflowService->finalizationReadiness($request);

        $this->assertFalse($readiness['ready']);
        $this->assertNotEmpty($readiness['issues']);

        $this->expectException(\RuntimeException::class);
        $workflowService->finalize($request);
    }

    public function test_notary_request_cannot_be_approved_before_session_completion_and_register_entry(): void
    {
        $requester = User::factory()->create();
        $notary = User::factory()->create();

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
        DocumentSigner::factory()->for($document)->create([
            'user_id' => $notary->id,
            'email' => $notary->email,
            'status' => DocumentSignerStatus::Signed,
            'signing_order' => 999,
        ]);

        $this->expectException(\RuntimeException::class);

        app(NotaryRequestWorkflowService::class)->approve($request);
    }

    public function test_notary_request_cannot_be_approved_until_client_payment_is_completed(): void
    {
        config(['docutrust.notary.auto_invite_signers_to_video' => false]);

        $requester = User::factory()->create();
        $notary = User::factory()->create();

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
        DocumentSigner::factory()->for($document)->create([
            'user_id' => $notary->id,
            'email' => $notary->email,
            'status' => DocumentSignerStatus::Signed,
            'signing_order' => 999,
        ]);

        $session = app(NotarySchedulingService::class)->schedule(
            $request,
            now()->addHour(),
            'manual',
            'https://example.test/notary-session'
        );

        app(NotarySchedulingService::class)->start($session);
        app(NotarySchedulingService::class)->complete($session, [
            'face_matches_id' => true,
            'id_valid_not_expired' => true,
            'signer_conscious_aware' => true,
            'signer_agrees_voluntarily' => true,
            'signer_in_philippines' => true,
            'id_shown_on_camera' => true,
        ]);

        $credential = NotaryCredential::factory()->for($notary)->create();
        NotarialRegisterEntry::factory()->create([
            'notary_request_id' => $request->id,
            'notary_credential_id' => $credential->id,
            'document_id' => $document->id,
            'fees' => 500.00,
        ]);

        $this->assertFalse(app(NotaryRequestWorkflowService::class)->canApprove($request->fresh()));

        $this->expectException(\RuntimeException::class);

        app(NotaryRequestWorkflowService::class)->approve($request->fresh());
    }

    public function test_notary_request_cannot_finalize_before_digitalization(): void
    {
        $requester = User::factory()->create();
        $notary = User::factory()->create();

        $request = NotaryRequest::factory()->for($requester)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneyApproved,
        ]);

        $this->expectException(\RuntimeException::class);

        app(NotaryRequestWorkflowService::class)->finalize($request);
    }

    public function test_identity_verification_predicate_stays_open_for_late_identity_completion_states(): void
    {
        $requester = User::factory()->create();
        $request = NotaryRequest::factory()->for($requester)->create([
            'status' => NotaryRequestStatus::IdentityReviewRequired,
            'identity_verified_at' => null,
        ]);

        $this->assertTrue(app(NotaryRequestWorkflowService::class)->canVerifyIdentity($request));
    }

    public function test_location_verification_predicate_closes_after_location_is_already_verified(): void
    {
        $requester = User::factory()->create();
        $request = NotaryRequest::factory()->for($requester)->create([
            'status' => NotaryRequestStatus::LocationVerified,
            'location_verified_at' => now(),
        ]);

        $this->assertFalse(app(NotaryRequestWorkflowService::class)->canVerifyLocation($request));
    }

    public function test_location_verification_predicate_stays_open_for_location_review_required(): void
    {
        $requester = User::factory()->create();
        $request = NotaryRequest::factory()->for($requester)->create([
            'status' => NotaryRequestStatus::LocationReviewRequired,
            'location_verified_at' => null,
        ]);

        $this->assertTrue(app(NotaryRequestWorkflowService::class)->canVerifyLocation($request));
    }

    public function test_workflow_steps_for_draft_request_start_with_upload_and_send_current(): void
    {
        $requester = User::factory()->create();
        $request = NotaryRequest::factory()->for($requester)->create([
            'status' => NotaryRequestStatus::Draft,
        ]);

        $steps = app(NotaryRequestWorkflowService::class)->workflowSteps($request);

        $this->assertSame('Prepare the document', $steps[0]['label']);
        $this->assertSame('upcoming', $steps[0]['state']);
        $this->assertSame('upcoming', $steps[1]['state']);
    }

    public function test_workflow_steps_for_digitalized_request_mark_digital_notarization_complete(): void
    {
        $requester = User::factory()->create();
        $request = NotaryRequest::factory()->for($requester)->create([
            'status' => NotaryRequestStatus::Digitalized,
        ]);

        $steps = app(NotaryRequestWorkflowService::class)->workflowSteps($request);

        $this->assertCount(11, $steps);
        $this->assertSame('Apply seal and certificate', $steps[10]['label']);
        $this->assertSame('complete', $steps[10]['state']);
    }

    public function test_workflow_closing_steps_align_with_settlement_order(): void
    {
        $requester = User::factory()->create();
        $request = NotaryRequest::factory()->for($requester)->create([
            'status' => NotaryRequestStatus::AttorneySigning,
        ]);

        $workflow = app(NotaryRequestWorkflowService::class);
        $workflowSteps = $workflow->workflowSteps($request);
        $settlementSteps = $workflow->settlementSteps($request);

        $closingWorkflowLabels = array_column(array_slice($workflowSteps, 4), 'label');
        $settlementLabels = array_column($settlementSteps, 'label');

        $this->assertSame($settlementLabels, $closingWorkflowLabels);
    }

    public function test_attorney_milestone_steps_condense_sidebar_to_match_tabs(): void
    {
        $requester = User::factory()->create();
        $request = NotaryRequest::factory()->for($requester)->create([
            'status' => NotaryRequestStatus::SessionScheduled,
        ]);

        $milestones = app(NotaryRequestWorkflowService::class)->attorneyMilestoneSteps($request);

        $this->assertCount(4, $milestones);
        $this->assertSame('Prepare & collect signatures', $milestones[0]['label']);
        $this->assertSame('Verify client on video', $milestones[1]['label']);
        $this->assertSame('Sign as attorney', $milestones[2]['label']);
        $this->assertSame('Fees & register', $milestones[3]['label']);
        $this->assertSame('current', $milestones[1]['state']);
    }

    public function test_payment_step_stays_upcoming_during_video_when_fee_not_required(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::SessionScheduled,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Completed,
        ]);

        DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);

        $paymentStep = collect(app(NotaryRequestWorkflowService::class)->workflowSteps($request))
            ->firstWhere('label', 'Client pays the fee');

        $this->assertSame('upcoming', $paymentStep['state']);
    }

    public function test_settlement_fee_step_is_current_after_attorney_signing_without_fee(): void
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

        $feeStep = collect(app(NotaryRequestWorkflowService::class)->settlementSteps($request))
            ->firstWhere('key', 'settlement_fee');

        $this->assertSame('current', $feeStep['state']);
        $this->assertNull($feeStep['waiting_on']);
    }

    public function test_settlement_payment_step_waits_on_client_after_fee_is_saved(): void
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

        $paymentStep = collect(app(NotaryRequestWorkflowService::class)->settlementSteps($request))
            ->firstWhere('key', 'payment');

        $this->assertSame('current', $paymentStep['state']);
        $this->assertNull($paymentStep['waiting_on']);
    }

    public function test_settlement_registry_step_waits_on_client_before_payment(): void
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

        $registryStep = collect(app(NotaryRequestWorkflowService::class)->settlementSteps($request))
            ->firstWhere('key', 'registry_draft');

        $this->assertSame('blocked', $registryStep['state']);
        $this->assertSame('client', $registryStep['waiting_on']);
    }

    public function test_current_settlement_section_id_points_to_current_step(): void
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

        $workflow = app(NotaryRequestWorkflowService::class);

        $this->assertSame('section-settlement-fee', $workflow->currentSettlementSectionId($request));
    }

    public function test_can_create_register_entry_requires_attorney_signing_seal_and_payment(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneyApproved,
        ]);

        $workflow = app(NotaryRequestWorkflowService::class);

        $this->assertFalse($workflow->canCreateRegisterEntry($request));
    }

    public function test_can_create_register_entry_allows_case_when_prerequisites_are_met(): void
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
            'user_id' => $notary->id,
            'email' => $notary->email,
            'role_type' => TemplateRoleType::Signer,
            'status' => DocumentSignerStatus::Signed,
            'signing_order' => 999,
        ]);

        NotaryCredential::query()
            ->where('user_id', $notary->id)
            ->update(['seal_image_path' => 'seals/test-seal.png']);

        AttorneyNotarialRegistry::factory()->create([
            'notary_request_id' => $request->id,
            'fees' => 0,
            'registry_fields_completed_at' => now(),
        ]);

        $workflow = app(NotaryRequestWorkflowService::class);

        $this->assertTrue($workflow->canCreateRegisterEntry($request->fresh(['documents.documentSigners', 'attorneyNotarialRegistry'])));
    }

    public function test_settlement_due_amount_uses_registry_draft_before_register_entry(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
        ]);

        AttorneyNotarialRegistry::factory()->create([
            'notary_request_id' => $request->id,
            'fees' => 750.00,
        ]);

        $workflow = app(NotaryRequestWorkflowService::class);

        $this->assertSame(750.0, $workflow->settlementDueAmount($request->fresh()));
    }

    public function test_client_portal_timeline_marks_payment_current_when_fee_is_set(): void
    {
        $notary = User::factory()->notary()->create();
        $client = User::factory()->create();
        $request = NotaryRequest::factory()->for($client)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
        ]);

        AttorneyNotarialRegistry::factory()->create([
            'notary_request_id' => $request->id,
            'fees' => 500.00,
        ]);

        $workflow = app(NotaryRequestWorkflowService::class);
        $timeline = $workflow->clientPortalTimeline($request->fresh());

        $paymentStep = collect($timeline)->firstWhere('key', 'payment');

        $this->assertNotNull($paymentStep);
        $this->assertSame('current', $paymentStep['state']);
    }
}
