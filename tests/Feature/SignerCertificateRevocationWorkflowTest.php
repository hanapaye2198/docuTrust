<?php

namespace Tests\Feature;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Enums\SignatureFieldType;
use App\Models\Document;
use App\Models\DocumentHash;
use App\Models\DocumentSigner;
use App\Models\Signature;
use App\Models\SignatureField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt as LivewireVolt;
use Tests\TestCase;

class SignerCertificateRevocationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const TINY_PNG_DATA_URL = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function test_admin_can_revoke_signer_certificate_from_document_page(): void
    {
        [$owner, $document, $certificate] = $this->createSignedDocumentContext();

        $this->actingAs($owner);

        LivewireVolt::test('documents.show', ['document' => $document])
            ->set("revocationReasons.{$certificate->id}", 'Signer key compromised')
            ->call('revokeCertificate', $certificate->id)
            ->assertHasNoErrors();

        $certificate->refresh();

        $this->assertSame('revoked', $certificate->status);
        $this->assertNotNull($certificate->revoked_at);
        $this->assertSame('Signer key compromised', $certificate->revocation_reason);
    }

    public function test_notary_can_revoke_signer_certificate_from_notary_dashboard(): void
    {
        [$owner, $document, $certificate] = $this->createSignedDocumentContext();
        $notary = User::factory()->notary()->create([
            'organization_id' => $owner->organization_id,
            'organization_role' => $owner->organization_role,
        ]);

        $this->actingAs($notary);

        LivewireVolt::test('notary.dashboard')
            ->set("revocationReasons.{$certificate->id}", 'Identity mismatch during verification')
            ->call('revokeCertificate', $certificate->id)
            ->assertHasNoErrors();

        $certificate->refresh();

        $this->assertSame('revoked', $certificate->status);
        $this->assertNotNull($certificate->revoked_at);
        $this->assertSame('Identity mismatch during verification', $certificate->revocation_reason);
    }

    public function test_public_verification_page_shows_revocation_reason(): void
    {
        [, $document, $certificate] = $this->createSignedDocumentContext();

        $certificate->update([
            'status' => 'revoked',
            'revoked_at' => now(),
            'revocation_reason' => 'Manual compliance hold',
        ]);

        $documentHash = DocumentHash::query()
            ->where('document_id', $document->id)
            ->firstOrFail();

        $this->get(route('verify.index').'?documentIdentifier='.$documentHash->hash)
            ->assertOk()
            ->assertSee('Certificate status:')
            ->assertSee('Revocation reason:')
            ->assertSee('Manual compliance hold');
    }

    /**
     * @return array{User, Document, \App\Models\SignerCertificate}
     */
    private function createSignedDocumentContext(): array
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $path = 'documents/pki-revocation.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 pki-revocation-source');

        $document = Document::factory()->for($owner)->create([
            'status' => DocumentStatus::Pending,
            'file_path' => $path,
        ]);

        $signer = DocumentSigner::factory()->for($document)->create([
            'name' => 'Revocation Signer',
            'status' => DocumentSignerStatus::Pending,
        ]);

        $field = SignatureField::query()->create([
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

        $this->post(route('sign.signature.store', $signer), [
            'signature_field_id' => $field->id,
            'signature_image' => self::TINY_PNG_DATA_URL,
        ])->assertRedirect(route('sign.show', $signer->access_token));

        $signature = Signature::query()
            ->with('signerCertificate')
            ->where('signature_field_id', $field->id)
            ->firstOrFail();

        DocumentHash::query()->create([
            'document_id' => $document->id,
            'hash' => (string) $signature->signature_hash,
            'transaction_id' => null,
            'created_at' => now(),
        ]);

        return [$owner, $document, $signature->signerCertificate];
    }
}
