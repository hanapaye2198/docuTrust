<?php

namespace App\Services;

use App\Contracts\HsmService;
use App\Contracts\SignerKeyStore;
use App\Models\DocumentSigner;
use RuntimeException;

/**
 * HSM-backed key store for PKI operations
 * 
 * This service manages cryptographic keys in a FIPS 140-2 Level 3 certified HSM
 * as required by CSC standards for Certification Authorities.
 */
class HsmKeyManager implements SignerKeyStore
{
    public function __construct(private readonly HsmService $hsmService) {}

    /**
     * Check if signer has a key pair in HSM.
     */
    public function hasKeyPair(DocumentSigner $signer): bool
    {
        return is_string($signer->hsm_key_id) && $signer->hsm_key_id !== '';
    }

    /**
     * Get key pair for a signer.
     * Returns public_key PEM and private_key (HSM key ID used as reference).
     *
     * @return array{public_key: string, private_key: string}
     */
    public function keyPairFor(DocumentSigner $signer): array
    {
        if (!$this->hasKeyPair($signer)) {
            throw new RuntimeException('No HSM key ID found for signer.');
        }

        $publicKey = $this->hsmService->getPublicKey($signer->hsm_key_id);

        // Interface expects 'private_key' — for HSM, this is the key ID reference
        // The actual private key never leaves the HSM
        return [
            'public_key' => $publicKey,
            'private_key' => $signer->hsm_key_id,
        ];
    }

    /**
     * Store a key pair for a signer.
     * For HSM: generates a new key pair inside the HSM (ignores provided PEM keys).
     *
     * @return array{public_key: string, private_key: string}
     */
    public function storeKeyPair(DocumentSigner $signer, string $publicKeyPem, string $privateKeyPem): array
    {
        // For HSM, we generate keys inside the HSM rather than importing
        $keyPair = $this->generateKeyPairForSigner($signer);

        return [
            'public_key' => $keyPair['publicKey'],
            'private_key' => $keyPair['privateKeyId'],
        ];
    }

    /**
     * Generate and store key pair in HSM for a signer.
     *
     * @return array{publicKey: string, privateKeyId: string, fingerprint: string}
     */
    public function generateKeyPairForSigner(DocumentSigner $signer): array
    {
        $keySize = (int) config('hsm.key.size', 2048);

        if ($keySize < 2048) {
            throw new RuntimeException('Key size must be at least 2048 bits for CSC compliance.');
        }

        $keyPair = $this->hsmService->generateRsaKeyPair($keySize);

        $signer->update([
            'hsm_key_id' => $keyPair['privateKeyId'],
            'public_key_fingerprint' => $keyPair['fingerprint'],
        ]);

        return $keyPair;
    }

    /**
     * Sign a hash using signer's HSM key.
     */
    public function signHash(DocumentSigner $signer, string $hash): string
    {
        if (!$this->hasKeyPair($signer)) {
            throw new RuntimeException('No HSM key ID found for signer.');
        }

        return $this->hsmService->sign($hash, $signer->hsm_key_id);
    }

    /**
     * Verify a signature using signer's HSM key.
     */
    public function verifySignature(DocumentSigner $signer, string $hash, string $signature): bool
    {
        if (!$this->hasKeyPair($signer)) {
            return false;
        }

        return $this->hsmService->verify($hash, $signature, $signer->hsm_key_id);
    }

    /**
     * Revoke and destroy key in HSM.
     */
    public function revokeKey(DocumentSigner $signer): bool
    {
        if (!$this->hasKeyPair($signer)) {
            return false;
        }

        $result = $this->hsmService->destroyKey($signer->hsm_key_id);

        if ($result) {
            $signer->update([
                'hsm_key_id' => null,
                'public_key_fingerprint' => null,
            ]);
        }

        return $result;
    }

    /**
     * Check HSM health status.
     */
    public function checkHealth(): array
    {
        $status = $this->hsmService->getStatus();

        if ($status['status'] === 'online' && $status['errors'] === 0) {
            return ['status' => 'healthy', 'message' => 'HSM is online and operating normally.'];
        }

        if ($status['status'] === 'offline') {
            return ['status' => 'unhealthy', 'message' => 'HSM is offline. PKI operations are unavailable.'];
        }

        return ['status' => 'degraded', 'message' => 'HSM is online but experiencing issues.'];
    }
}
