<?php

namespace App\Services;

use RuntimeException;

class PkiSignatureService
{
    /**
     * @return array{public_key: string, private_key: string, fingerprint: string}
     */
    public function generateKeyPair(): array
    {
        $resource = openssl_pkey_new($this->opensslKeyOptions());

        if ($resource === false) {
            throw new RuntimeException('Unable to generate signing key pair.');
        }

        $privateKey = '';
        $privateKeyExported = openssl_pkey_export($resource, $privateKey, null, $this->opensslKeyOptions());
        $details = openssl_pkey_get_details($resource);

        if (! $privateKeyExported || $details === false || ! isset($details['key'])) {
            throw new RuntimeException('Unable to export signing key pair.');
        }

        $publicKey = (string) $details['key'];

        return [
            'public_key' => $publicKey,
            'private_key' => $privateKey,
            'fingerprint' => $this->fingerprint($publicKey),
        ];
    }

    public function signHash(string $hashHex, string $privateKey): string
    {
        $normalizedHash = $this->normalizeHash($hashHex);
        $signature = '';

        $result = openssl_sign(hex2bin($normalizedHash), $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (! $result) {
            throw new RuntimeException('Unable to create digital signature.');
        }

        return base64_encode($signature);
    }

    public function verifySignature(string $hashHex, string $signatureBase64, string $publicKey): bool
    {
        $normalizedHash = $this->normalizeHash($hashHex);
        $signature = base64_decode($signatureBase64, true);

        if ($signature === false) {
            return false;
        }

        $result = openssl_verify(hex2bin($normalizedHash), $signature, $publicKey, OPENSSL_ALGO_SHA256);

        return $result === 1;
    }

    public function fingerprint(string $publicKey): string
    {
        return hash('sha256', $publicKey);
    }

    private function normalizeHash(string $hashHex): string
    {
        $normalizedHash = strtolower(trim($hashHex));
        if (str_starts_with($normalizedHash, '0x')) {
            $normalizedHash = substr($normalizedHash, 2);
        }

        if (! preg_match('/^[a-f0-9]{64}$/', $normalizedHash)) {
            throw new RuntimeException('Invalid SHA-256 hash provided for signing.');
        }

        return $normalizedHash;
    }

    /**
     * @return array<string, int|string>
     */
    private function opensslKeyOptions(): array
    {
        $options = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $configPath = (string) config('docutrust.pki.openssl_config_path', '');
        if ($configPath !== '' && is_file($configPath)) {
            $options['config'] = $configPath;
        }

        return $options;
    }
}
