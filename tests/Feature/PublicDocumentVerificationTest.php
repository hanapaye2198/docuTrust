<?php

namespace Tests\Feature;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentHash;
use App\Models\DocumentSigner;
use App\Models\SignatureAuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicDocumentVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_access_public_verify_page(): void
    {
        $this->get(route('verify.index'))
            ->assertOk()
            ->assertSee('Verify document');
    }

    public function test_document_is_verified_by_hash(): void
    {
        $owner = User::factory()->create();
        $document = Document::factory()->for($owner)->create([
            'status' => DocumentStatus::Completed,
        ]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'name' => 'Jane Signer',
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);
        SignatureAuditEvent::query()->create([
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'action' => SignatureAuditEvent::ACTION_SIGNED,
            'ip_address' => '127.0.0.1',
        ]);
        SignatureAuditEvent::query()->create([
            'document_id' => $document->id,
            'signer_id' => null,
            'action' => SignatureAuditEvent::ACTION_COMPLETED,
            'ip_address' => '127.0.0.1',
        ]);
        $documentHash = DocumentHash::query()->create([
            'document_id' => $document->id,
            'hash' => hash('sha256', 'public-verify-test'),
            'transaction_id' => null,
            'created_at' => now(),
        ]);

        $this->get(route('verify.index').'?documentIdentifier='.$documentHash->hash)
            ->assertOk()
            ->assertSee('Valid')
            ->assertSee($documentHash->hash)
            ->assertSee('Jane Signer')
            ->assertSee('Signing timeline');
    }

    public function test_document_is_verified_by_document_id_and_invalid_lookup_is_handled(): void
    {
        $owner = User::factory()->create();
        $document = Document::factory()->for($owner)->create([
            'status' => DocumentStatus::Completed,
        ]);
        DocumentHash::query()->create([
            'document_id' => $document->id,
            'hash' => hash('sha256', 'public-verify-id-test'),
            'transaction_id' => null,
            'created_at' => now(),
        ]);

        $this->get(route('verify.index').'?documentIdentifier='.$document->id)
            ->assertOk()
            ->assertSee('Valid')
            ->assertSee((string) $document->id);

        $this->get(route('verify.index').'?documentIdentifier=not-a-real-hash')
            ->assertOk()
            ->assertSee('Invalid or unverified document');
    }
}
