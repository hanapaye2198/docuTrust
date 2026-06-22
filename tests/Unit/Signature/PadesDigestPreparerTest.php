<?php

namespace Tests\Unit\Signature;

use App\Services\Signature\PadesDigestPreparer;
use PHPUnit\Framework\TestCase;

class PadesDigestPreparerTest extends TestCase
{
    /**
     * @var array<int, string>
     */
    private array $temporaryPaths = [];

    protected function tearDown(): void
    {
        foreach (array_unique($this->temporaryPaths) as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_prepare_returns_required_keys(): void
    {
        $tmpPath = $this->writeMinimalPdf();

        $result = (new PadesDigestPreparer)->prepareDigest($tmpPath);
        $this->trackPreparedPath($result['prepared_pdf_path']);

        foreach ([
            'digest',
            'byte_range',
            'prepared_pdf_path',
            'contents_offset',
            'contents_length',
        ] as $key) {
            $this->assertArrayHasKey($key, $result);
        }

        $this->assertCount(4, $result['byte_range']);
        foreach ($result['byte_range'] as $value) {
            $this->assertIsInt($value);
        }
        $this->assertNotSame('', $result['digest']);
        $this->assertFileExists($result['prepared_pdf_path']);
    }

    public function test_digest_is_base64_encoded_sha256(): void
    {
        $tmpPath = $this->writeMinimalPdf();

        $result = (new PadesDigestPreparer)->prepareDigest($tmpPath);
        $this->trackPreparedPath($result['prepared_pdf_path']);

        $decodedDigest = base64_decode($result['digest'], true);

        $this->assertNotFalse($decodedDigest);
        $this->assertSame(32, strlen($decodedDigest));
    }

    private function writeMinimalPdf(): string
    {
        $pdf = "%PDF-1.4\n"
            ."1 0 obj<</Type /Catalog /Pages 2 0 R>>endobj\n"
            ."2 0 obj<</Type /Pages /Kids [3 0 R] /Count 1>>endobj\n"
            ."3 0 obj<</Type /Page /MediaBox [0 0 612 792]>>endobj\n"
            ."xref\n"
            ."0 4\n"
            ."0000000000 65535 f\n"
            ."0000000010 00000 n\n"
            ."0000000060 00000 n\n"
            ."0000000118 00000 n\n"
            ."trailer<</Size 4 /Root 1 0 R>>\n"
            ."startxref\n"
            ."0\n"
            .'%%EOF';

        $basePath = tempnam(sys_get_temp_dir(), 'pades_test_');
        $this->assertIsString($basePath);
        $tmpPath = $basePath.'.pdf';
        file_put_contents($tmpPath, $pdf);

        $this->temporaryPaths[] = $basePath;
        $this->temporaryPaths[] = $tmpPath;

        return $tmpPath;
    }

    private function trackPreparedPath(string $preparedPath): void
    {
        $this->temporaryPaths[] = $preparedPath;

        if (str_ends_with($preparedPath, '.pdf')) {
            $this->temporaryPaths[] = substr($preparedPath, 0, -4);
        }
    }
}
