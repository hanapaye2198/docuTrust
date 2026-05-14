<?php

namespace App\Services;

use App\Contracts\HsmService;
use RuntimeException;

/**
 * HSM-backed PKI Signature Service
 * 
 * Implements FIPS 140-2 Level 3 compliant digital signatures using HSM.
 * This service replaces the software-based PkiSignatureService for CSC compliance.
 */
class HsmPkiSignatureService
{
    public function __construct(private readonly HsmService $hsmService) {}

    /**
     * Sign a hash using HSM-backed RSA key
     *
     * @param string $hashHex Hex-encoded SHA-256 hash
     * @param string $keyId Key identifier in HSM
     * @return string Base64-encoded RSA-SHA256 signature
     */
    public function signHash(string $hashHex, string $keyId): string
    {
        $normalizedHash = $this->normalizeHash($hashHex);

        try {
            return $this->hsmService->sign($normalizedHash, $keyId);
        } catch (\Throwable $e) {
            throw new RuntimeException('HSM signing operation failed: ' . $e->getMessage());
        }
    }

    /**
     * Verify a signature using HSM-backed RSA key
     *
     * @param string $hashHex Hex-encoded SHA-256 hash
     * @param string $signatureBase64 Base64-encoded signature
     * @param string $keyId Key identifier in HSM
     * @return bool
     */
    public function verifySignature(string $hashHex, string $signatureBase64, string $keyId): bool
    {
        $normalizedHash = $this->normalizeHash($hashHex);

        try {
            return $this->hsmService->verify($normalizedHash, $signatureBase64, $keyId);
        } catch (\Throwable $e) {
            // Log error but return false for security
            \Log::channel('crypto')->error('HSM signature verification failed', [
                'hash' => $hashHex,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Generate fingerprint from public key
     *
     * @param string $publicKeyPem PEM-encoded public key
     * @return string SHA-256 fingerprint
     */
    public function fingerprint(string $publicKeyPem): string
    {
        // Normalize PEM to DER for consistent fingerprinting
        $normalized = preg_replace(
            '/-----BEGIN PUBLIC KEY-----|-----END PUBLIC KEY-----|\s+/',
            '',
            $publicKeyPem
        );

        if (!is_string($normalized) || $normalized === '') {
            throw new RuntimeException('Invalid public key format for fingerprinting.');
        }

        $der = base64_decode($normalized, true);
        if ($der === false) {
            throw new RuntimeException('Unable to decode public key for fingerprinting.');
        }

        return hash('sha256', $der);
    }

    private function normalizeHash(string $hashHex): string
    {
        $normalized = strtolower(trim($hashHex));
        if (str_starts_with($normalized, '0x')) {
            $normalized = substr($normalized, 2);
        }

        if (!preg_match('/^[a-f0-9]{64}$/', $normalized)) {
            throw new RuntimeException('Invalid SHA-256 hash provided for signing.');
        }

        return $normalized;
    }
}
