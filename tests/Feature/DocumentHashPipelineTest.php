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
}
