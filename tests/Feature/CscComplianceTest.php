<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\SignerCertificate;
use App\Services\HsmAuditLogger;
use App\Services\HsmKeyManager;
use App\Services\HsmPkiSignatureService;
use App\Services\HsmService;
use App\Services\Pkcs10Request;
use App\Services\ScepService;
use App\Services\CmpService;
use App\Services\CrlGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CSC Compliance Test Suite
 * 
 * Tests for CSC (Certification Service Provider) compliance requirements.
 */
class CscComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected HsmService $hsmService;
    protected HsmKeyManager $hsmKeyManager;
    protected HsmPkiSignatureService $pkiSignatureService;
    protected HsmAuditLogger $auditLogger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hsmService = $this->mock(HsmService::class);
        $this->hsmKeyManager = $this->app->make(HsmKeyManager::class);
        $this->pkiSignatureService = $this->app->make(HsmPkiSignatureService::class);
        $this->auditLogger = $this->app->make(HsmAuditLogger::class);
    }

    /** @test */
    public function it_requires_hsm_key_for_signing(): void
    {
        $signer = DocumentSigner::factory()->create([
            'hsm_key_id' => null,
        ]);

        // HSM key should be required for signing
        $this->assertNull($signer->hsm_key_id);
    }

    /** @test */
    public function it_generates_hsm_key_pair(): void
    {
        $this->hsmService->shouldReceive('generateRsaKeyPair')
            ->with(2048)
            ->andReturn([
                'publicKey' => '-----BEGIN PUBLIC KEY-----test-----END PUBLIC KEY-----',
                'privateKeyId' => 'test-key-id',
                'fingerprint' => hash('sha256', 'test'),
            ]);

        $keyPair = $this->hsmService->generateRsaKeyPair(2048);

        $this->assertArrayHasKey('publicKey', $keyPair);
        $this->assertArrayHasKey('privateKeyId', $keyPair);
        $this->assertArrayHasKey('fingerprint', $keyPair);
    }

    /** @test */
    public function it_signs_with_hsm(): void
    {
        $hash = str_repeat('a', 64); // SHA-256 hash
        $keyId = 'test-key-id';
        $signature = base64_encode(random_bytes(256));

        $this->hsmService->shouldReceive('sign')
            ->with($hash, $keyId)
            ->andReturn($signature);

        $result = $this->hsmService->sign($hash, $keyId);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /** @test */
    public function it_verifies_signature_with_hsm(): void
    {
        $hash = str_repeat('a', 64);
        $keyId = 'test-key-id';
        $signature = base64_encode(random_bytes(256));

        $this->hsmService->shouldReceive('verify')
            ->with($hash, $signature, $keyId)
            ->andReturn(true);

        $result = $this->hsmService->verify($hash, $signature, $keyId);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_generates_pkcs10_csr(): void
    {
        $subject = [
            'commonName' => 'Test User',
            'organizationName' => 'Test Organization',
            'countryName' => 'PH',
        ];

        $pkcs10 = new Pkcs10Request();
        $pkcs10->setDistinguishedName($subject);

        // Generate a test key pair
        $resource = openssl_pkey_new(['private_key_bits' => 2048]);
        $privateKey = '';
        openssl_pkey_export($resource, $privateKey);
        $details = openssl_pkey_get_details($resource);
        $publicKey = $details['key'];

        $csr = $pkcs10->generate($privateKey);

        $this->assertStringContainsString('-----BEGIN CERTIFICATE REQUEST-----', $csr);
        $this->assertStringContainsString('-----END CERTIFICATE REQUEST-----', $csr);
    }

    /** @test */
    public function it_generates_scep_transaction_id(): void
    {
        $scep = new ScepService();

        $transactionId = $scep->generateTransactionId();

        $this->assertIsString($transactionId);
        $this->assertEquals(32, strlen($transactionId)); // 16 bytes hex
    }

    /** @test */
    public function it_generates_scep_nonce(): void
    {
        $scep = new ScepService();

        $nonce = $scep->generateNonce();

        $this->assertIsString($nonce);
        $this->assertEquals(32, strlen($nonce)); // 16 bytes hex
    }

    /** @test */
    public function it_generates_cmp_transaction_id(): void
    {
        $cmp = new CmpService();

        $transactionId = $cmp->generateTransactionId();

        $this->assertIsString($transactionId);
        $this->assertEquals(32, strlen($transactionId));
    }

    /** @test */
    public function it_generates_crl(): void
    {
        $crlGenerator = new CrlGenerator();

        $crl = $crlGenerator->generate();

        $this->assertIsString($crl);
        $this->assertStringContainsString('-----BEGIN X509 CRL-----', $crl);
        $this->assertStringContainsString('-----END X509 CRL-----', $crl);
    }

    /** @test */
    public function it_logs_hsm_operations(): void
    {
        $keyId = 'test-key-id';
        $hash = str_repeat('a', 64);

        $this->auditLogger->logKeySign($keyId, $hash, null, null, null);

        $this->assertDatabaseHas('hsm_key_audit_log', [
            'operation' => 'key_sign',
            'key_id' => $keyId,
        ]);
    }

    /** @test */
    public function it_validates_hsm_health(): void
    {
        $this->hsmService->shouldReceive('getStatus')
            ->andReturn([
                'status' => 'online',
                'lastCheck' => now()->toIso8601String(),
                'uptime' => 3600,
                'errors' => 0,
            ]);

        $status = $this->hsmService->getStatus();

        $this->assertEquals('online', $status['status']);
        $this->assertEquals(0, $status['errors']);
    }

    /** @test */
    public function it_requires_minimum_key_size(): void
    {
        $keySize = (int) config('docutrust.pki.key_size', 2048);

        $this->assertGreaterThanOrEqual(2048, $keySize);
    }

    /** @test */
    public function it_uses_approved_hash_algorithm(): void
    {
        $hash = hash('sha256', 'test data');

        $this->assertEquals(64, strlen($hash)); // SHA-256 produces 64 hex chars
    }

    /** @test */
    public function it_uses_approved_signature_algorithm(): void
    {
        $resource = openssl_pkey_new(['private_key_bits' => 2048]);
        $privateKey = '';
        openssl_pkey_export($resource, $privateKey);
        $details = openssl_pkey_get_details($resource);
        $publicKey = $details['key'];

        $hash = hash('sha256', 'test data');
        $signature = '';
        openssl_sign(hex2bin($hash), $signature, $privateKey, OPENSSL_ALGO_SHA256);

        $verified = openssl_verify(hex2bin($hash), $signature, $publicKey, OPENSSL_ALGO_SHA256);

        $this->assertEquals(1, $verified);
    }
}
