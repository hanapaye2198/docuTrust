<?php

namespace App\Services;

use App\Contracts\HsmService;
use RuntimeException;

/**
 * AWS CloudHSM Service
 * 
 * Integration with AWS CloudHSM for FIPS 140-2 Level 3 compliance.
 * Requires AWS CloudHSM client and valid credentials.
 */
class AwsCloudHsmService implements HsmService
{
    private ?\Aws\CloudHSMV2\CloudHSMV2Client $client = null;
    private ?\Aws\Kms\KmsClient $kmsClient = null;
    private string $clusterId;

    public function __construct()
    {
        $this->clusterId = (string) config('hsm.aws.cluster_id', '');
    }

    public function sign(string $hash, string $keyId): string
    {
        $this->ensureKmsClient();

        $result = $this->kmsClient->sign([
            'KeyId' => $keyId,
            'Message' => hex2bin($hash),
            'MessageType' => 'DIGEST',
            'SigningAlgorithm' => 'RSASSA_PKCS1_V1_5_SHA_256',
        ]);

        return base64_encode($result->get('Signature'));
    }

    public function verify(string $hash, string $signature, string $keyId): bool
    {
        $this->ensureKmsClient();

        try {
            $result = $this->kmsClient->verify([
                'KeyId' => $keyId,
                'Message' => hex2bin($hash),
                'MessageType' => 'DIGEST',
                'Signature' => base64_decode($signature),
                'SigningAlgorithm' => 'RSASSA_PKCS1_V1_5_SHA_256',
            ]);

            return $result->get('SignatureValid') === true;
        } catch (\Aws\Exception\AwsException $e) {
            return false;
        }
    }

    public function generateRsaKeyPair(int $bits = 2048): array
    {
        $this->ensureKmsClient();

        $keySpec = match ($bits) {
            2048 => 'RSA_2048',
            3072 => 'RSA_3072',
            4096 => 'RSA_4096',
            default => throw new RuntimeException("Unsupported key size: {$bits} bits"),
        };

        $result = $this->kmsClient->createKey([
            'Description' => 'DocuTrust PKI Key',
            'KeySpec' => $keySpec,
            'KeyUsage' => 'SIGN_VERIFY',
            'CustomerMasterKeySpec' => 'RSA_2048',
        ]);

        $keyId = $result->get('KeyId');

        // Get public key
        $publicKeyResult = $this->kmsClient->getPublicKey([
            'KeyId' => $keyId,
        ]);

        return [
            'publicKey' => $publicKeyResult->get('PublicKey'),
            'privateKeyId' => $keyId,
            'fingerprint' => hash('sha256', $publicKeyResult->get('PublicKey')),
        ];
    }

    public function getPublicKey(string $keyId): string
    {
        $this->ensureKmsClient();

        $result = $this->kmsClient->getPublicKey([
            'KeyId' => $keyId,
        ]);

        return $result->get('PublicKey');
    }

    public function destroyKey(string $keyId): bool
    {
        $this->ensureKmsClient();

        try {
            $this->kmsClient->scheduleKeyDeletion([
                'KeyId' => $keyId,
                'PendingWindowInDays' => 7,
            ]);

            return true;
        } catch (\Aws\Exception\AwsException $e) {
            return false;
        }
    }

    public function getStatus(): array
    {
        try {
            $this->ensureClient();

            $clusterInfo = $this->client->describeCluster([
                'ClusterId' => $this->clusterId,
            ]);

            $cluster = $clusterInfo->get('Cluster');

            return [
                'status' => $this->determineStatus($cluster),
                'lastCheck' => now()->toIso8601String(),
                'uptime' => $this->calculateUptime($cluster),
                'errors' => 0,
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
        $this->ensureClient();

        $result = $this->client->describeCluster([
            'ClusterId' => $this->clusterId,
        ]);

        $cluster = $result->get('Cluster');

        return [
            'slotCount' => count($cluster['Hsms'] ?? []),
            'availableSlots' => count($cluster['Hsms'] ?? []),
            'model' => 'AWS CloudHSM',
            'firmwareVersion' => $cluster['Hsms'][0]['FirmwareVersion'] ?? 'Unknown',
        ];
    }

    private function ensureClient(): void
    {
        if ($this->client === null) {
            $region = (string) config('hsm.aws.region', 'us-east-1');

            $this->client = new \Aws\CloudHSMV2\CloudHSMV2Client([
                'version' => '2017-04-28',
                'region' => $region,
                'credentials' => [
                    'key' => (string) config('hsm.aws.access_key_id'),
                    'secret' => (string) config('hsm.aws.secret_access_key'),
                ],
            ]);
        }
    }

    private function ensureKmsClient(): void
    {
        if ($this->kmsClient === null) {
            $region = (string) config('hsm.aws.region', 'us-east-1');

            $this->kmsClient = new \Aws\Kms\KmsClient([
                'version' => '2014-11-01',
                'region' => $region,
                'credentials' => [
                    'key' => (string) config('hsm.aws.access_key_id'),
                    'secret' => (string) config('hsm.aws.secret_access_key'),
                ],
            ]);
        }
    }

    private function determineStatus(array $cluster): string
    {
        $hsms = $cluster['Hsms'] ?? [];
        $statuses = array_column($hsms, 'Status');

        if (in_array('UNINITIALIZED', $statuses)) {
            return 'offline';
        }

        if (in_array('PENDING', $statuses)) {
            return 'degraded';
        }

        return 'online';
    }

    private function calculateUptime(array $cluster): int
    {
        $hsms = $cluster['Hsms'] ?? [];
        if (empty($hsms)) {
            return 0;
        }

        $created = $hsms[0]['CreatedDate'] ?? null;
        if ($created === null) {
            return 0;
        }

        return time() - $created->getTimestamp();
    }
}
