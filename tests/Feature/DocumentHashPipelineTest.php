<?php

namespace Tests\Feature;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\User;
use App\Services\DocumentHashService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentHashPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_hash_is_created_when_document_becomes_completed(): void
    {
        Storage::fake('local');
        Http::fake([
            'http://127.0.0.1:3001/anchor' => Http::response([
                'transactionHash' => '0xabc123',
            ]),
        ]);

        $path = 'documents/completed-hash.pdf';
        $contents = '%PDF-1.4 document-content-for-hash';
        Storage::disk('local')->put($path, $contents);

        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create([
            'status' => DocumentStatus::Pending,
            'file_path' => $path,
        ]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
        ]);

        $this->post(route('sign.store', $signer->access_token))->assertRedirect();

        $document->refresh();
        $this->assertSame(DocumentStatus::Completed, $document->status);

        $this->assertDatabaseHas('document_hashes', [
            'document_id' => $document->id,
            'hash' => hash('sha256', $contents),
            'transaction_id' => '0xabc123',
        ]);
    }

    public function test_document_hash_generation_is_idempotent_for_same_document(): void
    {
        Storage::fake('local');
        Http::fake([
            'http://127.0.0.1:3001/anchor' => Http::response([
                'transactionHash' => '0xdef456',
            ]),
        ]);

        $path = 'documents/idempotent-hash.pdf';
        $contents = '%PDF-1.4 idempotent-content';
        Storage::disk('local')->put($path, $contents);

        $document = Document::factory()->for(User::factory())->create([
            'status' => DocumentStatus::Completed,
            'file_path' => $path,
        ]);

        $service = app(DocumentHashService::class);
        $service->createForCompletedDocument($document);
        $service->createForCompletedDocument($document);

        $this->assertSame(1, $document->documentHash()->count());
    }

    public function test_transaction_can_be_verified_on_blockchain(): void
    {
        Http::fake([
            'http://127.0.0.1:3001/verify' => Http::response([
                'exists' => true,
            ]),
        ]);

        $service = app(DocumentHashService::class);

        $this->assertTrue($service->transactionExistsOnBlockchain('0xproof123'));
    }

    public function test_stored_proof_can_be_verified_against_blockchain_hash_and_transaction(): void
    {
        Http::fake([
            'http://127.0.0.1:3001/verify' => Http::response([
                'exists' => true,
                'transactionMatches' => true,
                'blockNumber' => 123456,
                'proofTimestamp' => 1710000000,
                'submittedBy' => '0xabcDEF123',
            ]),
        ]);

        $document = Document::factory()->for(User::factory())->create([
            'status' => DocumentStatus::Completed,
        ]);

        $documentHash = $document->documentHash()->create([
            'hash' => hash('sha256', 'blockchain-proof'),
            'transaction_id' => '0xproof123',
            'created_at' => now(),
        ]);

        $result = app(DocumentHashService::class)->verifyStoredProof($documentHash);

        $this->assertSame('verified', $result['status']);
        $this->assertTrue($result['anchored']);
        $this->assertSame('0xproof123', $result['transaction_id']);
        $this->assertSame(123456, $result['block_number']);
        $this->assertSame('0xabcDEF123', $result['submitted_by']);
        $this->assertSame('2024-03-09 16:00:00', $result['anchored_at']);
    }

    public function test_stored_proof_fails_when_transaction_does_not_match_hash_record(): void
    {
        Http::fake([
            'http://127.0.0.1:3001/verify' => Http::response([
                'exists' => true,
                'transactionMatches' => false,
                'blockNumber' => 999,
                'proofTimestamp' => 1710000000,
                'submittedBy' => '0xabcDEF123',
            ]),
        ]);

        $document = Document::factory()->for(User::factory())->create([
            'status' => DocumentStatus::Completed,
        ]);

        $documentHash = $document->documentHash()->create([
            'hash' => hash('sha256', 'mismatched-proof'),
            'transaction_id' => '0xproof999',
            'created_at' => now(),
        ]);

        $result = app(DocumentHashService::class)->verifyStoredProof($documentHash);

        $this->assertSame('failed', $result['status']);
        $this->assertTrue($result['anchored']);
        $this->assertFalse($result['transaction_matches']);
        $this->assertSame('Document hash exists on-chain, but the stored transaction does not match the blockchain record.', $result['message']);
    }

    public function test_stored_proof_returns_failed_when_blockchain_service_is_unavailable(): void
    {
        Http::fake([
            'http://127.0.0.1:3001/verify' => Http::response([
                'message' => 'upstream unavailable',
            ], 503),
        ]);

        $document = Document::factory()->for(User::factory())->create([
            'status' => DocumentStatus::Completed,
        ]);

        $documentHash = $document->documentHash()->create([
            'hash' => hash('sha256', 'service-unavailable-proof'),
            'transaction_id' => '0xprooferror',
            'created_at' => now(),
        ]);

        $result = app(DocumentHashService::class)->verifyStoredProof($documentHash);

        $this->assertSame('failed', $result['status']);
        $this->assertFalse($result['anchored']);
        $this->assertSame('Unable to verify blockchain proof right now.', $result['message']);
    }
}
