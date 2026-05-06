<?php

namespace App\Trust\Verification;

use App\Models\SignerCertificate;
use RuntimeException;

class CertificateChainValidator
{
    public function validate(SignerCertificate $certificate): ?string
    {
        return $certificate->certificate_source === 'provider_managed'
            ? $this->validateProviderManagedCertificateChain($certificate)
            : $this->validateAppManagedCertificateChain($certificate);
    }

    private function validateAppManagedCertificateChain(SignerCertificate $certificate): ?string
    {
        $authority = $certificate->certificateAuthority;
        if ($authority === null) {
            return 'Missing issuing certificate authority.';
        }

        if ($authority->status !== 'active') {
            return 'Issuing certificate authority is not active.';
        }

        if ($authority->valid_from !== null && $authority->valid_from->isFuture()) {
            return 'Issuing certificate authority is not yet valid.';
        }

        if ($authority->valid_to !== null && $authority->valid_to->isPast()) {
            return 'Issuing certificate authority has expired.';
        }

        if (! is_string($certificate->certificate_pem) || trim($certificate->certificate_pem) === '') {
            return 'Stored signer certificate PEM is missing.';
        }

        if (! is_string($authority->certificate_pem) || trim($authority->certificate_pem) === '') {
            return 'Stored issuing certificate authority PEM is missing.';
        }

        try {
            $parsedCertificate = $this->parseCertificate($certificate->certificate_pem, 'signer');
            $parsedAuthority = $this->parseCertificate($authority->certificate_pem, 'certificate authority');
        } catch (RuntimeException $exception) {
            return $exception->getMessage();
        }

        $parsedSubject = $this->distinguishedNameToString($parsedCertificate['subject'] ?? []);
        if ($parsedSubject === '' || ! hash_equals($certificate->subject_dn, $parsedSubject)) {
            return 'Stored signer certificate subject does not match certificate contents.';
        }

        $parsedSerial = $this->parsedSerialNumber($parsedCertificate);
        if ($parsedSerial === '' || ! hash_equals($certificate->serial_number, $parsedSerial)) {
            return 'Stored signer certificate serial number does not match certificate contents.';
        }

        $parsedIssuer = $this->distinguishedNameToString($parsedCertificate['issuer'] ?? []);
        if ($parsedIssuer === '' || ! hash_equals($certificate->issuer_dn, $parsedIssuer)) {
            return 'Stored signer certificate issuer does not match certificate contents.';
        }

        $authoritySubject = $this->distinguishedNameToString($parsedAuthority['subject'] ?? []);
        if ($authoritySubject === '' || ! hash_equals($authority->subject_dn, $authoritySubject)) {
            return 'Stored certificate authority subject does not match certificate contents.';
        }

        if (! hash_equals($parsedIssuer, $authoritySubject)) {
            return 'Signer certificate issuer does not match the issuing certificate authority subject.';
        }

        $authoritySerial = $this->parsedSerialNumber($parsedAuthority);
        if ($authoritySerial === '' || ! hash_equals($authority->serial_number, $authoritySerial)) {
            return 'Stored certificate authority serial number does not match certificate contents.';
        }

        if (! $this->isCertificateAuthority($parsedAuthority)) {
            return 'Issuing certificate authority certificate is missing CA constraints.';
        }

        if ($this->isCertificateAuthority($parsedCertificate)) {
            return 'Signer certificate is incorrectly marked as a certificate authority.';
        }

        $certificatePublicKey = $this->extractPublicKeyPem($certificate->certificate_pem, 'signer');
        if (! hash_equals(trim($certificate->public_key_pem), trim($certificatePublicKey))) {
            return 'Stored signer public key does not match the signer certificate public key.';
        }

        $authorityPublicKey = $this->extractPublicKeyPem($authority->certificate_pem, 'certificate authority');
        if (! hash_equals(trim($authority->public_key_pem), trim($authorityPublicKey))) {
            return 'Stored certificate authority public key does not match the certificate authority certificate public key.';
        }

        $x509 = openssl_x509_read($certificate->certificate_pem);
        if ($x509 === false) {
            return 'Unable to read signer certificate for trust validation.';
        }

        return openssl_x509_verify($x509, $authorityPublicKey) === 1
            ? null
            : 'Signer certificate signature could not be verified against the issuing certificate authority.';
    }

