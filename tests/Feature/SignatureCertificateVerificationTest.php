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
use App\Services\CertificateVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SignatureCertificateVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const TINY_PNG_DATA_URL = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function test_certificate_verification_service_accepts_valid_pki_signature(): void
    {
        [$document, $signature, $documentHash] = $this->createSignedDocument();

        $result = app(CertificateVerificationService::class)
            ->verifyDocumentSignatures($document->fresh(['signatures.signerCertificate', 'signatures.signer']), $documentHash->hash);

        $this->assertSame('verified', $result['status']);
        $this->assertTrue($result['all_valid']);
        $this->assertSame(1, $result['verified_signatures']);
        $this->assertSame(0, $result['failed_signatures']);
        $this->assertSame('verified', $result['details'][0]['result']);
        $this->assertSame((string) $signature->signer?->name, $result['details'][0]['signer_name']);
    }

    public function test_certificate_verification_service_rejects_revoked_certificate(): void
    {
        [$document, , $documentHash] = $this->createSignedDocument();
        $certificate = $document->fresh(['signatures.signerCertificate'])
            ->signatures
            ->firstOrFail()
            ->signerCertificate;
        $certificate->update([
            'status' => 'revoked',
            'revoked_at' => now(),
            'revocation_reason' => 'Manual test revocation',
        ]);

        $result = app(CertificateVerificationService::class)
            ->verifyDocumentSignatures($document->fresh(['signatures.signerCertificate', 'signatures.signer']), $documentHash->hash);

        $this->assertSame('failed', $result['status']);
        $this->assertFalse($result['all_valid']);
        $this->assertSame(0, $result['verified_signatures']);
        $this->assertSame(1, $result['failed_signatures']);
        $this->assertSame('Certificate has been revoked. Reason: Manual test revocation', $result['details'][0]['reason']);
        $this->assertSame('Manual test revocation', $result['details'][0]['revocation_reason']);
    }

    public function test_certificate_verification_service_rejects_hash_mismatch(): void
    {
        [$document, $signature, $documentHash] = $this->createSignedDocument();

        $result = app(CertificateVerificationService::class)
            ->verifyDocumentSignatures($document->fresh(['signatures.signerCertificate', 'signatures.signer']), str_repeat('a', 64));

        $this->assertSame('failed', $result['status']);
        $this->assertFalse($result['all_valid']);
        $this->assertSame(0, $result['verified_signatures']);
        $this->assertSame(1, $result['failed_signatures']);
        $this->assertSame('Signature hash does not match document hash.', $result['details'][0]['reason']);
        $this->assertSame($documentHash->hash, $signature->signature_hash);
    }

    public function test_public_verification_page_shows_certificate_verification_summary(): void
    {
        [, , $documentHash] = $this->createSignedDocument();

        $this->get(route('verify.index').'?documentIdentifier='.$documentHash->hash)
            ->assertOk()
            ->assertSee('Certificate verification')
            ->assertSee('Verified')
            ->assertSee('Fingerprint:');
    }

    /**
     * @return array{Document, Signature, DocumentHash}
     */
    private function createSignedDocument(): array
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $path = 'documents/pki-verify.pdf';
        $contents = '%PDF-1.4 pki-verification-source';
        Storage::disk('local')->put($path, $contents);

        $document = Document::factory()->for($user)->create([
            'status' => DocumentStatus::Pending,
            'file_path' => $path,
        ]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'name' => 'PKI Signer',
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
            ->with(['signer', 'signerCertificate'])
            ->where('signature_field_id', $field->id)
            ->firstOrFail();

        $documentHash = DocumentHash::query()->create([
            'document_id' => $document->id,
            'hash' => (string) $signature->signature_hash,
            'transaction_id' => null,
            'created_at' => now(),
        ]);

        return [$document, $signature, $documentHash];
    }
}
