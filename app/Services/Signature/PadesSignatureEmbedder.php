<?php

namespace App\Services\Signature;

use RuntimeException;

class PadesSignatureEmbedder
{
    /**
     * @param  array{0: int, 1: int, 2: int, 3: int}  $byteRange
     * @return array{success: bool, output_path: string, byte_range: array{0: int, 1: int, 2: int, 3: int}}
     */
    public function embed(string $pdfPath, string $cmsSignatureHex, array $byteRange, string $outputPath): array
    {
        $this->validateReadablePdf($pdfPath);
        $this->validateOutputPath($outputPath);
        $this->validateByteRange($byteRange);

        $cmsHex = $this->normalizeCmsHex($cmsSignatureHex);
        $slotStart = $byteRange[0] + $byteRange[1];
        $slotLength = $byteRange[2] - $slotStart;

        if ($slotLength <= 0) {
            throw new RuntimeException('Invalid PAdES /Contents slot length calculated from byte range.');
        }

        if (strlen($cmsHex) > $slotLength) {
            throw new RuntimeException(sprintf(
                'CMS signature hex length (%d) exceeds PAdES /Contents slot length (%d).',
                strlen($cmsHex),
                $slotLength,
            ));
        }

        $source = fopen($pdfPath, 'rb');
        if (! is_resource($source)) {
            throw new RuntimeException("Unable to open prepared PDF for reading: {$pdfPath}");
        }

        $target = fopen($outputPath, 'wb');
        if (! is_resource($target)) {
            fclose($source);

            throw new RuntimeException("Unable to open signed PDF for writing: {$outputPath}");
        }

        try {
            $this->copyRange($source, $target, 0, $slotStart);
            $this->writeAll($target, str_pad($cmsHex, $slotLength, '0'));

            if (fseek($source, $byteRange[2]) !== 0) {
                throw new RuntimeException("Unable to seek to post-signature byte range offset {$byteRange[2]}.");
            }

            $this->copyRemainingRange($source, $target, $byteRange[3]);
        } finally {
            fclose($source);
            fclose($target);
        }

        return [
            'success' => true,
            'output_path' => $outputPath,
            'byte_range' => $byteRange,
        ];
    }

    private function validateReadablePdf(string $pdfPath): void
    {
        if (! is_file($pdfPath) || ! is_readable($pdfPath)) {
            throw new RuntimeException("Unable to read prepared PDF for PAdES signature embedding: {$pdfPath}");
        }
    }

    private function validateOutputPath(string $outputPath): void
    {
        $directory = dirname($outputPath);
        if (! is_dir($directory) || ! is_writable($directory)) {
            throw new RuntimeException("Unable to write signed PDF to directory: {$directory}");
        }
    }

    /**
     * @param  array<int, mixed>  $byteRange
     */
    private function validateByteRange(array $byteRange): void
    {
        if (count($byteRange) !== 4) {
            throw new RuntimeException('PAdES /ByteRange must contain exactly four integer values.');
        }

        foreach ($byteRange as $value) {
            if (! is_int($value) || $value < 0) {
                throw new RuntimeException('PAdES /ByteRange values must be non-negative integers.');
            }
        }

        if ($byteRange[2] < ($byteRange[0] + $byteRange[1])) {
            throw new RuntimeException('PAdES /ByteRange offset_after must be after the /Contents slot start.');
        }
    }

    private function normalizeCmsHex(string $cmsSignatureHex): string
    {
        $cmsHex = preg_replace('/\s+/', '', $cmsSignatureHex);
        if (! is_string($cmsHex) || $cmsHex === '') {
            throw new RuntimeException('CMS signature hex must not be empty.');
        }

        if ((strlen($cmsHex) % 2) !== 0 || ! ctype_xdigit($cmsHex)) {
            throw new RuntimeException('CMS signature must be an even-length hexadecimal string.');
        }

        return strtoupper($cmsHex);
    }

    /**
     * @param  resource  $source
     * @param  resource  $target
     */
    private function copyRange($source, $target, int $offset, int $length): void
    {
        if (fseek($source, $offset) !== 0) {
            throw new RuntimeException("Unable to seek to PDF byte offset {$offset}.");
        }

        $this->copyRemainingRange($source, $target, $length);
    }

    /**
     * @param  resource  $source
     * @param  resource  $target
     */
    private function copyRemainingRange($source, $target, int $length): void
    {
        $remaining = $length;
        while ($remaining > 0) {
            $chunk = fread($source, min(8192, $remaining));
            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('Unable to read prepared PDF byte range while embedding signature.');
            }

            $this->writeAll($target, $chunk);
            $remaining -= strlen($chunk);
        }
    }

    /**
     * @param  resource  $target
     */
    private function writeAll($target, string $bytes): void
    {
        $offset = 0;
        $length = strlen($bytes);

        while ($offset < $length) {
            $written = fwrite($target, substr($bytes, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Unable to write signed PDF bytes.');
            }

            $offset += $written;
        }
    }
}
