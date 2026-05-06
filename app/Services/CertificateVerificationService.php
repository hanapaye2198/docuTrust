<?php

namespace App\Services;

use App\Models\Document;
use App\Models\SignerCertificate;
use App\Models\Signature;
use RuntimeException;

class CertificateVerificationService
{
    public function __construct(
        private readonly PkiSignatureService $pkiSignatureService,
    ) {}

    /**
     * @return array{
     *   status: 'verified'|'failed'|'not_available',
     *   all_valid: bool,
     *   verified_signatures: int,
     *   failed_signatures: int,
     *   message: string,
     *   details: array<int, array{
     *     signer_name: string,
     *     result: 'verified'|'failed',
     *     reason: string,
     *     certificate_status: string|null,
     *     certificate_fingerprint: string|null,
     *     issuer_dn: string|null,
     *     serial_number: string|null,
     *     signing_provider: string|null,
     *     signing_provider_reference: string|null,
     *     signing_provider_payload: array<string, mixed>|null,
     *     revoked_at: string|null,
     *     revocation_reason: string|null,
     *     valid_from: string|null,
     *     valid_to: string|null
     *   }>
     * }
     */
    public function verifyDocumentSignatures(Document $document, string $expectedHash): array
    {
        $document->loadMissing([
            'signatures.signerCertificate.certificateAuthority',
            'signatures.signer',
        ]);

        $pkiSignatures = $document->signatures
            ->filter(fn (Signature $signature): bool => is_string($signature->signature_value) && $signature->signature_value !== '');

        if ($pkiSignatures->isEmpty()) {
            return [
                'status' => 'not_available',
                'all_valid' => false,
                'verified_signatures' => 0,
                'failed_signatures' => 0,
                'message' => 'No PKI signatures found for this document.',
                'details' => [],
            ];
        }

        $details = [];
        $verified = 0;
        $failed = 0;

        foreach ($pkiSignatures as $signature) {
            $result = $this->verifySignature($signature, $expectedHash);
            $details[] = $result;

            if ($result['result'] === 'verified') {
                $verified++;
            } else {
                $failed++;
            }
        }

        return [
            'status' => $failed === 0 ? 'verified' : 'failed',
            'all_valid' => $failed === 0,
            'verified_signatures' => $verified,
            'failed_signatures' => $failed,
            'message' => $failed === 0
                ? 'All PKI signatures are valid.'
                : 'One or more PKI signatures failed certificate verification.',
            'details' => $details,
        ];
    }

