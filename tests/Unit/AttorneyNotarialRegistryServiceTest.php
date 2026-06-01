<?php

namespace Tests\Unit;

use App\Enums\DocumentSignerStatus;
use App\Enums\NotaryRequestStatus;
use App\Enums\TemplateRoleType;
use App\Models\AttorneyNotarialRegistry;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\NotaryRequest;
use App\Models\User;
use App\Services\AttorneyNotarialRegistryService;
use App\Services\NotaryRequestWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttorneyNotarialRegistryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_zero_fee_registry_draft_is_not_marked_as_payment_settled_without_a_paid_payment(): void
    {
        $notary = User::factory()->notary()->create();

        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
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
            'fees' => 0,
            'official_receipt_no' => null,
        ]);

        $state = app(AttorneyNotarialRegistryService::class)->draftStateForRequest($request->fresh(), $notary);

        $this->assertFalse($state['payment_settled']);
        $this->assertTrue($state['or_editable']);
        $this->assertFalse($state['fees_editable']);
    }

    public function test_zero_fee_registry_draft_does_not_advance_past_settlement_fee_step(): void
    {
        $notary = User::factory()->notary()->create();

        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
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
            'fees' => 0,
            'official_receipt_no' => null,
        ]);

        $workflow = app(NotaryRequestWorkflowService::class);
        $request = $request->fresh(['attorneyNotarialRegistry', 'payments', 'registerEntries', 'documents.documentSigners']);

        $this->assertFalse($workflow->hasSettlementFeeConfigured($request));
        $this->assertSame('section-settlement-fee', $workflow->currentSettlementSectionId($request));
    }
}
