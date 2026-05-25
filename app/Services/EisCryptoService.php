<?php

namespace App\Services;

use Firebase\JWT\JWT;
use RuntimeException;

class EisCryptoService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function encryptAuthenticationPayload(array $payload): string
    {
        $plaintext = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $publicKey = openssl_pkey_get_public($this->publicKeyContents());
        $encrypted = '';

        if ($publicKey === false) {
            throw new RuntimeException('Unable to read the configured EIS public key.');
        }

        if (! openssl_public_encrypt($plaintext, $encrypted, $publicKey, $this->rsaPadding())) {
            throw new RuntimeException('Unable to encrypt the EIS authentication payload.');
        }

        return base64_encode($encrypted);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function signInvoicePayload(array $payload): string
    {
        return JWT::encode(
            $payload,
            $this->privateKeyContents(),
            'RS256',
            null,
            ['typ' => 'JWT']
        );
    }

    /**
     * @return array{data:string,iv:string}
     */
    public function encryptSubmissionPayload(string $payload, string $sessionKey): array
    {
        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt(
            $payload,
            'AES-256-CBC',
            $this->normalizeSessionKey($sessionKey),
            OPENSSL_RAW_DATA,
            $iv
        );

        if (! is_string($ciphertext)) {
            throw new RuntimeException('Unable to encrypt the EIS invoice payload.');
        }

        return [
            'data' => base64_encode($ciphertext),
            'iv' => base64_encode($iv),
        ];
    }

    public function generateSessionKey(): string
    {
        return base64_encode(random_bytes(32));
    }

    public function makeAuthorizationSignature(string $timestamp, string $body, string $secret): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    private function publicKeyContents(): string
    {
        $path = trim((string) config('services.eis.public_key_path', ''));
        if ($path === '' || ! is_file($path)) {
            throw new RuntimeException('EIS public key path is not configured or the file does not exist.');
        }

        $contents = file_get_contents($path);
        if (! is_string($contents) || trim($contents) === '') {
            throw new RuntimeException('EIS public key file is empty or unreadable.');
        }

        return $contents;
    }

    private function privateKeyContents(): string
    {
        $path = trim((string) config('services.eis.signing_private_key_path', ''));
        if ($path === '' || ! is_file($path)) {
            throw new RuntimeException('EIS signing private key path is not configured or the file does not exist.');
        }

        $contents = file_get_contents($path);
        if (! is_string($contents) || trim($contents) === '') {
            throw new RuntimeException('EIS signing private key file is empty or unreadable.');
        }

        return $contents;
    }

    private function rsaPadding(): int
    {
        return match (strtolower(trim((string) config('services.eis.rsa_padding', 'pkcs1')))) {
            'oaep' => OPENSSL_PKCS1_OAEP_PADDING,
            default => OPENSSL_PKCS1_PADDING,
        };
    }

    private function normalizeSessionKey(string $sessionKey): string
    {
        $decoded = base64_decode($sessionKey, true);
        if (is_string($decoded) && strlen($decoded) >= 32) {
            return substr($decoded, 0, 32);
        }

        return substr(hash('sha256', $sessionKey, true), 0, 32);
    }
}
