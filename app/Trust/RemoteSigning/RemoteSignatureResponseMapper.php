<?php

namespace App\Trust\RemoteSigning;

use RuntimeException;

class RemoteSignatureResponseMapper
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function map(array $payload): RemoteSignatureMaterial
    {
        $signatureValue = $this->extractSignatureValue($payload);
        $certificatePem = $this->extractSignerCertificatePem($payload);
        $issuerCertificatePem = $this->extractIssuerCertificatePem($payload);
        $providerReference = $this->extractProviderReference($payload);
        $signatureAlgorithm = $payload['signature_algorithm'] ?? $payload['signAlgo'] ?? 'RSA-SHA256';
        $publicKeyPem = $payload['public_key_pem'] ?? null;

        if (! is_string($signatureValue) || trim($signatureValue) === '') {
            throw new RuntimeException('Remote signing provider did not return a signature value.');
        }

        if (! is_string($certificatePem) || trim($certificatePem) === '') {
            throw new RuntimeException('Remote signing provider did not return a signer certificate.');
        }

        if (! is_string($issuerCertificatePem) || trim($issuerCertificatePem) === '') {
            throw new RuntimeException('Remote signing provider did not return an issuer certificate.');
        }

        return new RemoteSignatureMaterial(
            signatureValue: $signatureValue,
            certificatePem: $certificatePem,
            issuerCertificatePem: $issuerCertificatePem,
            providerReference: is_string($providerReference) && trim($providerReference) !== '' ? $providerReference : null,
            signatureAlgorithm: is_string($signatureAlgorithm) && trim($signatureAlgorithm) !== '' ? $signatureAlgorithm : 'RSA-SHA256',
            publicKeyPem: is_string($publicKeyPem) && trim($publicKeyPem) !== '' ? $publicKeyPem : null,
            evidence: $this->extractEvidencePayload($payload),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractSignatureValue(array $payload): ?string
    {
        $signatureValue = $payload['signature_value'] ?? null;
        if (is_string($signatureValue) && trim($signatureValue) !== '') {
            return $signatureValue;
        }

        $signatures = $payload['signatures'] ?? null;
        if (is_array($signatures) && isset($signatures[0]) && is_string($signatures[0]) && trim($signatures[0]) !== '') {
            return $signatures[0];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractSignerCertificatePem(array $payload): ?string
    {
        $certificatePem = $payload['certificate_pem'] ?? null;
        if (is_string($certificatePem) && trim($certificatePem) !== '') {
            return $certificatePem;
        }

        $certificates = $payload['certificates'] ?? null;
        if (is_array($certificates) && isset($certificates[0]) && is_string($certificates[0])) {
            return $this->normalizeCertificatePem($certificates[0]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractIssuerCertificatePem(array $payload): ?string
    {
        $issuerCertificatePem = $payload['issuer_certificate_pem'] ?? null;
        if (is_string($issuerCertificatePem) && trim($issuerCertificatePem) !== '') {
            return $issuerCertificatePem;
        }

        $certificates = $payload['certificates'] ?? null;
        if (is_array($certificates) && isset($certificates[1]) && is_string($certificates[1])) {
            return $this->normalizeCertificatePem($certificates[1]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractProviderReference(array $payload): ?string
    {
        foreach (['provider_reference', 'transactionID', 'transaction_id'] as $key) {
            $value = $payload[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function extractEvidencePayload(array $payload): ?array
    {
        $evidence = [];

        foreach ([
            'credentialID' => 'credential_id',
            'transactionID' => 'transaction_id',
            'transaction_id' => 'transaction_id',
            'authMode' => 'authentication_mode',
            'SCAL' => 'scal',
            'timestamp' => 'timestamp',
            'signingTime' => 'signing_time',
        ] as $sourceKey => $targetKey) {
            $value = $payload[$sourceKey] ?? null;
            if ($value !== null) {
                $evidence[$targetKey] = $value;
            }
        }

        $validationInfo = $payload['validationInfo'] ?? null;
        if (is_array($validationInfo) && $validationInfo !== []) {
            $evidence['validation_info'] = $validationInfo;
        }

        $rawEvidence = $payload['evidence'] ?? null;
        if (is_array($rawEvidence) && $rawEvidence !== []) {
            $evidence = [...$evidence, ...$rawEvidence];
        }

        return $evidence !== [] ? $evidence : null;
    }

    private function normalizeCertificatePem(string $certificate): string
    {
        $trimmed = trim($certificate);
        if ($trimmed === '') {
            return $trimmed;
        }

        if (str_contains($trimmed, 'BEGIN CERTIFICATE')) {
            return $trimmed;
        }

        $normalized = preg_replace('/\s+/', '', $trimmed) ?: '';

        return "-----BEGIN CERTIFICATE-----\n"
            .chunk_split($normalized, 64, "\n")
            ."-----END CERTIFICATE-----\n";
    }
}
