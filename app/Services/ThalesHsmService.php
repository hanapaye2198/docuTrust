<?php

namespace App\Services;

use App\Contracts\HsmService;
use RuntimeException;

/**
 * Thales Luna HSM Service
 * 
 * Integration with Thales Luna Network HSM for FIPS 140-2 Level 3 compliance.
 * Requires Thales Luna SDK and client library.
 */
class ThalesHsmService implements HsmService
{
    private ?\LunaClient $client = null;
    private string $partitionPassword;
    private string $partitionLabel;

    public function __construct()
    {
        $this->partitionPassword = (string) config('hsm.thales.partition_password', '');
        $this->partitionLabel = (string) config('hsm.thales.partition_label', 'default');
    }

    public function sign(string $hash, string $keyId): string
    {
        $this->ensureConnected();

        // Use Thales SDK to sign with HSM key
        $signature = $this->client->sign(
            $keyId,
            hex2bin($hash),
            \LunaClient::ALGO_SHA256_RSA
        );

        return base64_encode($signature);
    }

    public function verify(string $hash, string $signature, string $keyId): bool
    {
        $this->ensureConnected();

        $verified = $this->client->verify(
            $keyId,
            hex2bin($hash),
            base64_decode($signature),
            \LunaClient::ALGO_SHA256_RSA
        );

        return $verified === true;
    }

    public function generateRsaKeyPair(int $bits = 2048): array
    {
        $this->ensureConnected();

        $keyPair = $this->client->generateRsaKeyPair([
            'label' => 'docutrust_' . bin2hex(random_bytes(8)),
            'modulusBits' => $bits,
            'extractable' => false, // Keys must be non-extractable for CSC
            'token' => true,
        ]);

        return [
            'publicKey' => $keyPair['publicKey'],
            'privateKeyId' => $keyPair['privateKeyId'],
            'fingerprint' => hash('sha256', $keyPair['publicKey']),
        ];
    }

    public function getPublicKey(string $keyId): string
    {
        $this->ensureConnected();

        $keyInfo = $this->client->getKeyInfo($keyId);

        return $keyInfo['publicKey'];
    }

    public function destroyKey(string $keyId): bool
    {
        $this->ensureConnected();

        return $this->client->destroyKey($keyId) === true;
    }

    public function getStatus(): array
    {
        try {
            $this->ensureConnected();

            $status = $this->client->getStatus();

            return [
                'status' => $status['online'] ? 'online' : 'offline',
                'lastCheck' => now()->toIso8601String(),
                'uptime' => $status['uptime'] ?? 0,
                'errors' => $status['errorCount'] ?? 0,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'offline',
                'lastCheck' => now()->toIso8601String(),
                'uptime' => 0,
                'errors' => 1,
            ];
        }
    }

    public function getSlotInfo(): array
    {
        $this->ensureConnected();

        $slots = $this->client->getSlotList();

        return [
            'slotCount' => count($slots),
            'availableSlots' => count($slots),
            'model' => $this->client->getHsmModel(),
            'firmwareVersion' => $this->client->getFirmwareVersion(),
        ];
    }

    private function ensureConnected(): void
    {
        if ($this->client === null) {
            $this->client = new \LunaClient([
                'partitionLabel' => $this->partitionLabel,
                'partitionPassword' => $this->partitionPassword,
            ]);

            if (!$this->client->connect()) {
                throw new RuntimeException('Failed to connect to Thales Luna HSM.');
            }
        }
    }
}
