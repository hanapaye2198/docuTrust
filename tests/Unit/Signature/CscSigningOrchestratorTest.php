<?php

namespace Tests\Unit\Signature;

use App\Contracts\PadesSigningContract;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Services\Signature\CscApiClient;
use App\Services\Signature\CscSigningOrchestrator;
use App\Services\Signature\SadLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class CscSigningOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_orchestrate_calls_pipeline_in_correct_order(): void
    {
        config()->set('signature.ltv_enabled', false);

        $digestData = [
            'digest' => 'digest-base64',
            'byte_range' => [0, 100, 200, 300],
            'prepared_pdf_path' => '/tmp/prepared.pdf',
            'contents_offset' => 100,
            'contents_length' => 100,
        ];
        $cmsSignature = base64_encode('fake-signature');

        $pades = Mockery::mock(PadesSigningContract::class);
        $pades->shouldReceive('prepareDigest')
            ->once()
            ->with('/tmp/stamped.pdf')
            ->ordered()
            ->andReturn($digestData);
        $pades->shouldReceive('embedSignature')
            ->once()
            ->with('/tmp/prepared.pdf', bin2hex('fake-signature'), [0, 100, 200, 300], '/tmp/out.pdf')
            ->ordered()
            ->andReturn([
                'success' => true,
                'output_path' => '/tmp/out.pdf',
                'byte_range' => [0, 100, 200, 300],
            ]);

        $cscClient = Mockery::mock(CscApiClient::class);
        $cscClient->shouldReceive('signHash')
            ->once()
            ->with('access-token', 'decrypted-sad-value', 'cred-001', 'digest-base64')
            ->ordered()
            ->andReturn([
                'signatures' => [$cmsSignature],
            ]);

        $sadService = Mockery::mock(SadLifecycleService::class);
        $sadService->shouldReceive('consumeSad')
            ->once()
            ->ordered()
            ->andReturn('decrypted-sad-value');

        $document = Document::factory()->create(['status' => DocumentStatus::Pending]);
        $signer = DocumentSigner::factory()->for($document)->create();

        $result = (new CscSigningOrchestrator(
            $pades,
            $cscClient,
            app('log'),
            $sadService,
        ))->orchestrate(
            document: $document,
            signer: $signer,
            stampedPdfPath: '/tmp/stamped.pdf',
            accessToken: 'access-token',
            sad: '',
            credentialId: 'cred-001',
            outputPath: '/tmp/out.pdf',
        );

        foreach (['output_path', 'byte_range', 'cms_signature', 'digest'] as $key) {
            $this->assertArrayHasKey($key, $result);
        }
        $this->assertSame('/tmp/out.pdf', $result['output_path']);
        $this->assertSame([0, 100, 200, 300], $result['byte_range']);
        $this->assertSame($cmsSignature, $result['cms_signature']);
        $this->assertSame('digest-base64', $result['digest']);
    }

    public function test_orchestrate_throws_when_sign_hash_returns_empty_signatures(): void
    {
        $pades = Mockery::mock(PadesSigningContract::class);
        $pades->shouldReceive('prepareDigest')
            ->once()
            ->andReturn([
                'digest' => 'digest-base64',
                'byte_range' => [0, 100, 200, 300],
                'prepared_pdf_path' => '/tmp/prepared.pdf',
                'contents_offset' => 100,
                'contents_length' => 100,
            ]);

        $cscClient = Mockery::mock(CscApiClient::class);
        $cscClient->shouldReceive('signHash')
            ->once()
            ->andReturn([
                'signatures' => [],
            ]);

        $sadService = Mockery::mock(SadLifecycleService::class);
        $sadService->shouldReceive('consumeSad')
            ->once()
            ->andReturn('sad-value');

        $document = Document::factory()->create(['status' => DocumentStatus::Pending]);
        $signer = DocumentSigner::factory()->for($document)->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no signature');

        (new CscSigningOrchestrator(
            $pades,
            $cscClient,
            app('log'),
            $sadService,
        ))->orchestrate(
            document: $document,
            signer: $signer,
            stampedPdfPath: '/tmp/stamped.pdf',
            accessToken: 'access-token',
            sad: '',
            credentialId: 'cred-001',
            outputPath: '/tmp/out.pdf',
        );
    }
}
