<?php

namespace Tests\Feature;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentHash;
use App\Models\DocumentSigner;
use App\Models\Signature;
use App\Models\SignatureAuditEvent;
use App\Models\SignerCertificate;
use App\Models\User;
use App\Services\CertificateAuthorityService;
use App\Services\PkiSignatureService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
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

    public function test_public_verify_page_hides_restricted_audit_details(): void
    {
        Http::fake([
            'http://127.0.0.1:3001/verify' => Http::response([
                'exists' => true,
                'transactionMatches' => true,
                'blockNumber' => 999,
                'proofTimestamp' => 1710000000,
                'submittedBy' => '0xVerifier',
            ]),
        ]);

        $owner = User::factory()->create(['name' => 'Hidden Author']);
        $document = Document::factory()->for($owner)->create([
            'status' => DocumentStatus::Completed,
            'audit_enabled' => false,
            'audit_settings' => [
                'show_email' => false,
                'show_document_id' => false,
                'show_author' => false,
                'show_mobile' => false,
                'show_id_details' => false,
            ],
        ]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'name' => 'Private Signer',
            'email' => 'private@example.test',
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
            'hash' => hash('sha256', 'restricted-audit'),
            'transaction_id' => '0xrestricted',
            'created_at' => now(),
        ]);

        $this->get(route('verify.index').'?documentIdentifier='.$documentHash->hash)
            ->assertOk()
            ->assertSee('Audit trail')
            ->assertSee('restricted public verification record')
            ->assertDontSee('Signer list')
            ->assertDontSee('Signing timeline')
            ->assertDontSee('Document author:')
            ->assertDontSee('Email:');
    }

    public function test_public_verify_page_shows_configured_audit_metadata(): void
    {
        Http::fake([
            'http://127.0.0.1:3001/verify' => Http::response([
                'exists' => true,
                'transactionMatches' => true,
                'blockNumber' => 555,
                'proofTimestamp' => 1710000000,
                'submittedBy' => '0xVerifier',
            ]),
        ]);

        $owner = User::factory()->create(['name' => 'Visible Author']);
        $linkedSigner = User::factory()->signer()->organizationMember()->create([
            'organization_id' => $owner->organization_id,
            'email' => 'linked.audit@example.test',
            'mobile_number' => '+15551234567',
            'mobile_verified_at' => now(),
            'kyc_id_type' => 'passport',
            'kyc_verified_at' => now(),
        ]);
        $document = Document::factory()->for($owner)->create([
            'status' => DocumentStatus::Completed,
            'audit_enabled' => true,
            'audit_settings' => [
                'show_email' => true,
                'show_document_id' => true,
                'show_author' => true,
                'show_mobile' => true,
                'show_id_details' => true,
            ],
        ]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'name' => 'Visible Signer',
            'email' => 'linked.audit@example.test',
            'user_id' => $linkedSigner->id,
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
            'hash' => hash('sha256', 'visible-audit'),
            'transaction_id' => '0xvisible',
            'created_at' => now(),
        ]);

        $this->get(route('verify.index').'?documentIdentifier='.$documentHash->hash)
            ->assertOk()
            ->assertSee('Document ID:')
            ->assertSee((string) $document->id)
            ->assertSee('Document author:')
            ->assertSee('Visible Author')
            ->assertSee('Email:')
            ->assertSee('linked.audit@example.test')
            ->assertSee('Verified mobile:')
            ->assertSee('+15551234567')
            ->assertSee('Verified ID:')
            ->assertSee('Verified passport')
            ->assertSee('Signing timeline');
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
        $timestamp = $this->makeTrustedTimestampToken($hash);
        config()->set('services.remote_signing.csc.timestamp_openssl_binary', $this->resolveOpenSslBinary());
        config()->set('services.remote_signing.csc.timestamp_trust_cert_path', $timestamp['trust_cert_path']);
        $signatureValue = app(PkiSignatureService::class)->signHash($hash, $chain['private_key_pem']);
        $parsed = openssl_x509_parse($chain['certificate_pem']);

        $certificate = SignerCertificate::query()->create([
            'document_signer_id' => $signer->id,
            'certificate_authority_id' => null,
            'certificate_source' => 'provider_managed',
            'provider_name' => 'trust_service_provider',
            'provider_reference' => 'public-ref-001',
            'subject_dn' => app(CertificateAuthorityService::class)->distinguishedNameToString($parsed['subject'] ?? []),
            'issuer_dn' => app(CertificateAuthorityService::class)->distinguishedNameToString($parsed['issuer'] ?? []),
            'serial_number' => app(CertificateAuthorityService::class)->parsedSerialNumber($parsed),
            'public_key_pem' => $chain['public_key_pem'],
            'certificate_pem' => $chain['certificate_pem'],
            'issuer_certificate_pem' => $chain['issuer_certificate_pem'],
            'fingerprint_sha256' => app(CertificateAuthorityService::class)->certificateFingerprint($chain['certificate_pem']),
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
                'timestamp_token' => $timestamp['timestamp_token'],
                'timestamp_hash' => $hash,
                'timestamp_hash_algorithm' => '2.16.840.1.101.3.4.2.1',
                'timestamp_request_nonce' => $timestamp['nonce'],
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
            ->assertSee('otp')
            ->assertSee('Timestamp verification:')
            ->assertSee('RFC3161 timestamp token signature, trust chain, message imprint, and nonce were verified.');
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

    /**
     * @return array{timestamp_token: string, trust_cert_path: string, nonce: string}
     */
    private function makeTrustedTimestampToken(string $hash): array
    {
        $openssl = $this->resolveOpenSslBinary();
        if ($openssl === null) {
            self::markTestSkipped('OpenSSL CLI is required for RFC3161 timestamp verification tests.');
        }

        $directory = storage_path('app/testing/public-timestamps/'.Str::uuid()->toString());
        mkdir($directory, 0777, true);
        $directoryPosix = str_replace('\\', '/', $directory);

        $config = <<<CFG
[ req ]
distinguished_name = req_distinguished_name
x509_extensions = v3_tsa
prompt = no

[ req_distinguished_name ]
CN = Test TSA
O = DocuTrust
C = PH

[ v3_tsa ]
basicConstraints = critical,CA:FALSE
keyUsage = critical, digitalSignature, nonRepudiation
extendedKeyUsage = critical,timeStamping
subjectKeyIdentifier = hash
authorityKeyIdentifier = keyid,issuer

[ tsa ]
default_tsa = tsa_config1

[ tsa_config1 ]
signer_cert = {$directoryPosix}/tsa.crt
certs = {$directoryPosix}/tsa.crt
signer_key = {$directoryPosix}/tsa.key
signer_digest = sha256
digests = sha256
ess_cert_id_chain = no
ess_cert_id_alg = sha256
default_policy = 1.2.3.4.1
other_policies = 1.2.3.4.5.6
serial = {$directoryPosix}/tsa-serial
crypto_device = builtin
accuracy = secs:1
ordering = no
tsa_name = no
CFG;

        file_put_contents($directory.DIRECTORY_SEPARATOR.'tsa.cnf', $config);
        file_put_contents($directory.DIRECTORY_SEPARATOR.'tsa-serial', "01\n");

        $this->runOpenSsl($openssl, [
            'req', '-x509', '-newkey', 'rsa:2048',
            '-keyout', $directory.DIRECTORY_SEPARATOR.'tsa.key',
            '-out', $directory.DIRECTORY_SEPARATOR.'tsa.crt',
            '-days', '365',
            '-nodes',
            '-config', $directory.DIRECTORY_SEPARATOR.'tsa.cnf',
        ]);

        $this->runOpenSsl($openssl, [
            'ts', '-query',
            '-digest', $hash,
            '-sha256',
            '-cert',
            '-out', $directory.DIRECTORY_SEPARATOR.'req.tsq',
        ]);

        $queryText = $this->runOpenSsl($openssl, [
            'ts', '-query',
            '-in', $directory.DIRECTORY_SEPARATOR.'req.tsq',
            '-text',
        ]);
        preg_match('/Nonce:\s*0x([A-Fa-f0-9]+)/', $queryText, $matches);
        $nonce = $matches[1] ?? null;
        $this->assertIsString($nonce);

        $this->runOpenSsl($openssl, [
            'ts', '-reply',
            '-config', $directory.DIRECTORY_SEPARATOR.'tsa.cnf',
            '-section', 'tsa_config1',
            '-queryfile', $directory.DIRECTORY_SEPARATOR.'req.tsq',
            '-out', $directory.DIRECTORY_SEPARATOR.'resp.tsr',
            '-token_out',
        ]);

        return [
            'timestamp_token' => base64_encode((string) file_get_contents($directory.DIRECTORY_SEPARATOR.'resp.tsr')),
            'trust_cert_path' => $directory.DIRECTORY_SEPARATOR.'tsa.crt',
            'nonce' => $nonce,
        ];
    }

    private function resolveOpenSslBinary(): ?string
    {
        foreach ([
            'C:\\Program Files\\Git\\mingw64\\bin\\openssl.exe',
            'C:\\Program Files\\Git\\usr\\bin\\openssl.exe',
        ] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $arguments
     */
    private function runOpenSsl(string $binary, array $arguments): string
    {
        $process = new Process([$binary, ...$arguments]);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());

        return trim($process->getOutput()."\n".$process->getErrorOutput());
    }
}
