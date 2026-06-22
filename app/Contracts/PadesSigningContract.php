<?php

namespace App\Contracts;

interface PadesSigningContract
{
    /**
     * @return array{digest: string, byte_range: array{0: int, 1: int, 2: int, 3: int}, prepared_pdf_path: string, contents_offset: int, contents_length: int}
     */
    public function prepareDigest(string $pdfPath): array;

    /**
     * @param  array{0: int, 1: int, 2: int, 3: int}  $byteRange
     * @return array{success: bool, output_path: string, byte_range: array{0: int, 1: int, 2: int, 3: int}}
     */
    public function embedSignature(string $preparedPdfPath, string $cmsHex, array $byteRange, string $outputPath): array;
}
