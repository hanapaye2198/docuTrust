<?php

namespace App\Services;

use App\Models\CertificateAuthority;
use DateTimeImmutable;
use RuntimeException;

class CertificateAuthorityService
{
    public function getOrCreateRootAuthority(): CertificateAuthority
    {
        $authority = CertificateAuthority::query()
            ->where('is_root', true)
            ->where('status', 'active')
            ->first();

        if ($authority !== null) {
            return $authority;
        }

        return $this->createRootAuthority();
    }

    /**
     * @return array{
     *   serial_number: string,
     *   public_key_pem: string,
     *   private_key_pem: string,
     *   certificate_pem: string,
     *   fingerprint_sha256: string,
     *   valid_from: \DateTimeImmutable,
     *   valid_to: \DateTimeImmutable,
     *   subject_dn: string,
     *   issuer_dn: string
     * }
     */
    public function createSelfSignedAuthorityCertificate(): array
    {
        $name = (string) config('docutrust.pki.root_ca_name', 'DocuTrust Root CA');
        $distinguishedName = [
            'commonName' => $name,
            'organizationName' => (string) config('app.name', 'DocuTrust'),
            'organizationalUnitName' => 'PKI',
            'countryName' => (string) config('docutrust.pki.root_ca_country', 'PH'),
        ];

        $keyPair = $this->generateKeyPair();
        $privateKeyResource = openssl_pkey_get_private($keyPair['private_key_pem']);
        if ($privateKeyResource === false) {
            throw new RuntimeException('Unable to load generated root CA private key.');
        }

        $csr = openssl_csr_new($distinguishedName, $privateKeyResource, $this->opensslCertificateOptions());

        if ($csr === false) {
            throw new RuntimeException('Unable to create root CA CSR.');
        }

        $days = (int) config('docutrust.pki.root_ca_valid_days', 3650);
        $serialNumber = $this->generateSerialNumber();
        $x509 = openssl_csr_sign($csr, null, $privateKeyResource, $days, $this->opensslCertificateOptions(), $this->certificateSerialInteger($serialNumber));

        if ($x509 === false) {
            throw new RuntimeException('Unable to sign root CA certificate.');
        }

        $certificatePem = '';
        if (! openssl_x509_export($x509, $certificatePem)) {
            throw new RuntimeException('Unable to export root CA certificate.');
        }

        $parsed = openssl_x509_parse($certificatePem);
        if (! is_array($parsed)) {
            throw new RuntimeException('Unable to parse root CA certificate.');
        }

        return [
            'serial_number' => $serialNumber,
            'public_key_pem' => $keyPair['public_key_pem'],
            'private_key_pem' => $keyPair['private_key_pem'],
            'certificate_pem' => $certificatePem,
            'fingerprint_sha256' => $this->certificateFingerprint($certificatePem),
            'valid_from' => $this->parseTimestamp($parsed['validFrom_time_t'] ?? null),
            'valid_to' => $this->parseTimestamp($parsed['validTo_time_t'] ?? null),
            'subject_dn' => $this->distinguishedNameToString($parsed['subject'] ?? []),
            'issuer_dn' => $this->distinguishedNameToString($parsed['issuer'] ?? []),
        ];
    }

    /**
     * @return array{public_key_pem: string, private_key_pem: string}
     */
    public function generateKeyPair(): array
    {
        $resource = openssl_pkey_new($this->opensslCertificateOptions());

        if ($resource === false) {
            throw new RuntimeException('Unable to generate PKI key pair.');
        }

        $privateKeyPem = '';
        $privateKeyExported = openssl_pkey_export($resource, $privateKeyPem, null, $this->opensslCertificateOptions());
        $details = openssl_pkey_get_details($resource);

        if (! $privateKeyExported || $details === false || ! isset($details['key'])) {
            throw new RuntimeException('Unable to export PKI key pair.');
        }

        return [
            'public_key_pem' => (string) $details['key'],
            'private_key_pem' => $privateKeyPem,
        ];
    }

    public function generateSerialNumber(): string
    {
        return strtoupper(bin2hex(random_bytes(16)));
    }

    public function distinguishedNameToString(array $distinguishedName): string
    {
        $parts = [];

        foreach ($distinguishedName as $key => $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $parts[] = sprintf('%s=%s', $key, (string) $value);
        }

        return implode(', ', $parts);
    }

    public function certificateFingerprint(string $certificatePem): string
    {
        $normalized = preg_replace('/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s+/', '', $certificatePem);

        if (! is_string($normalized) || $normalized === '') {
            throw new RuntimeException('Unable to normalize certificate for fingerprinting.');
        }

        $der = base64_decode($normalized, true);
        if ($der === false) {
            throw new RuntimeException('Unable to decode certificate for fingerprinting.');
        }

        return hash('sha256', $der);
    }

    private function createRootAuthority(): CertificateAuthority
    {
        $certificate = $this->createSelfSignedAuthorityCertificate();

        return CertificateAuthority::query()->create([
            'name' => (string) config('docutrust.pki.root_ca_name', 'DocuTrust Root CA'),
            'subject_dn' => $certificate['subject_dn'],
            'issuer_dn' => $certificate['issuer_dn'],
            'serial_number' => $certificate['serial_number'],
            'public_key_pem' => $certificate['public_key_pem'],
            'private_key_pem' => $certificate['private_key_pem'],
            'certificate_pem' => $certificate['certificate_pem'],
            'fingerprint_sha256' => $certificate['fingerprint_sha256'],
            'valid_from' => $certificate['valid_from'],
            'valid_to' => $certificate['valid_to'],
            'is_root' => true,
            'status' => 'active',
        ]);
    }

    private function parseTimestamp(mixed $timestamp): DateTimeImmutable
    {
        if (! is_int($timestamp)) {
            throw new RuntimeException('Certificate validity timestamp missing.');
        }

        return (new DateTimeImmutable())->setTimestamp($timestamp);
    }

    private function certificateSerialInteger(string $serialNumber): int
    {
        $prefix = substr($serialNumber, 0, 7);
        $value = hexdec($prefix);

        if (! is_int($value) || $value <= 0) {
            return 1;
        }

        return $value;
    }

    /**
     * @return array<string, int|string>
     */
    private function opensslCertificateOptions(): array
    {
        $options = [
            'digest_alg' => 'sha256',
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
