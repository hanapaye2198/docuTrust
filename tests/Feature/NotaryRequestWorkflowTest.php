<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\DocumentSignerStatus;
use App\Enums\NotaryRequestStatus;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\DocumentHash;
use App\Models\NotarialRegisterEntry;
use App\Models\NotaryCredential;
use App\Models\NotaryRequest;
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

        $credential = NotaryCredential::factory()->for($notary)->create();
        NotarialRegisterEntry::factory()->create([
            'notary_request_id' => $request->id,
            'notary_credential_id' => $credential->id,
            'document_id' => $document->id,
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
}
