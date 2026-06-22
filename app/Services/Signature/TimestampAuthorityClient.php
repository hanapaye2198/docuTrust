<?php

namespace App\Services\Signature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TimestampAuthorityClient
{
    public function requestTimestamp(string $digest, string $digestAlgo = 'sha256'): string
    {
        $url = (string) config('signature.tsa.url', '');
        if ($url === '') {
            throw new RuntimeException('TSA URL is not configured.');
        }

        $tsq = $this->buildTsq($digest, $digestAlgo);
        $response = Http::timeout((int) config('signature.tsa.timeout', 15))
            ->withBody($tsq, 'application/timestamp-query')
            ->accept('application/timestamp-reply')
            ->post($url);

        $contentType = strtolower((string) $response->header('Content-Type', ''));
        if ($response->status() !== 200) {
            throw new RuntimeException("TSA timestamp request failed with HTTP {$response->status()}.");
        }

        if (! str_contains($contentType, 'application/timestamp-reply')) {
            throw new RuntimeException("TSA timestamp response had unexpected content type [{$contentType}].");
        }

        return $response->body();
    }

    public function buildTsq(string $hexDigest, string $algo = 'sha256'): string
    {
        if (strtolower($algo) !== 'sha256') {
            throw new RuntimeException('Only SHA-256 timestamp requests are currently supported.');
        }

        $rawDigest = $this->decodeDigest($hexDigest);
        if (strlen($rawDigest) !== 32) {
            throw new RuntimeException('SHA-256 timestamp requests require a 32-byte digest.');
        }

        $version = $this->derTlv('02', "\x01");
        $hashAlgorithm = hex2bin('300d06096086480165030402010500');
        if ($hashAlgorithm === false) {
            throw new RuntimeException('Unable to decode SHA-256 AlgorithmIdentifier.');
        }

        $hashedMessage = $this->derTlv('04', $rawDigest);
        $messageImprint = $this->derTlv('30', $hashAlgorithm.$hashedMessage);
        $certReq = $this->derTlv('01', "\xff");

        return $this->derTlv('30', $version.$messageImprint.$certReq);
    }

    public function verifyTimestampToken(string $tsrBytes, string $originalDigest): bool
    {
        $tmpTsr = tempnam(sys_get_temp_dir(), 'docutrust-tsr-');
        if ($tmpTsr === false) {
            Log::channel('signature')->warning('Unable to allocate temporary TSR file for verification.');

            return false;
        }

        try {
            if (file_put_contents($tmpTsr, $tsrBytes) === false) {
                Log::channel('signature')->warning('Unable to write temporary TSR file for verification.');

                return false;
            }

            $digest = bin2hex($this->decodeDigest($originalDigest));
            $command = sprintf(
                'openssl ts -verify -digest %s -in %s -sha256%s 2>&1',
                escapeshellarg($digest),
                escapeshellarg($tmpTsr),
                $this->caFileArgument(),
            );

            $output = [];
            $exitCode = 1;
            exec($command, $output, $exitCode);

            Log::channel('signature')->info('TSA timestamp verification completed', [
                'success' => $exitCode === 0,
                'exit_code' => $exitCode,
                'output' => implode("\n", $output),
            ]);

            return $exitCode === 0;
        } finally {
            if (is_file($tmpTsr)) {
                @unlink($tmpTsr);
            }
        }
    }

    private function decodeDigest(string $digest): string
    {
        $normalized = preg_replace('/\s+/', '', $digest);
        if (! is_string($normalized) || $normalized === '') {
            throw new RuntimeException('Timestamp digest must not be empty.');
        }

        if ((strlen($normalized) % 2) === 0 && ctype_xdigit($normalized)) {
            $raw = hex2bin($normalized);
            if ($raw !== false) {
                return $raw;
            }
        }

        $raw = base64_decode($normalized, true);
        if ($raw === false) {
            throw new RuntimeException('Timestamp digest must be hex or base64 encoded.');
        }

        return $raw;
    }

    private function derLength(int $length): string
    {
        if ($length < 0) {
            throw new RuntimeException('DER length cannot be negative.');
        }

        if ($length < 128) {
            return chr($length);
        }

        $bytes = '';
        while ($length > 0) {
            $bytes = chr($length & 0xFF).$bytes;
            $length >>= 8;
        }

        return chr(0x80 | strlen($bytes)).$bytes;
    }

    private function derTlv(string $tag, string $value): string
    {
        $tagBytes = hex2bin($tag);
        if ($tagBytes === false) {
            throw new RuntimeException("Invalid DER tag [{$tag}].");
        }

        return $tagBytes.$this->derLength(strlen($value)).$value;
    }

    private function caFileArgument(): string
    {
        $caFile = trim((string) config('signature.tsa.ca_cert', ''));

        return $caFile !== '' ? ' -CAfile '.escapeshellarg($caFile) : '';
    }
}
