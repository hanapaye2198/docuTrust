<?php

namespace App\Services;

use App\Models\DocumentSigner;
use App\Models\SignerCertificate;
use DateTimeImmutable;
use RuntimeException;

class SignerCertificateService
{
    public function __construct(
        private readonly CertificateAuthorityService $certificateAuthorityService,
    ) {}

    public function getOrIssueForSigner(DocumentSigner $signer): SignerCertificate
    {
        $authority = $this->certificateAuthorityService->getOrCreateRootAuthority();

        $certificate = SignerCertificate::query()
            ->where('document_signer_id', $signer->id)
            ->where('certificate_authority_id', $authority->id)
            ->where('status', 'active')
            ->first();

        if ($certificate !== null) {
            return $certificate;
        }

        if (! is_string($signer->signing_public_key) || $signer->signing_public_key === '') {
            throw new RuntimeException('Signer public key is required before issuing a certificate.');
        }

        return $this->issueForSigner($signer, $authority);
    }

    private function issueForSigner(DocumentSigner $signer, \App\Models\CertificateAuthority $authority): SignerCertificate
    {
        $caPrivateKey = openssl_pkey_get_private((string) $authority->private_key_pem);
        if ($caPrivateKey === false) {
            throw new RuntimeException('Unable to load CA private key.');
        }

        $subject = [
            'commonName' => $signer->name,
            'emailAddress' => $signer->email,
            'organizationName' => (string) config('app.name', 'DocuTrust'),
            'organizationalUnitName' => 'Signer',
            'countryName' => (string) config('docutrust.pki.root_ca_country', 'PH'),
        ];

        $signerPrivateKey = openssl_pkey_get_private((string) $signer->signing_private_key);
        if ($signerPrivateKey === false) {
            throw new RuntimeException('Unable to load signer private key.');
        }

        $csr = openssl_csr_new($subject, $signerPrivateKey, $this->opensslCertificateOptions());

        if ($csr === false) {
            throw new RuntimeException('Unable to create signer CSR.');
        }

        $days = (int) config('docutrust.pki.signer_valid_days', 825);
        $serialNumber = $this->certificateAuthorityService->generateSerialNumber();
        $caCertificate = openssl_x509_read((string) $authority->certificate_pem);
        if ($caCertificate === false) {
            throw new RuntimeException('Unable to read CA certificate.');
        }

        $x509 = openssl_csr_sign($csr, $caCertificate, $caPrivateKey, $days, $this->opensslCertificateOptions(), $this->certificateSerialInteger($serialNumber));
        if ($x509 === false) {
            throw new RuntimeException('Unable to sign signer certificate.');
        }

        $certificatePem = '';
        if (! openssl_x509_export($x509, $certificatePem)) {
            throw new RuntimeException('Unable to export signer certificate.');
        }

        $parsed = openssl_x509_parse($certificatePem);
        if (! is_array($parsed)) {
            throw new RuntimeException('Unable to parse signer certificate.');
        }

        return SignerCertificate::query()->create([
            'document_signer_id' => $signer->id,
            'certificate_authority_id' => $authority->id,
            'subject_dn' => $this->certificateAuthorityService->distinguishedNameToString($parsed['subject'] ?? []),
            'issuer_dn' => $this->certificateAuthorityService->distinguishedNameToString($parsed['issuer'] ?? []),
            'serial_number' => $serialNumber,
            'public_key_pem' => (string) $signer->signing_public_key,
            'certificate_pem' => $certificatePem,
            'fingerprint_sha256' => $this->certificateAuthorityService->certificateFingerprint($certificatePem),
            'valid_from' => $this->parseTimestamp($parsed['validFrom_time_t'] ?? null),
            'valid_to' => $this->parseTimestamp($parsed['validTo_time_t'] ?? null),
            'status' => 'active',
        ]);
    }

    private function parseTimestamp(mixed $timestamp): DateTimeImmutable
    {
        if (! is_int($timestamp)) {
            throw new RuntimeException('Signer certificate validity timestamp missing.');
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
