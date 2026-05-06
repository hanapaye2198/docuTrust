<?php

namespace Tests\Feature;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Enums\SignatureFieldType;
use App\Jobs\GenerateCertificateJob;
use App\Jobs\GenerateDocumentPdfJob;
use App\Models\Document;
use App\Models\DocumentHash;
use App\Models\DocumentSigner;
use App\Models\Signature;
use App\Models\SignatureField;
use App\Models\User;
use App\Services\CertificateVerificationService;
use App\Services\PkiSignatureService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SignatureCertificateVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const TINY_PNG_DATA_URL = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private function putValidPdf(string $path): void
    {
        Storage::disk('local')->put($path, Pdf::loadHTML('<h1>PKI verification source</h1>')->output());
    }

    private function runQueuedCompletionWork(Document $document): void
    {
        $document->refresh();

        if ($document->status !== DocumentStatus::Completed) {
            return;
        }

        app()->call([new GenerateDocumentPdfJob($document->id, 'final'), 'handle']);
        app()->call([new GenerateCertificateJob($document->id), 'handle']);
    }

    /**
     * @return array{
     *   certificate_pem: string,
     *   issuer_certificate_pem: string,
     *   public_key_pem: string,
     *   private_key_pem: string
     * }
     */
    private function makeProviderManagedCertificateChain(): array
    {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'config' => (string) config('docutrust.pki.openssl_config_path'),
            'x509_extensions' => 'v3_ca',
        ];

        $issuerKey = openssl_pkey_new($config);
        openssl_pkey_export($issuerKey, $issuerPrivateKeyPem, null, $config);
        $issuerDn = [
            'commonName' => 'Provider Root',
            'organizationName' => 'DocuTrust Remote Provider',
            'organizationalUnitName' => 'Trust Service Provider',
            'countryName' => 'PH',
        ];
        $issuerCsr = openssl_csr_new($issuerDn, $issuerKey, $config);
        $issuerCert = openssl_csr_sign($issuerCsr, null, $issuerKey, 3650, $config, 8001);
        openssl_x509_export($issuerCert, $issuerCertificatePem);

        $signerConfig = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'config' => (string) config('docutrust.pki.openssl_config_path'),
            'x509_extensions' => 'usr_cert',
        ];
        $signerKey = openssl_pkey_new($signerConfig);
        openssl_pkey_export($signerKey, $signerPrivateKeyPem, null, $signerConfig);
        $signerDetails = openssl_pkey_get_details($signerKey);
        $signerDn = [
            'commonName' => 'Provider Managed Signer',
            'emailAddress' => 'provider-managed@example.test',
            'organizationName' => 'DocuTrust',
            'organizationalUnitName' => 'Signer',
            'countryName' => 'PH',
        ];
        $signerCsr = openssl_csr_new($signerDn, $signerKey, $signerConfig);
        $issuerCertResource = openssl_x509_read($issuerCertificatePem);
        $signerCert = openssl_csr_sign($signerCsr, $issuerCertResource, $issuerKey, 825, $signerConfig, 8002);
        openssl_x509_export($signerCert, $signerCertificatePem);

        return [
            'certificate_pem' => $signerCertificatePem,
            'issuer_certificate_pem' => $issuerCertificatePem,
            'public_key_pem' => (string) ($signerDetails['key'] ?? ''),
            'private_key_pem' => $signerPrivateKeyPem,
        ];
    }

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

    public function test_certificate_verification_service_rejects_certificate_with_mismatched_issuer(): void
    {
        [$document, , $documentHash] = $this->createSignedDocument();
        $certificate = $document->fresh(['signatures.signerCertificate'])
            ->signatures
            ->firstOrFail()
            ->signerCertificate;

        $certificate->update([
            'issuer_dn' => 'CN=Unexpected Issuer',
        ]);

        $result = app(CertificateVerificationService::class)
            ->verifyDocumentSignatures($document->fresh(['signatures.signerCertificate.certificateAuthority', 'signatures.signer']), $documentHash->hash);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('Stored signer certificate issuer does not match certificate contents.', $result['details'][0]['reason']);
    }

    public function test_certificate_verification_service_rejects_certificate_with_mismatched_public_key(): void
    {
        [$document, , $documentHash] = $this->createSignedDocument();
        $certificate = $document->fresh(['signatures.signerCertificate'])
            ->signatures
            ->firstOrFail()
            ->signerCertificate;

        $certificate->update([
            'public_key_pem' => "-----BEGIN PUBLIC KEY-----\ninvalid\n-----END PUBLIC KEY-----",
        ]);

        $result = app(CertificateVerificationService::class)
            ->verifyDocumentSignatures($document->fresh(['signatures.signerCertificate.certificateAuthority', 'signatures.signer']), $documentHash->hash);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('Stored signer public key does not match the signer certificate public key.', $result['details'][0]['reason']);
    }

    public function test_certificate_verification_service_rejects_when_signature_fingerprint_does_not_match_certificate_key(): void
    {
        [$document, $signature, $documentHash] = $this->createSignedDocument();

        $signature->update([
            'public_key_fingerprint' => str_repeat('0', 64),
        ]);

        $result = app(CertificateVerificationService::class)
            ->verifyDocumentSignatures($document->fresh(['signatures.signerCertificate.certificateAuthority', 'signatures.signer']), $documentHash->hash);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('Stored public key fingerprint does not match certificate public key.', $result['details'][0]['reason']);
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

    public function test_certificate_verification_service_accepts_provider_managed_signature(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $path = 'documents/provider-managed-verify.pdf';
        $this->putValidPdf($path);

        $document = Document::factory()->for($user)->create([
            'status' => DocumentStatus::Completed,
            'file_path' => $path,
            'final_pdf_path' => $path,
        ]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'name' => 'Provider Managed Signer',
            'status' => DocumentSignerStatus::Signed,
        ]);

        $hash = hash('sha256', Storage::disk('local')->get($path));
        $chain = $this->makeProviderManagedCertificateChain();
        $signatureValue = app(PkiSignatureService::class)->signHash($hash, $chain['private_key_pem']);
        $certificateParsed = openssl_x509_parse($chain['certificate_pem']);

        $certificate = $signer->signerCertificates()->create([
            'certificate_authority_id' => null,
            'certificate_source' => 'provider_managed',
            'provider_name' => 'remote_managed',
            'provider_reference' => 'provider-ref-verify-001',
            'subject_dn' => app(\App\Services\CertificateAuthorityService::class)->distinguishedNameToString($certificateParsed['subject'] ?? []),
            'issuer_dn' => app(\App\Services\CertificateAuthorityService::class)->distinguishedNameToString($certificateParsed['issuer'] ?? []),
            'serial_number' => app(\App\Services\CertificateAuthorityService::class)->parsedSerialNumber($certificateParsed),
            'public_key_pem' => $chain['public_key_pem'],
            'certificate_pem' => $chain['certificate_pem'],
            'issuer_certificate_pem' => $chain['issuer_certificate_pem'],
            'fingerprint_sha256' => app(\App\Services\CertificateAuthorityService::class)->certificateFingerprint($chain['certificate_pem']),
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
            'status' => 'active',
        ]);

        $document->signatures()->create([
            'signer_id' => $signer->id,
            'signer_certificate_id' => $certificate->id,
            'signature_value' => $signatureValue,
            'signature_hash' => $hash,
            'public_key_fingerprint' => app(PkiSignatureService::class)->fingerprint($chain['public_key_pem']),
            'signature_algorithm' => 'RSA-SHA256',
            'signing_provider' => 'remote_managed',
            'signing_provider_reference' => 'provider-ref-verify-001',
        ]);

        $result = app(CertificateVerificationService::class)
            ->verifyDocumentSignatures($document->fresh(['signatures.signerCertificate.certificateAuthority', 'signatures.signer']), $hash);

        $this->assertSame('verified', $result['status']);
        $this->assertTrue($result['all_valid']);
        $this->assertSame('verified', $result['details'][0]['result']);
    }

    /**
     * @return array{Document, Signature, DocumentHash}
     */
    private function createSignedDocument(): array
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $path = 'documents/pki-verify.pdf';
        $this->putValidPdf($path);

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

        $this->runQueuedCompletionWork($document);

        $signature = Signature::query()
            ->with(['signer', 'signerCertificate'])
            ->where('signature_field_id', $field->id)
            ->firstOrFail();

        $documentHash = $document->fresh('documentHash')->documentHash;
        $this->assertNotNull($documentHash);

        return [$document, $signature, $documentHash];
    }
}
