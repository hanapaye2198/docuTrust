<?php

namespace App\Services;

use App\Contracts\HsmService;
use DateTimeImmutable;
use RuntimeException;

/**
 * Mock HSM Service
 * 
 * Implementation for development/testing. Replace with real HSM client
 * for production CSC compliance.
 */
class MockHsmService implements HsmService
{
    private array $keys = [];
    private array $status = [
        'status' => 'online',
        'lastCheck' => '',
        'uptime' => 0,
        'errors' => 0,
    ];

    public function __construct()
    {
        $this->status['lastCheck'] = now()->toIso8601String();
        $this->status['uptime'] = time();
    }

    public function sign(string $hash, string $keyId): string
    {
        if (!isset($this->keys[$keyId])) {
            throw new RuntimeException("Key {$keyId} not found in HSM.");
        }

        // Simulate signing (in production, this would call HSM API)
        $privateKey = $this->keys[$keyId]['private_key'];
        $signature = '';
        openssl_sign(hex2bin($hash), $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    public function verify(string $hash, string $signature, string $keyId): bool
    {
        if (!isset($this->keys[$keyId])) {
            return false;
        }

        $publicKey = $this->keys[$keyId]['public_key'];
        $signatureDecoded = base64_decode($signature, true);

        if ($signatureDecoded === false) {
            return false;
        }

        $result = openssl_verify(hex2bin($hash), $signatureDecoded, $publicKey, OPENSSL_ALGO_SHA256);

        return $result === 1;
    }

    public function generateRsaKeyPair(int $bits = 2048): array
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => $bits,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($resource === false) {
            throw new RuntimeException('Unable to generate RSA key pair.');
        }

        $privateKey = '';
        openssl_pkey_export($resource, $privateKey);
        $details = openssl_pkey_get_details($resource);

        if ($details === false || !isset($details['key'])) {
            throw new RuntimeException('Unable to export key pair.');
        }

        $publicKey = $details['key'];
        $keyId = 'key_' . bin2hex(random_bytes(16));

        $this->keys[$keyId] = [
            'public_key' => $publicKey,
            'private_key' => $privateKey,
            'created_at' => now()->toIso8601String(),
        ];

        // Generate fingerprint
        $fingerprint = hash('sha256', $publicKey);

        return [
            'publicKey' => $publicKey,
            'privateKeyId' => $keyId,
            'fingerprint' => $fingerprint,
        ];
    }

    public function getPublicKey(string $keyId): string
    {
        if (!isset($this->keys[$keyId])) {
            throw new RuntimeException("Key {$keyId} not found in HSM.");
        }

        return $this->keys[$keyId]['public_key'];
    }

    public function destroyKey(string $keyId): bool
    {
        if (!isset($this->keys[$keyId])) {
            return false;
        }

        unset($this->keys[$keyId]);
        return true;
    }

    public function getStatus(): array
    {
        $this->status['lastCheck'] = now()->toIso8601String();
        $this->status['uptime'] = time() - $this->status['uptime'];

        return $this->status;
    }

    public function getSlotInfo(): array
    {
        return [
            'slotCount' => 1,
            'availableSlots' => 1,
            'model' => 'MockHSM-Dev',
            'firmwareVersion' => '1.0.0',
        ];
    }
}
