<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Signature;

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
            'signatures.signerCertificate',
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
            return $this->failureResult($signerName, 'Missing signer certificate.');
        }

        if ($certificate->revoked_at !== null || $certificate->status === 'revoked') {
            $reason = 'Certificate has been revoked.';

            if (is_string($certificate->revocation_reason) && $certificate->revocation_reason !== '') {
                $reason .= ' Reason: '.$certificate->revocation_reason;
            }

            return $this->failureResult($signerName, $reason, $certificate);
        }

        if ($certificate->status !== 'active') {
            return $this->failureResult($signerName, 'Certificate is not active.', $certificate);
        }

        if ($certificate->valid_from !== null && $certificate->valid_from->isFuture()) {
            return $this->failureResult($signerName, 'Certificate is not yet valid.', $certificate);
        }

        if ($certificate->valid_to !== null && $certificate->valid_to->isPast()) {
            return $this->failureResult($signerName, 'Certificate has expired.', $certificate);
        }

        if (! is_string($signature->signature_hash) || $signature->signature_hash === '') {
            return $this->failureResult($signerName, 'Stored signature hash is missing.', $certificate);
        }

        if (! hash_equals(strtolower($signature->signature_hash), strtolower($expectedHash))) {
            return $this->failureResult($signerName, 'Signature hash does not match document hash.', $certificate);
        }

        if (! is_string($signature->signature_value) || $signature->signature_value === '') {
            return $this->failureResult($signerName, 'Stored signature value is missing.', $certificate);
        }

        if (! $this->pkiSignatureService->verifySignature($expectedHash, $signature->signature_value, $certificate->public_key_pem)) {
            return $this->failureResult($signerName, 'Digital signature verification failed.', $certificate);
        }

        return [
            'signer_name' => $signerName,
            'result' => 'verified',
            'reason' => 'Signature and certificate are valid.',
            'certificate_status' => $certificate->status,
            'certificate_fingerprint' => $certificate->fingerprint_sha256,
            'issuer_dn' => $certificate->issuer_dn,
            'serial_number' => $certificate->serial_number,
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
     *   revoked_at: string|null,
     *   revocation_reason: string|null,
     *   valid_from: string|null,
     *   valid_to: string|null
     * }
     */
    private function failureResult(string $signerName, string $reason, mixed $certificate = null): array
    {
        return [
            'signer_name' => $signerName,
            'result' => 'failed',
            'reason' => $reason,
            'certificate_status' => $certificate?->status,
            'certificate_fingerprint' => $certificate?->fingerprint_sha256,
            'issuer_dn' => $certificate?->issuer_dn,
            'serial_number' => $certificate?->serial_number,
            'revoked_at' => $certificate?->revoked_at?->toDateTimeString(),
            'revocation_reason' => $certificate?->revocation_reason,
            'valid_from' => $certificate?->valid_from?->toDateTimeString(),
            'valid_to' => $certificate?->valid_to?->toDateTimeString(),
        ];
    }
}
