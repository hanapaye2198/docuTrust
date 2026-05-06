<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Signature;
use App\Trust\Verification\CertificateChainValidator;

class CertificateVerificationService
{
    public function __construct(
        private readonly PkiSignatureService $pkiSignatureService,
        private readonly CertificateChainValidator $certificateChainValidator,
        private readonly TimestampEvidenceValidator $timestampEvidenceValidator,
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
     *     timestamp_verification_status: 'verified'|'failed'|'not_available',
     *     timestamp_verification_reason: string,
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
     *   timestamp_verification_status: 'verified'|'failed'|'not_available',
     *   timestamp_verification_reason: string,
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
        $timestampValidation = $this->timestampEvidenceValidator->validate(
            is_array($signature->signing_provider_payload) ? $signature->signing_provider_payload : null,
            $expectedHash
        );

        if ($certificate === null) {
            return $this->failureResult($signerName, 'Missing signer certificate.', null, $signature);
        }

        $chainValidation = $this->certificateChainValidator->validate($certificate);
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
            'timestamp_verification_status' => $timestampValidation['status'],
            'timestamp_verification_reason' => $timestampValidation['reason'],
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
     *   timestamp_verification_status: 'verified'|'failed'|'not_available',
     *   timestamp_verification_reason: string,
     *   revoked_at: string|null,
     *   revocation_reason: string|null,
     *   valid_from: string|null,
     *   valid_to: string|null
     * }
     */
    private function failureResult(string $signerName, string $reason, mixed $certificate = null, ?Signature $signature = null): array
    {
        $timestampValidation = $this->timestampEvidenceValidator->validate(
            is_array($signature?->signing_provider_payload) ? $signature->signing_provider_payload : null,
            is_string($signature?->signature_hash) ? $signature->signature_hash : ''
        );

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
            'timestamp_verification_status' => $timestampValidation['status'],
            'timestamp_verification_reason' => $timestampValidation['reason'],
            'revoked_at' => $certificate?->revoked_at?->toDateTimeString(),
            'revocation_reason' => $certificate?->revocation_reason,
            'valid_from' => $certificate?->valid_from?->toDateTimeString(),
            'valid_to' => $certificate?->valid_to?->toDateTimeString(),
        ];
    }

}
