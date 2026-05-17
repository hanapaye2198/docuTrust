<?php

namespace App\Services;

use App\Models\CertificateAuthority;
use RuntimeException;

/**
 * S/MIME Certificate Service
 *
 * Issues S/MIME certificates for email client interoperability.
 * Required by CSC for PKI-aware application compatibility with email clients.
 */
class SmimeCertificateService
{
    public function __construct(
        private readonly CertificateAuthorityService $caService,
    ) {}

    /**
     * Issue an S/MIME certificate for email signing/encryption.
     *
     * @param string $email Email address
     * @param string $commonName User's common name
     * @param string $publicKeyPem PEM-encoded public key
     * @param int $validDays Certificate validity in days
     * @return array{certificate_pem: string, serial_number: string, fingerprint: string}
     */
    public function issueCertificate(
        string $email,
        string $commonName,
        string $publicKeyPem,
        int $validDays = 825
    ): array {
        $ca = $this->caService->getOrCreateRootAuthority();

        $distinguishedName = [
            'commonName' => $commonName,
            'emailAddress' => $email,
            'organizationName' => (string) config('app.name', 'DocuTrust'),
            'countryName' => (string) config('docutrust.pki.root_ca_country', 'PH'),
        ];

        // Create CSR
        $privateKey = openssl_pkey_get_private($this->getCaPrivateKey($ca));
        if ($privateKey === false) {
            throw new RuntimeException('Unable to load CA private key.');
        }

        $subjectKey = openssl_pkey_get_public($publicKeyPem);
        if ($subjectKey === false) {
            throw new RuntimeException('Invalid public key for S/MIME certificate.');
        }

        $config = $this->getSmimeConfig();
        $csr = openssl_csr_new($distinguishedName, $subjectKey, $config);

        if ($csr === false) {
            throw new RuntimeException('Unable to create S/MIME CSR.');
        }

        $serialNumber = $this->caService->generateCertificateSerialInteger();
        $caCert = openssl_x509_read($ca->certificate_pem);

        $x509 = openssl_csr_sign(
            $csr,
            $caCert,
            $privateKey,
            $validDays,
            $config,
            $serialNumber
        );

        if ($x509 === false) {
            throw new RuntimeException('Unable to sign S/MIME certificate.');
        }

        $certificatePem = '';
        if (!openssl_x509_export($x509, $certificatePem)) {
            throw new RuntimeException('Unable to export S/MIME certificate.');
        }

        return [
            'certificate_pem' => $certificatePem,
            'serial_number' => $this->caService->parsedSerialNumber(openssl_x509_parse($certificatePem)),
            'fingerprint' => $this->caService->certificateFingerprint($certificatePem),
        ];
    }

    /**
     * Verify an S/MIME signed message.
     *
     * @param string $signedMessage PKCS#7 signed message
     * @param string $caCertificatePath Path to CA certificate file
     * @return array{verified: bool, signer_email: string|null, message: string}
     */
    public function verifySignedMessage(string $signedMessage, string $caCertificatePath): array
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'smime_');
        file_put_contents($tempFile, $signedMessage);

        $result = openssl_pkcs7_verify($tempFile, 0, $caCertificatePath);

        unlink($tempFile);

        if ($result === true) {
            return [
                'verified' => true,
                'signer_email' => null, // Would need to extract from cert
                'message' => 'S/MIME signature is valid.',
            ];
        }

        return [
            'verified' => false,
            'signer_email' => null,
            'message' => 'S/MIME signature verification failed.',
        ];
    }

    /**
     * Get OpenSSL config for S/MIME certificates.
     *
     * @return array
     */
    private function getSmimeConfig(): array
    {
        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $configPath = (string) config('docutrust.pki.openssl_config_path', '');
        if ($configPath !== '' && is_file($configPath)) {
            $config['config'] = $configPath;
            $config['x509_extensions'] = 'smime_cert'; // S/MIME profile
        }

        return $config;
    }

    /**
     * Get CA private key.
     */
    private function getCaPrivateKey(CertificateAuthority $ca): string
    {
        $keyStore = app(\App\Contracts\CertificateAuthorityKeyStore::class);
        return $keyStore->privateKeyPemFor($ca);
    }
}
