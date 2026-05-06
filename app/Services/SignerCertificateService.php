<?php

namespace App\Services;

use App\Contracts\CertificateAuthorityKeyStore;
use App\Contracts\SignerKeyStore;
use App\Models\DocumentSigner;
use App\Models\SignerCertificate;
use DateTimeImmutable;
use RuntimeException;

class SignerCertificateService
{
    public function __construct(
        private readonly CertificateAuthorityService $certificateAuthorityService,
        private readonly CertificateAuthorityKeyStore $certificateAuthorityKeyStore,
        private readonly SignerKeyStore $signerKeyStore,
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

        if (! $this->signerKeyStore->hasKeyPair($signer)) {
            throw new RuntimeException('Signer public key is required before issuing a certificate.');
        }

        return $this->issueForSigner($signer, $authority);
    }

    public function createOrRefreshProviderManagedForSigner(
        DocumentSigner $signer,
        string $providerName,
        ?string $providerReference,
        string $certificatePem,
        string $issuerCertificatePem,
        ?string $publicKeyPem = null,
    ): SignerCertificate {
        $parsed = openssl_x509_parse($certificatePem);
        if (! is_array($parsed)) {
            throw new RuntimeException('Unable to parse provider-managed signer certificate.');
        }

        $resolvedPublicKeyPem = $publicKeyPem;
        if (! is_string($resolvedPublicKeyPem) || trim($resolvedPublicKeyPem) === '') {
            $resolvedPublicKeyPem = $this->extractPublicKeyPem($certificatePem, 'provider-managed signer');
        }

        $attributes = [
            'subject_dn' => $this->certificateAuthorityService->distinguishedNameToString($parsed['subject'] ?? []),
            'issuer_dn' => $this->certificateAuthorityService->distinguishedNameToString($parsed['issuer'] ?? []),
            'serial_number' => $this->certificateAuthorityService->parsedSerialNumber($parsed),
            'public_key_pem' => $resolvedPublicKeyPem,
            'certificate_pem' => $certificatePem,
            'issuer_certificate_pem' => $issuerCertificatePem,
            'fingerprint_sha256' => $this->certificateAuthorityService->certificateFingerprint($certificatePem),
            'valid_from' => $this->parseTimestamp($parsed['validFrom_time_t'] ?? null),
            'valid_to' => $this->parseTimestamp($parsed['validTo_time_t'] ?? null),
            'status' => 'active',
            'certificate_source' => 'provider_managed',
            'provider_name' => $providerName,
            'provider_reference' => $providerReference,
            'certificate_authority_id' => null,
            'revoked_at' => null,
            'revocation_reason' => null,
        ];

        $existing = SignerCertificate::query()
            ->where('document_signer_id', $signer->id)
            ->where('fingerprint_sha256', $attributes['fingerprint_sha256'])
            ->first();

        if ($existing !== null) {
            $existing->forceFill($attributes)->save();

            return $existing->refresh();
        }

        return SignerCertificate::query()->create([
            'document_signer_id' => $signer->id,
            ...$attributes,
        ]);
    }

    private function issueForSigner(DocumentSigner $signer, \App\Models\CertificateAuthority $authority): SignerCertificate
    {
        $caPrivateKey = openssl_pkey_get_private($this->certificateAuthorityKeyStore->privateKeyPemFor($authority));
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

        $signerKeyPair = $this->signerKeyStore->keyPairFor($signer);

        $signerPrivateKey = openssl_pkey_get_private($signerKeyPair['private_key']);
        if ($signerPrivateKey === false) {
            throw new RuntimeException('Unable to load signer private key.');
        }

        $csr = openssl_csr_new($subject, $signerPrivateKey, $this->opensslCertificateOptions());

        if ($csr === false) {
            throw new RuntimeException('Unable to create signer CSR.');
        }

        $days = (int) config('docutrust.pki.signer_valid_days', 825);
        $serialNumber = $this->certificateAuthorityService->generateCertificateSerialInteger();
        $caCertificate = openssl_x509_read((string) $authority->certificate_pem);
        if ($caCertificate === false) {
            throw new RuntimeException('Unable to read CA certificate.');
        }

        $x509 = openssl_csr_sign(
            $csr,
            $caCertificate,
            $caPrivateKey,
            $days,
            $this->opensslCertificateOptions(\App\Services\CertificateAuthorityService::PROFILE_SIGNER),
            $serialNumber
        );
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
            'serial_number' => $this->certificateAuthorityService->parsedSerialNumber($parsed),
            'public_key_pem' => $signerKeyPair['public_key'],
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

    /**
     * @return array<string, int|string>
     */
    private function opensslCertificateOptions(string $profile = CertificateAuthorityService::PROFILE_DEFAULT): array
    {
        $options = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $configPath = (string) config('docutrust.pki.openssl_config_path', '');
        if ($configPath !== '' && is_file($configPath)) {
            $options['config'] = $configPath;

            $extensionsSection = match ($profile) {
                CertificateAuthorityService::PROFILE_ROOT_CA => 'v3_ca',
                CertificateAuthorityService::PROFILE_SIGNER => 'usr_cert',
                default => null,
            };

            if ($extensionsSection !== null) {
                $options['x509_extensions'] = $extensionsSection;
            }
        }

        return $options;
    }

    private function extractPublicKeyPem(string $certificatePem, string $label): string
    {
        $publicKey = openssl_pkey_get_public($certificatePem);
        if ($publicKey === false) {
            throw new RuntimeException(sprintf('Unable to extract public key from %s certificate.', $label));
        }

        $details = openssl_pkey_get_details($publicKey);
        if ($details === false || ! isset($details['key']) || ! is_string($details['key']) || trim($details['key']) === '') {
            throw new RuntimeException(sprintf('Unable to read public key details from %s certificate.', $label));
        }

        return $details['key'];
    }
}
