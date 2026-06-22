<?php

namespace App\Services\Signature;

use HashContext;
use RuntimeException;

class PadesDigestPreparer
{
    private const CONTENTS_HEX_LENGTH = 32768;

    /**
     * @return array{digest: string, byte_range: array{0: int, 1: int, 2: int, 3: int}, prepared_pdf_path: string, contents_offset: int, contents_length: int}
     */
    public function prepareDigest(string $pdfPath): array
    {
        return $this->prepare($pdfPath);
    }

    /**
     * @return array{digest: string, byte_range: array{0: int, 1: int, 2: int, 3: int}, prepared_pdf_path: string, contents_offset: int, contents_length: int}
     */
    public function prepare(string $pdfPath): array
    {
        if (! is_file($pdfPath) || ! is_readable($pdfPath)) {
            throw new RuntimeException("Unable to read PDF for PAdES digest preparation: {$pdfPath}");
        }

        $preparedPdfPath = $this->temporaryPreparedPdfPath();
        $sourceBytes = $this->readFileBytes($pdfPath);
        $signatureDictionary = $this->signatureDictionaryPlaceholder();
        $preparedBytes = rtrim($sourceBytes)."\n".$signatureDictionary;

        $contentsOffset = strpos($preparedBytes, '/Contents <');
        if ($contentsOffset === false) {
            throw new RuntimeException('Unable to locate PAdES /Contents placeholder.');
        }

        $contentsOffset += strlen('/Contents <');
        $contentsLength = self::CONTENTS_HEX_LENGTH;
        $offsetAfter = $contentsOffset + $contentsLength;
        $fileSize = strlen($preparedBytes);

        if ($offsetAfter > $fileSize) {
            throw new RuntimeException('PAdES /Contents placeholder exceeds prepared PDF length.');
        }

        $byteRange = [
            0,
            $contentsOffset,
            $offsetAfter,
            $fileSize - $offsetAfter,
        ];

        $preparedBytes = str_replace(
            '/ByteRange [0000000000 0000000000 0000000000 0000000000]',
            sprintf(
                '/ByteRange [%010d %010d %010d %010d]',
                $byteRange[0],
                $byteRange[1],
                $byteRange[2],
                $byteRange[3],
            ),
            $preparedBytes,
            $replacementCount,
        );

        if ($replacementCount !== 1) {
            throw new RuntimeException('Unable to inject calculated PAdES /ByteRange values.');
        }

        $this->writeFileBytes($preparedPdfPath, $preparedBytes);

        return [
            'digest' => $this->digestByteRanges($preparedPdfPath, $byteRange),
            'byte_range' => $byteRange,
            'prepared_pdf_path' => $preparedPdfPath,
            'contents_offset' => $contentsOffset,
            'contents_length' => $contentsLength,
        ];
    }

    private function signatureDictionaryPlaceholder(): string
    {
        return sprintf(
            "%% DocuTrust PAdES signature placeholder\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached /ByteRange [0000000000 0000000000 0000000000 0000000000] /Contents <%s> /M (D:%s+00'00') >>\n%%EOF\n",
            str_repeat('0', self::CONTENTS_HEX_LENGTH),
            gmdate('YmdHis'),
        );
    }

    private function temporaryPreparedPdfPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'docutrust-pades-');
        if ($path === false) {
            throw new RuntimeException('Unable to allocate a temporary PDF for PAdES digest preparation.');
        }

        return $path.'.pdf';
    }

    private function readFileBytes(string $path): string
    {
        $handle = fopen($path, 'rb');
        if (! is_resource($handle)) {
            throw new RuntimeException("Unable to open PDF for reading: {$path}");
        }

        try {
            $bytes = '';
            while (! feof($handle)) {
                $chunk = fread($handle, 8192);
                if ($chunk === false) {
                    throw new RuntimeException("Unable to read PDF bytes from: {$path}");
                }

                $bytes .= $chunk;
            }

            return $bytes;
        } finally {
            fclose($handle);
        }
    }

    private function writeFileBytes(string $path, string $bytes): void
    {
        $handle = fopen($path, 'wb');
        if (! is_resource($handle)) {
            throw new RuntimeException("Unable to open prepared PDF for writing: {$path}");
        }

        try {
            $written = fwrite($handle, $bytes);
            if ($written === false || $written !== strlen($bytes)) {
                throw new RuntimeException("Unable to write complete prepared PDF: {$path}");
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array{0: int, 1: int, 2: int, 3: int}  $byteRange
     */
    private function digestByteRanges(string $path, array $byteRange): string
    {
        $handle = fopen($path, 'rb');
        if (! is_resource($handle)) {
            throw new RuntimeException("Unable to open prepared PDF for digesting: {$path}");
        }

        $hash = hash_init('sha256');

        try {
            $this->hashRange($handle, $hash, $byteRange[0], $byteRange[1]);
            $this->hashRange($handle, $hash, $byteRange[2], $byteRange[3]);

            return base64_encode(hash_final($hash, true));
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     * @param  resource  $handle
     */
    private function hashRange($handle, HashContext $hash, int $offset, int $length): void
    {
        if (fseek($handle, $offset) !== 0) {
            throw new RuntimeException("Unable to seek to PDF byte offset {$offset}.");
        }

        $remaining = $length;
        while ($remaining > 0) {
            $chunkSize = min(8192, $remaining);
            $chunk = fread($handle, $chunkSize);
            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('Unable to read PDF byte range for digest calculation.');
            }

            hash_update($hash, $chunk);
            $remaining -= strlen($chunk);
        }
    }
}
