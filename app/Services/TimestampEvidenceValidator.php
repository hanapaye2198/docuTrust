<?php

namespace App\Services;

use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class TimestampEvidenceValidator
{
    /**
     * @param  array<string, mixed>|null  $payload
     * @return array{status: 'verified'|'failed'|'not_available', reason: string}
     */
    public function validate(?array $payload, string $expectedHash): array
    {
        if (! is_array($payload) || $payload === []) {
            return [
                'status' => 'not_available',
                'reason' => 'No timestamp evidence recorded.',
            ];
        }

        $token = $payload['timestamp_token'] ?? null;
        if (! is_string($token) || trim($token) === '') {
            return [
                'status' => 'not_available',
                'reason' => 'No RFC3161 timestamp token recorded.',
            ];
        }

        if (base64_decode($token, true) === false) {
            return [
                'status' => 'failed',
                'reason' => 'Recorded RFC3161 timestamp token is not valid base64.',
            ];
        }

        $opensslBinary = $this->resolveOpenSslBinary();
        if ($opensslBinary === null) {
            return [
                'status' => 'failed',
                'reason' => 'OpenSSL CLI is not configured for RFC3161 timestamp verification.',
            ];
        }

        $trustCertPath = trim((string) config('services.remote_signing.csc.timestamp_trust_cert_path', ''));
        if ($trustCertPath === '' || ! is_file($trustCertPath)) {
            return [
                'status' => 'failed',
                'reason' => 'Trusted TSA certificate bundle is not configured for RFC3161 timestamp verification.',
            ];
        }

        $recordedHash = $payload['timestamp_hash'] ?? null;
        if (! is_string($recordedHash) || trim($recordedHash) === '') {
            return [
                'status' => 'failed',
                'reason' => 'Recorded timestamp hash is missing.',
            ];
        }

        $normalizedExpected = $this->normalizeHash($expectedHash);
        $normalizedRecorded = $this->normalizeHash($recordedHash);

        if ($normalizedExpected === null) {
            return [
                'status' => 'failed',
                'reason' => 'Verified document hash is invalid for RFC3161 timestamp verification.',
            ];
        }

        if ($normalizedRecorded === null) {
            return [
                'status' => 'failed',
                'reason' => 'Recorded timestamp hash is invalid.',
            ];
        }

        if (! hash_equals($normalizedExpected, $normalizedRecorded)) {
            return [
                'status' => 'failed',
                'reason' => 'Recorded timestamp hash does not match the verified document hash.',
            ];
        }

        $nonce = $payload['timestamp_request_nonce'] ?? null;
        if ($nonce !== null && (! is_string($nonce) || ! preg_match('/^[a-f0-9]+$/i', $nonce))) {
            return [
                'status' => 'failed',
                'reason' => 'Recorded timestamp nonce is invalid.',
            ];
        }

        $algorithm = $this->resolveDigestAlgorithm($payload['timestamp_hash_algorithm'] ?? null);
        if ($algorithm === null) {
            return [
                'status' => 'failed',
                'reason' => 'Recorded timestamp hash algorithm is unsupported.',
            ];
        }

        $der = base64_decode($token, true);
        if (! is_string($der)) {
            return [
                'status' => 'failed',
                'reason' => 'Recorded RFC3161 timestamp token could not be decoded.',
            ];
        }

        $tmpDir = storage_path('app/tmp/timestamp-validation-'.Str::uuid()->toString());
        if (! is_dir($tmpDir) && ! @mkdir($tmpDir, 0777, true) && ! is_dir($tmpDir)) {
            return [
                'status' => 'failed',
                'reason' => 'Temporary storage for RFC3161 timestamp verification is unavailable.',
            ];
        }

        $tokenPath = $tmpDir.DIRECTORY_SEPARATOR.'timestamp.tsr';
        file_put_contents($tokenPath, $der);

        try {
            $verifyProcess = new Process([
                $opensslBinary,
                'ts',
                '-verify',
                '-digest',
                $normalizedExpected,
                '-'.$algorithm,
                '-in',
                $tokenPath,
                '-token_in',
                '-CAfile',
                $trustCertPath,
            ]);
            $verifyProcess->run();

            if (! $verifyProcess->isSuccessful()) {
                return [
                    'status' => 'failed',
                    'reason' => 'RFC3161 timestamp token signature verification failed.',
                ];
            }

            $textProcess = new Process([
                $opensslBinary,
                'ts',
                '-reply',
                '-in',
                $tokenPath,
                '-token_in',
                '-text',
            ]);
            $textProcess->run();

            if (! $textProcess->isSuccessful()) {
                return [
                    'status' => 'failed',
                    'reason' => 'RFC3161 timestamp token could not be inspected.',
                ];
            }

            $tokenText = trim($textProcess->getOutput()."\n".$textProcess->getErrorOutput());
            $nonceMismatch = $this->validateNonceFromTokenText($tokenText, $nonce);
            if ($nonceMismatch !== null) {
                return [
                    'status' => 'failed',
                    'reason' => $nonceMismatch,
                ];
            }

            return [
                'status' => 'verified',
                'reason' => 'RFC3161 timestamp token signature, trust chain, message imprint, and nonce were verified.',
            ];
        } finally {
            @unlink($tokenPath);
            @rmdir($tmpDir);
        }
    }

    private function normalizeHash(string $hash): ?string
    {
        $normalized = strtolower(trim($hash));
        if (str_starts_with($normalized, '0x')) {
            $normalized = substr($normalized, 2);
        }

        return preg_match('/^[a-f0-9]{64}$/', $normalized) === 1 ? $normalized : null;
    }

    private function resolveOpenSslBinary(): ?string
    {
        $configured = trim((string) config('services.remote_signing.csc.timestamp_openssl_binary', ''));
        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }

        foreach ([
            'C:\\Program Files\\Git\\mingw64\\bin\\openssl.exe',
            'C:\\Program Files\\Git\\usr\\bin\\openssl.exe',
            'C:\\Program Files\\Surfshark\\Resources\\x64\\openssl.exe',
            'C:\\Program Files\\Surfshark\\Resources\\x32\\openssl.exe',
        ] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function resolveDigestAlgorithm(mixed $value): ?string
    {
        return match (strtolower(trim((string) $value))) {
            '2.16.840.1.101.3.4.2.1', 'sha256' => 'sha256',
            '2.16.840.1.101.3.4.2.2', 'sha384' => 'sha384',
            '2.16.840.1.101.3.4.2.3', 'sha512' => 'sha512',
            default => null,
        };
    }

    private function validateNonceFromTokenText(string $tokenText, ?string $expectedNonce): ?string
    {
        if (! is_string($expectedNonce) || $expectedNonce === '') {
            return null;
        }

        if (! preg_match('/Nonce:\s*0x([A-Fa-f0-9]+)/', $tokenText, $matches)) {
            return 'RFC3161 timestamp token nonce is missing from the token payload.';
        }

        return hash_equals(strtolower($expectedNonce), strtolower($matches[1]))
            ? null
            : 'RFC3161 timestamp token nonce does not match the recorded timestamp request nonce.';
    }
}
