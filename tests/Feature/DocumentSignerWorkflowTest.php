<?php

namespace Tests\Feature;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Enums\SignatureFieldType;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Livewire\DocumentSignersManager;
use App\Models\Contact;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\SignatureField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Livewire\Volt\Volt as LivewireVolt;
use Tests\TestCase;

class DocumentSignerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function putValidPdf(string $path): void
    {
        Storage::disk('local')->put($path, Pdf::loadHTML('<h1>DocuTrust</h1>')->output());
    }

    public function test_sign_page_loads_for_signer(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Pending]);
        $signer = DocumentSigner::factory()->for($document)->create(['status' => DocumentSignerStatus::Pending]);

        $this->get(route('sign.show', $signer->access_token))
            ->assertOk()
            ->assertSee($document->title);
    }

    public function test_signing_updates_signer_and_completes_document_when_only_one_signer(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Pending]);
        $signer = DocumentSigner::factory()->for($document)->create(['status' => DocumentSignerStatus::Pending]);

        $this->post(route('sign.store', $signer))
            ->assertRedirect(route('sign.show', $signer->access_token));

        $signer->refresh();
        $this->assertSame(DocumentSignerStatus::Signed, $signer->status);
        $this->assertNotNull($signer->signed_at);

        $document->refresh();
        $this->assertSame(DocumentStatus::Completed, $document->status);
    }

    public function test_multiple_signers_stays_pending_until_all_sign(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Pending]);
        $a = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
            'email' => 'a@example.com',
        ]);
        $b = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
            'email' => 'b@example.com',
        ]);

        $this->post(route('sign.store', $a))->assertRedirect();

        $document->refresh();
        $this->assertSame(DocumentStatus::Pending, $document->status);

        $this->post(route('sign.store', $b))->assertRedirect();

        $document->refresh();
        $this->assertSame(DocumentStatus::Completed, $document->status);
    }

    public function test_sign_is_forbidden_when_document_not_pending(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Draft]);
        $signer = DocumentSigner::factory()->for($document)->create();

        $this->post(route('sign.store', $signer))
            ->assertForbidden()
            ->assertSee('Link expired or invalid');
    }

    public function test_sequential_signer_is_blocked_until_previous_signer_completes(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Pending]);
        DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
            'signing_order' => 1,
        ]);
        $secondSigner = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
            'signing_order' => 2,
        ]);

        $this->post(route('sign.store', $secondSigner))
            ->assertRedirect(route('sign.show', $secondSigner->access_token))
            ->assertSessionHas('error', 'You cannot sign yet. Previous signer has not completed signing.');
    }

    public function test_sequential_signer_can_sign_after_previous_signer_completes(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Pending]);
        $firstSigner = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
            'signing_order' => 1,
        ]);
        $secondSigner = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
            'signing_order' => 2,
        ]);

        $this->post(route('sign.store', $firstSigner))->assertRedirect(route('sign.show', $firstSigner->access_token));
        $this->post(route('sign.store', $secondSigner))->assertRedirect(route('sign.show', $secondSigner->access_token));

        $secondSigner->refresh();
        $this->assertSame(DocumentSignerStatus::Signed, $secondSigner->status);
    }

    public function test_parallel_signers_can_sign_in_any_order(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Pending]);
        $firstSigner = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
            'signing_order' => null,
        ]);
        $secondSigner = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
            'signing_order' => null,
        ]);

        $this->post(route('sign.store', $secondSigner))->assertRedirect(route('sign.show', $secondSigner->access_token));
        $this->post(route('sign.store', $firstSigner))->assertRedirect(route('sign.show', $firstSigner->access_token));

        $firstSigner->refresh();
        $secondSigner->refresh();

        $this->assertSame(DocumentSignerStatus::Signed, $firstSigner->status);
        $this->assertSame(DocumentSignerStatus::Signed, $secondSigner->status);
    }

    public function test_duplicate_signing_is_blocked(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Pending]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);

        $this->post(route('sign.store', $signer))
            ->assertRedirect(route('sign.show', $signer->access_token))
            ->assertSessionHas('error', 'You have already signed this document.');
    }

    public function test_cancelled_and_declined_documents_cannot_be_signed(): void
    {
        $user = User::factory()->create();
        $cancelledDocument = Document::factory()->for($user)->create(['status' => DocumentStatus::Cancelled]);
        $cancelledSigner = DocumentSigner::factory()->for($cancelledDocument)->create();

        $declinedDocument = Document::factory()->for($user)->create(['status' => DocumentStatus::Declined]);
        $declinedSigner = DocumentSigner::factory()->for($declinedDocument)->create();

        $this->post(route('sign.store', $cancelledSigner))
            ->assertForbidden()
            ->assertSee('Link expired or invalid');

        $this->post(route('sign.store', $declinedSigner))
            ->assertForbidden()
            ->assertSee('Link expired or invalid');
    }

    public function test_owner_can_add_signer_via_livewire(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Draft]);

        $this->actingAs($user);

        Livewire::test(DocumentSignersManager::class, ['documentId' => $document->id])
            ->set('name', 'Jane Doe')
            ->set('email', 'jane@example.com')
            ->call('addSigner')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('document_signers', [
            'document_id' => $document->id,
            'email' => 'jane@example.com',
        ]);

        $this->assertDatabaseHas('contacts', [
            'user_id' => $user->id,
            'email' => 'jane@example.com',
            'name' => 'Jane Doe',
        ]);
    }

    public function test_owner_can_edit_signer_via_livewire(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Draft]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'signing_order' => 1,
        ]);

        $this->actingAs($user);

        Livewire::test(DocumentSignersManager::class, ['documentId' => $document->id])
            ->call('startEditingSigner', $signer->id)
            ->set('editingName', 'Jane Updated')
            ->set('editingEmail', 'updated@example.com')
            ->call('saveSignerEdits')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('document_signers', [
            'id' => $signer->id,
            'name' => 'Jane Updated',
            'email' => 'updated@example.com',
        ]);
    }

    public function test_owner_can_reorder_signers_via_livewire(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Draft]);
        $firstSigner = DocumentSigner::factory()->for($document)->create([
            'name' => 'First',
            'signing_order' => 1,
        ]);
        $secondSigner = DocumentSigner::factory()->for($document)->create([
            'name' => 'Second',
            'signing_order' => 2,
        ]);

        $this->actingAs($user);

        Livewire::test(DocumentSignersManager::class, ['documentId' => $document->id])
            ->call('moveSignerUp', $secondSigner->id)
            ->assertHasNoErrors();

        $firstSigner->refresh();
        $secondSigner->refresh();

        $this->assertSame(2, $firstSigner->signing_order);
        $this->assertSame(1, $secondSigner->signing_order);
    }

    public function test_adding_signer_does_not_duplicate_contact_when_email_already_exists(): void
    {
        $user = User::factory()->create();
        Contact::factory()->for($user)->create([
            'name' => 'Jane',
            'email' => 'jane@example.com',
        ]);
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Draft]);

        $this->actingAs($user);

        Livewire::test(DocumentSignersManager::class, ['documentId' => $document->id])
            ->set('name', 'Jane Doe')
            ->set('email', 'jane@example.com')
            ->call('addSigner')
            ->assertHasNoErrors();

        $this->assertSame(1, Contact::query()->where('user_id', $user->id)->where('email', 'jane@example.com')->count());
    }

    public function test_contact_suggestions_populate_while_typing_signer_name(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Draft]);
        Contact::factory()->for($user)->create([
            'name' => 'Alice Wonder',
            'email' => 'alice@example.com',
        ]);

        $this->actingAs($user);

        $component = Livewire::test(DocumentSignersManager::class, ['documentId' => $document->id])
            ->set('name', 'Ali');

        $this->assertCount(1, $component->get('contactSuggestions'));
    }

    public function test_owner_can_send_for_signature_from_document_page(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $path = 'documents/send-for-signature-source.pdf';
        $this->putValidPdf($path);
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Draft, 'file_path' => $path]);
        $signer = DocumentSigner::factory()->for($document)->create();
        SignatureField::query()->create([
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'type' => SignatureFieldType::Signature,
            'position_data' => [
                'x' => 0.1,
                'y' => 0.1,
                'width' => 0.2,
                'height' => 0.05,
            ],
        ]);

        $this->actingAs($user);

        LivewireVolt::test('documents.show', ['document' => $document])
            ->call('sendForSignature')
            ->assertHasNoErrors();

        $document->refresh();
        $this->assertSame(DocumentStatus::Pending, $document->status);
        $this->assertNotNull($document->sent_at);
        $this->assertNotNull($document->prepared_pdf_path);
        $this->assertTrue(Storage::disk('local')->exists($document->prepared_pdf_path));

        $signer->refresh();
        $this->assertNotNull($signer->access_token);
        $this->assertNotNull($signer->expires_at);
    }

    public function test_document_cannot_be_sent_when_any_signer_has_no_signature_fields(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Draft]);
        $firstSigner = DocumentSigner::factory()->for($document)->create(['name' => 'First Signer']);
        $secondSigner = DocumentSigner::factory()->for($document)->create(['name' => 'Second Signer']);
        $originalSecondSignerToken = $secondSigner->access_token;
        $originalSecondSignerExpiry = $secondSigner->expires_at?->toDateTimeString();
        SignatureField::query()->create([
            'document_id' => $document->id,
            'signer_id' => $firstSigner->id,
            'type' => SignatureFieldType::Signature,
            'position_data' => [
                'x' => 0.1,
                'y' => 0.1,
                'width' => 0.2,
                'height' => 0.05,
            ],
        ]);

        $this->actingAs($user);

        LivewireVolt::test('documents.show', ['document' => $document])
            ->call('sendForSignature')
            ->assertHasErrors(['send']);

        $document->refresh();
        $this->assertSame(DocumentStatus::Draft, $document->status);
        $secondSigner->refresh();
        $this->assertSame($originalSecondSignerToken, $secondSigner->access_token);
        $this->assertSame($originalSecondSignerExpiry, $secondSigner->expires_at?->toDateTimeString());
    }
}
