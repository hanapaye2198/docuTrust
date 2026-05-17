<?php

namespace App\Services;

use App\Contracts\HsmService;
use RuntimeException;

/**
 * Utimaco HSM Service
 * 
 * Integration with Utimaco Security Server for FIPS 140-2 Level 3 compliance.
 * Requires Utimaco CS:Botan or CS:CryptoServer library.
 */
class UtimacoHsmService implements HsmService
{
    private ?\CryptokiInterface $cryptoki = null;
    private int $slotId;
    private string $userPin;

    public function __construct()
    {
        $this->slotId = (int) config('hsm.utimaco.slot_id', 0);
        $this->userPin = (string) config('hsm.utimaco.user_pin', '');
    }

    public function sign(string $hash, string $keyId): string
    {
        $this->ensureConnected();

        $signature = $this->cryptoki->C_Sign(
            $this->findObject($keyId),
            hex2bin($hash)
        );

        return base64_encode($signature);
    }

    public function verify(string $hash, string $signature, string $keyId): bool
    {
        $this->ensureConnected();

        try {
            $verified = $this->cryptoki->C_Verify(
                $this->findObject($keyId),
                hex2bin($hash),
                base64_decode($signature)
            );

            return $verified === true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function generateRsaKeyPair(int $bits = 2048): array
    {
        $this->ensureConnected();

        $template = [
            // Public key
            [
                'type' => \CKA::CKA_CLASS,
                'value' => \CKO::CKO_PUBLIC_KEY,
            ],
            [
                'type' => \CKA::CKA_KEY_TYPE,
                'value' => \CKK::CKK_RSA,
            ],
            [
                'type' => \CKA::CKA_VERIFY,
                'value' => true,
            ],
            [
                'type' => \CKA::CKA_MODULUS_BITS,
                'value' => $bits,
            ],
            [
                'type' => \CKA::CKA_PUBLIC_EXPONENT,
                'value' => [0x01, 0x00, 0x01], // 65537
            ],
            [
                'type' => \CKA::CKA_LABEL,
                'value' => 'docutrust_' . bin2hex(random_bytes(8)),
            ],

            // Private key
            [
                'type' => \CKA::CKA_CLASS,
                'value' => \CKO::CKO_PRIVATE_KEY,
            ],
            [
                'type' => \CKA::CKA_KEY_TYPE,
                'value' => \CKK::CKK_RSA,
            ],
            [
                'type' => \CKA::CKA_SIGN,
                'value' => true,
            ],
            [
                'type' => \CKA::CKA_SENSITIVE,
                'value' => true, // Must be sensitive for CSC
            ],
            [
                'type' => \CKA::CKA_EXTRACTABLE,
                'value' => false, // Must be non-extractable for CSC
            ],
        ];

        $keyPair = $this->cryptoki->C_GenerateKeyPair($template);

        return [
            'publicKey' => $keyPair['publicKey'],
            'privateKeyId' => $keyPair['privateKeyId'],
            'fingerprint' => hash('sha256', $keyPair['publicKey']),
        ];
    }

    public function getPublicKey(string $keyId): string
    {
        $this->ensureConnected();

        $object = $this->findObject($keyId);
        $attributes = $this->cryptoki->C_GetAttributeValue($object, [
            \CKA::CKA_MODULUS,
            \CKA::CKA_PUBLIC_EXPONENT,
        ]);

        // Reconstruct public key from modulus and exponent
        return $this->constructPublicKeyPem(
            $attributes[\CKA::CKA_MODULUS],
            $attributes[\CKA::CKA_PUBLIC_EXPONENT]
        );
    }

    public function destroyKey(string $keyId): bool
    {
        $this->ensureConnected();

        try {
            $object = $this->findObject($keyId);
            return $this->cryptoki->C_DestroyObject($object) === true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getStatus(): array
    {
        try {
            $this->ensureConnected();

            return [
                'status' => 'online',
                'lastCheck' => now()->toIso8601String(),
                'uptime' => $this->cryptoki->getUptime(),
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
        $this->ensureConnected();

        $slots = $this->cryptoki->getSlotList();

        return [
            'slotCount' => count($slots),
            'availableSlots' => count($slots),
            'model' => $this->cryptoki->getHsmModel(),
            'firmwareVersion' => $this->cryptoki->getFirmwareVersion(),
        ];
    }

    private function ensureConnected(): void
    {
        if ($this->cryptoki === null) {
            $libraryPath = (string) config('hsm.utimaco.library_path', '/usr/lib/libcsulutimaco.so');

            $this->cryptoki = new \CryptokiInterface($libraryPath);

            if (!$this->cryptoki->C_Initialize()) {
                throw new RuntimeException('Failed to initialize Utimaco HSM.');
            }

            if (!$this->cryptoki->C_OpenSession($this->slotId, \CKF::CKF_SERIAL_SESSION)) {
                throw new RuntimeException('Failed to open HSM session.');
            }

            if (!$this->cryptoki->C_Login(\CKU::CKU_USER, $this->userPin)) {
                throw new RuntimeException('Failed to login to HSM.');
            }
        }
    }

    private function findObject(string $keyId): int
    {
        // In production, you'd query the HSM for the object by label/ID
        // This is a simplified implementation
        return (int) $keyId;
    }

    private function constructPublicKeyPem(string $modulus, string $exponent): string
    {
        // Construct RSA public key in PEM format
        $rsa = [
            'modulus' => $modulus,
            'publicExponent' => $exponent,
        ];

        // This would use a library to construct the actual PEM
        // For now, return placeholder
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($modulus), 64) . "-----END PUBLIC KEY-----";
    }
}