    /**
     * @return array{
     *   signer_name: string,
     *   result: 'verified'|'failed',
     *   reason: string,
     *   certificate_status: string|null,
     *   certificate_fingerprint: string|null,
     *   issuer_dn: string|null,
     *   serial_number: string|null,
     *   signing_provider: string|null,
     *   signing_provider_reference: string|null,
     *   signing_provider_payload: array<string, mixed>|null,
     *   revoked_at: string|null,
     *   revocation_reason: string|null,
     *   valid_from: string|null,
     *   valid_to: string|null
     * }
     */
    private function verifySignature(Signature $signature, string $expectedHash): array
    {
        $certificate = $signature->signerCertificate;
        $signerName = $signature->signer?->name ?? 'Unknown signer';

        if ($certificate === null) {
            return $this->failureResult($signerName, 'Missing signer certificate.', null, $signature);
        }

        $chainValidation = $this->validateCertificateChain($certificate);
        if ($chainValidation !== null) {
            return $this->failureResult($signerName, $chainValidation, $certificate, $signature);
        }

        if ($certificate->revoked_at !== null || $certificate->status === 'revoked') {
            $reason = 'Certificate has been revoked.';

            if (is_string($certificate->revocation_reason) && $certificate->revocation_reason !== '') {
                $reason .= ' Reason: '.$certificate->revocation_reason;
            }

            return $this->failureResult($signerName, $reason, $certificate, $signature);
        }

        if ($certificate->status !== 'active') {
            return $this->failureResult($signerName, 'Certificate is not active.', $certificate, $signature);
        }

        if ($certificate->valid_from !== null && $certificate->valid_from->isFuture()) {
            return $this->failureResult($signerName, 'Certificate is not yet valid.', $certificate, $signature);
        }

        if ($certificate->valid_to !== null && $certificate->valid_to->isPast()) {
            return $this->failureResult($signerName, 'Certificate has expired.', $certificate, $signature);
        }

        if (! is_string($signature->signature_hash) || $signature->signature_hash === '') {
            return $this->failureResult($signerName, 'Stored signature hash is missing.', $certificate, $signature);
        }

        if (! hash_equals(strtolower($signature->signature_hash), strtolower($expectedHash))) {
            return $this->failureResult($signerName, 'Signature hash does not match document hash.', $certificate, $signature);
        }

        if (! is_string($signature->signature_value) || $signature->signature_value === '') {
            return $this->failureResult($signerName, 'Stored signature value is missing.', $certificate, $signature);
        }

        if (! is_string($signature->public_key_fingerprint) || $signature->public_key_fingerprint === '') {
            return $this->failureResult($signerName, 'Stored public key fingerprint is missing.', $certificate, $signature);
        }

        if (! hash_equals(
            strtolower($signature->public_key_fingerprint),
            strtolower($this->pkiSignatureService->fingerprint($certificate->public_key_pem))
        )) {
            return $this->failureResult($signerName, 'Stored public key fingerprint does not match certificate public key.', $certificate, $signature);
        }

        if (! $this->pkiSignatureService->verifySignature($expectedHash, $signature->signature_value, $certificate->public_key_pem)) {
            return $this->failureResult($signerName, 'Digital signature verification failed.', $certificate, $signature);
        }

        return [
            'signer_name' => $signerName,
            'result' => 'verified',
            'reason' => 'Signature and certificate are valid.',
            'certificate_status' => $certificate->status,
            'certificate_fingerprint' => $certificate->fingerprint_sha256,
            'issuer_dn' => $certificate->issuer_dn,
            'serial_number' => $certificate->serial_number,
            'signing_provider' => $signature->signing_provider,
            'signing_provider_reference' => $signature->signing_provider_reference,
            'signing_provider_payload' => is_array($signature->signing_provider_payload) ? $signature->signing_provider_payload : null,
            'revoked_at' => $certificate->revoked_at?->toDateTimeString(),
            'revocation_reason' => $certificate->revocation_reason,
            'valid_from' => $certificate->valid_from?->toDateTimeString(),
            'valid_to' => $certificate->valid_to?->toDateTimeString(),
        ];
    }

    /**
     * @return array{
     *   signer_name: string,
     *   result: 'failed',
     *   reason: string,
     *   certificate_status: string|null,
     *   certificate_fingerprint: string|null,
     *   issuer_dn: string|null,
     *   serial_number: string|null,
     *   signing_provider: string|null,
     *   signing_provider_reference: string|null,
     *   signing_provider_payload: array<string, mixed>|null,
     *   revoked_at: string|null,
     *   revocation_reason: string|null,
     *   valid_from: string|null,
     *   valid_to: string|null
     * }
     */
    private function failureResult(string $signerName, string $reason, mixed $certificate = null, ?Signature $signature = null): array
    {
        return [
            'signer_name' => $signerName,
            'result' => 'failed',
            'reason' => $reason,
            'certificate_status' => $certificate?->status,
            'certificate_fingerprint' => $certificate?->fingerprint_sha256,
            'issuer_dn' => $certificate?->issuer_dn,
            'serial_number' => $certificate?->serial_number,
            'signing_provider' => $signature?->signing_provider,
            'signing_provider_reference' => $signature?->signing_provider_reference,
            'signing_provider_payload' => is_array($signature?->signing_provider_payload) ? $signature->signing_provider_payload : null,
            'revoked_at' => $certificate?->revoked_at?->toDateTimeString(),
            'revocation_reason' => $certificate?->revocation_reason,
            'valid_from' => $certificate?->valid_from?->toDateTimeString(),
            'valid_to' => $certificate?->valid_to?->toDateTimeString(),
        ];
    }

    private function validateCertificateChain(SignerCertificate $certificate): ?string
    {
        if ($certificate->certificate_source === 'provider_managed') {
            return $this->validateProviderManagedCertificateChain($certificate);
        }

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

        $verification = openssl_x509_verify($x509, $authorityPublicKey);

        if ($verification !== 1) {
            return 'Signer certificate signature could not be verified against the issuing certificate authority.';
        }

        return null;
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

        $verification = openssl_x509_verify($x509, $issuerPublicKey);

        if ($verification !== 1) {
            return 'Signer certificate signature could not be verified against the provider-managed issuer certificate.';
        }

        return null;
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
