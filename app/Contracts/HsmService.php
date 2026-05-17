<?php

namespace App\Contracts;

use DateTimeImmutable;

interface HsmService
{
    /**
     * Sign a hash using HSM-backed private key
     *
     * @param string $hash Hex-encoded hash to sign
     * @param string $keyId Key identifier in HSM
     * @return string Base64-encoded signature
     */
    public function sign(string $hash, string $keyId): string;

    /**
     * Verify a signature using HSM-backed public key
     *
     * @param string $hash Hex-encoded hash
     * @param string $signature Base64-encoded signature
     * @param string $keyId Key identifier in HSM
     * @return bool
     */
    public function verify(string $hash, string $signature, string $keyId): bool;

    /**
     * Generate RSA key pair in HSM
     *
     * @param int $bits Key size (2048 or 4096)
     * @return array{publicKey: string, privateKeyId: string, fingerprint: string}
     */
    public function generateRsaKeyPair(int $bits = 2048): array;

    /**
     * Get public key from HSM by key ID
     *
     * @param string $keyId Key identifier
     * @return string PEM-encoded public key
     */
    public function getPublicKey(string $keyId): string;

    /**
     * Destroy key in HSM
     *
     * @param string $keyId Key identifier
     * @return bool
     */
    public function destroyKey(string $keyId): bool;

    /**
     * Get HSM status and health
     *
     * @return array{status: 'online'|'offline'|'degraded', lastCheck: string, uptime: int, errors: int}
     */
    public function getStatus(): array;

    /**
     * Get HSM slot information
     *
     * @return array{slotCount: int, availableSlots: int, model: string, firmwareVersion: string}
     */
    public function getSlotInfo(): array;
}
