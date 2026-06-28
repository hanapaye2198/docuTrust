<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SignedDocumentsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_documents_page_only_shows_completed_documents(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $completedDocument = Document::factory()->for($user)->create([
            'title' => 'Fully Signed Agreement',
            'status' => DocumentStatus::Completed,
        ]);
        $completedDocument->documentHash()->create([
            'hash' => hash('sha256', 'completed-document'),
            'transaction_id' => '0xcompleted',
            'created_at' => now(),
        ]);
        Document::factory()->for($user)->create([
            'title' => 'Pending Agreement',
            'status' => DocumentStatus::Pending,
        ]);

        $this->actingAs($user)
            ->get(route('signed-documents.index'))
            ->assertOk()
            ->assertSee('Completed Documents')
            ->assertSee('Fully Signed Agreement')
            ->assertSee(hash('sha256', 'completed-document'))
            ->assertSee('0xcompleted')
            ->assertDontSee('Pending Agreement');
    }

    public function test_completed_document_detail_shows_verification_hash(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create([
            'title' => 'Signed Contract',
            'status' => DocumentStatus::Completed,
        ]);
        $document->documentHash()->create([
            'hash' => hash('sha256', 'signed-contract'),
            'transaction_id' => '0xsignedcontract',
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('documents.show', $document))
            ->assertOk()
            ->assertSee('Verification proof')
            ->assertSee(hash('sha256', 'signed-contract'))
            ->assertSee('0xsignedcontract');
    }
}
