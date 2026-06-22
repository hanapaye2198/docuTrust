<?php

namespace App\Services\Signature;

use App\Contracts\PadesSigningContract;
use Illuminate\Support\Facades\Log;

class PadesSigningService implements PadesSigningContract
{
    public function __construct(
        private readonly PadesDigestPreparer $digestPreparer,
        private readonly PadesSignatureEmbedder $signatureEmbedder,
    ) {}

    /**
     * @return array{digest: string, byte_range: array{0: int, 1: int, 2: int, 3: int}, prepared_pdf_path: string, contents_offset: int, contents_length: int}
     */
    public function prepareDigest(string $pdfPath): array
    {
        Log::channel('signature')->info('Preparing PAdES digest', [
            'pdf_path' => $pdfPath,
        ]);

        $result = $this->digestPreparer->prepare($pdfPath);

        Log::channel('signature')->info('Prepared PAdES digest', [
            'prepared_pdf_path' => $result['prepared_pdf_path'],
            'byte_range' => $result['byte_range'],
            'contents_offset' => $result['contents_offset'],
            'contents_length' => $result['contents_length'],
        ]);

        return $result;
    }

    /**
     * @param  array{0: int, 1: int, 2: int, 3: int}  $byteRange
     * @return array{success: bool, output_path: string, byte_range: array{0: int, 1: int, 2: int, 3: int}}
     */
    public function embedSignature(string $preparedPdfPath, string $cmsHex, array $byteRange, string $outputPath): array
    {
        Log::channel('signature')->info('Embedding PAdES signature', [
            'prepared_pdf_path' => $preparedPdfPath,
            'output_path' => $outputPath,
            'byte_range' => $byteRange,
            'cms_hex_length' => strlen(preg_replace('/\s+/', '', $cmsHex) ?? ''),
        ]);

        $result = $this->signatureEmbedder->embed($preparedPdfPath, $cmsHex, $byteRange, $outputPath);

        Log::channel('signature')->info('Embedded PAdES signature', [
            'output_path' => $result['output_path'],
            'byte_range' => $result['byte_range'],
        ]);

        return $result;
    }
}