    private function validateProviderManagedCertificateChain(SignerCertificate $certificate): ?string
    {
        if (! is_string($certificate->certificate_pem) || trim($certificate->certificate_pem) === '') {
            return 'Stored signer certificate PEM is missing.';
        }

        if (! is_string($certificate->issuer_certificate_pem) || trim($certificate->issuer_certificate_pem) === '') {
            return 'Stored issuer certificate PEM is missing for provider-managed signing.';
        }

        try {
            $parsedCertificate = $this->parseCertificate($certificate->certificate_pem, 'provider-managed signer');
            $parsedIssuer = $this->parseCertificate($certificate->issuer_certificate_pem, 'provider-managed issuer');
        } catch (RuntimeException $exception) {
            return $exception->getMessage();
        }

        $parsedSubject = $this->distinguishedNameToString($parsedCertificate['subject'] ?? []);
        if ($parsedSubject === '' || ! hash_equals($certificate->subject_dn, $parsedSubject)) {
            return 'Stored signer certificate subject does not match certificate contents.';
        }

        $parsedSerial = $this->parsedSerialNumber($parsedCertificate);
        if ($parsedSerial === '' || ! hash_equals($certificate->serial_number, $parsedSerial)) {
            return 'Stored signer certificate serial number does not match certificate contents.';
        }

        $parsedIssuerDn = $this->distinguishedNameToString($parsedCertificate['issuer'] ?? []);
        if ($parsedIssuerDn === '' || ! hash_equals($certificate->issuer_dn, $parsedIssuerDn)) {
            return 'Stored signer certificate issuer does not match certificate contents.';
        }

        $issuerSubjectDn = $this->distinguishedNameToString($parsedIssuer['subject'] ?? []);
        if ($issuerSubjectDn === '' || ! hash_equals($parsedIssuerDn, $issuerSubjectDn)) {
            return 'Provider-managed signer certificate issuer does not match the issuer certificate subject.';
        }

        if (! $this->isCertificateAuthority($parsedIssuer)) {
            return 'Provider-managed issuer certificate is missing CA constraints.';
        }

        if ($this->isCertificateAuthority($parsedCertificate)) {
            return 'Signer certificate is incorrectly marked as a certificate authority.';
        }

        $certificatePublicKey = $this->extractPublicKeyPem($certificate->certificate_pem, 'provider-managed signer');
        if (! hash_equals(trim($certificate->public_key_pem), trim($certificatePublicKey))) {
            return 'Stored signer public key does not match the signer certificate public key.';
        }

        $issuerPublicKey = $this->extractPublicKeyPem($certificate->issuer_certificate_pem, 'provider-managed issuer');
        $x509 = openssl_x509_read($certificate->certificate_pem);
        if ($x509 === false) {
            return 'Unable to read signer certificate for trust validation.';
        }

        return openssl_x509_verify($x509, $issuerPublicKey) === 1
            ? null
            : 'Signer certificate signature could not be verified against the provider-managed issuer certificate.';
    }

    /**
     * @return array<string, mixed>
     */
    private function parseCertificate(string $certificatePem, string $label): array
    {
        $parsed = openssl_x509_parse($certificatePem);

        if (! is_array($parsed)) {
            throw new RuntimeException(sprintf('Unable to parse %s certificate.', $label));
        }

        return $parsed;
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

    private function distinguishedNameToString(array $distinguishedName): string
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

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function parsedSerialNumber(array $parsed): string
    {
        $serialNumberHex = $parsed['serialNumberHex'] ?? null;
        if (is_string($serialNumberHex) && trim($serialNumberHex) !== '') {
            return strtoupper(trim($serialNumberHex));
        }

        $serialNumber = $parsed['serialNumber'] ?? null;
        if (is_scalar($serialNumber)) {
            return (string) $serialNumber;
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function isCertificateAuthority(array $parsed): bool
    {
        $extensions = $parsed['extensions'] ?? null;
        if (! is_array($extensions)) {
            return false;
        }

        $basicConstraints = $extensions['basicConstraints'] ?? null;
        if (! is_string($basicConstraints)) {
            return false;
        }

        return str_contains(strtoupper($basicConstraints), 'CA:TRUE');
    }
}
