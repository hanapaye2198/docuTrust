<?php

namespace Tests\Feature;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentHash;
use App\Models\DocumentSigner;
use App\Models\Signature;
use App\Models\SignerCertificate;
use App\Models\SignatureAuditEvent;
use App\Models\User;
use App\Services\PkiSignatureService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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
        Http::fake([
            'http://127.0.0.1:3001/verify' => Http::response([
                'exists' => true,
                'transactionMatches' => true,
                'blockNumber' => 424242,
                'proofTimestamp' => 1710000000,
                'submittedBy' => '0xVerifier',
            ]),
        ]);

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
            'transaction_id' => '0xproof123',
            'created_at' => now(),
        ]);

        $this->get(route('verify.index').'?documentIdentifier='.$documentHash->hash)
            ->assertOk()
            ->assertSee('Valid')
            ->assertSee($documentHash->hash)
            ->assertSee('Jane Signer')
            ->assertSee('Blockchain verification')
            ->assertSee('Document hash is anchored on-chain and matches the recorded transaction.')
            ->assertSee('0xproof123')
            ->assertSee('Signing timeline');
    }

    public function test_document_is_verified_by_document_id_and_invalid_lookup_is_handled(): void
    {
        Http::fake([
            'http://127.0.0.1:3001/verify' => Http::response([
                'exists' => true,
                'transactionMatches' => true,
                'blockNumber' => 111,
                'proofTimestamp' => 1710000000,
                'submittedBy' => '0xVerifier',
            ]),
        ]);

        $owner = User::factory()->create();
        $document = Document::factory()->for($owner)->create([
            'status' => DocumentStatus::Completed,
        ]);
        DocumentHash::query()->create([
            'document_id' => $document->id,
            'hash' => hash('sha256', 'public-verify-id-test'),
            'transaction_id' => '0xproof999',
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

    public function test_public_verify_page_shows_blockchain_not_available_when_no_transaction_is_recorded(): void
    {
        $owner = User::factory()->create();
        $document = Document::factory()->for($owner)->create([
            'status' => DocumentStatus::Completed,
        ]);

        $documentHash = DocumentHash::query()->create([
            'document_id' => $document->id,
            'hash' => hash('sha256', 'public-verify-no-chain'),
            'transaction_id' => null,
            'created_at' => now(),
        ]);

        $this->get(route('verify.index').'?documentIdentifier='.$documentHash->hash)
            ->assertOk()
            ->assertSee('Blockchain verification')
            ->assertSee('Not available')
            ->assertSee('No blockchain transaction is recorded for this document.');
    }

    public function test_public_verify_page_shows_remote_signing_provider_evidence(): void
    {
        Storage::fake('local');

        $path = 'documents/public-remote-verify.pdf';
        Storage::disk('local')->put($path, Pdf::loadHTML('<h1>Remote verify</h1>')->output());

        $owner = User::factory()->create();
        $document = Document::factory()->for($owner)->create([
            'status' => DocumentStatus::Completed,
            'file_path' => $path,
            'final_pdf_path' => $path,
        ]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'name' => 'Remote Signer',
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);

        $chain = $this->makeProviderManagedCertificateChain();
        $hash = hash('sha256', Storage::disk('local')->get($path));
        $signatureValue = app(PkiSignatureService::class)->signHash($hash, $chain['private_key_pem']);
        $parsed = openssl_x509_parse($chain['certificate_pem']);

        $certificate = SignerCertificate::query()->create([
            'document_signer_id' => $signer->id,
            'certificate_authority_id' => null,
            'certificate_source' => 'provider_managed',
            'provider_name' => 'trust_service_provider',
            'provider_reference' => 'public-ref-001',
            'subject_dn' => app(\App\Services\CertificateAuthorityService::class)->distinguishedNameToString($parsed['subject'] ?? []),
            'issuer_dn' => app(\App\Services\CertificateAuthorityService::class)->distinguishedNameToString($parsed['issuer'] ?? []),
            'serial_number' => app(\App\Services\CertificateAuthorityService::class)->parsedSerialNumber($parsed),
            'public_key_pem' => $chain['public_key_pem'],
            'certificate_pem' => $chain['certificate_pem'],
            'issuer_certificate_pem' => $chain['issuer_certificate_pem'],
            'fingerprint_sha256' => app(\App\Services\CertificateAuthorityService::class)->certificateFingerprint($chain['certificate_pem']),
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
            'status' => 'active',
        ]);

        Signature::query()->create([
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'signer_certificate_id' => $certificate->id,
            'signature_value' => $signatureValue,
            'signature_hash' => $hash,
            'public_key_fingerprint' => app(PkiSignatureService::class)->fingerprint($chain['public_key_pem']),
            'signature_algorithm' => 'RSA-SHA256',
            'signing_provider' => 'trust_service_provider',
            'signing_provider_reference' => 'public-ref-001',
            'signing_provider_payload' => [
                'transaction_id' => 'txn-public-001',
                'authentication_method' => 'otp',
            ],
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
            'hash' => $hash,
            'transaction_id' => null,
            'created_at' => now(),
        ]);

        $this->get(route('verify.index').'?documentIdentifier='.$documentHash->hash)
            ->assertOk()
            ->assertSee('Signing provider:')
            ->assertSee('Trust service provider', false)
            ->assertSee('Provider reference:')
            ->assertSee('public-ref-001')
            ->assertSee('Provider evidence:')
            ->assertSee('Transaction Id:')
            ->assertSee('txn-public-001')
            ->assertSee('Authentication Method:')
            ->assertSee('otp');
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
        $issuerCert = openssl_csr_sign($issuerCsr, null, $issuerKey, 3650, $config, 8101);
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
            'commonName' => 'Public Remote Signer',
            'emailAddress' => 'public-remote@example.test',
            'organizationName' => 'DocuTrust',
            'organizationalUnitName' => 'Signer',
            'countryName' => 'PH',
        ];
        $signerCsr = openssl_csr_new($signerDn, $signerKey, $signerConfig);
        $issuerCertResource = openssl_x509_read($issuerCertificatePem);
        $signerCert = openssl_csr_sign($signerCsr, $issuerCertResource, $issuerKey, 825, $signerConfig, 8102);
        openssl_x509_export($signerCert, $signerCertificatePem);

        return [
            'certificate_pem' => $signerCertificatePem,
            'issuer_certificate_pem' => $issuerCertificatePem,
            'public_key_pem' => (string) ($signerDetails['key'] ?? ''),
            'private_key_pem' => $signerPrivateKeyPem,
        ];
    }
}
