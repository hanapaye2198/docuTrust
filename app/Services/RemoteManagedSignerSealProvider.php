<?php

namespace App\Services;

use App\Contracts\SignerSealProvider;
use App\Data\SignerSealResult;
use App\Models\DocumentSigner;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;

class RemoteManagedSignerSealProvider implements SignerSealProvider
{
    public function __construct(
        private readonly PkiSignatureService $pkiSignatureService,
        private readonly SignerCertificateService $signerCertificateService,
    ) {}

    public function seal(DocumentSigner $signer, string $hash): SignerSealResult
    {
        $providerName = trim((string) config('services.remote_signing.provider_name', 'remote_managed'));
        $response = match ((string) config('services.remote_signing.api_mode', 'csc')) {
            'csc' => $this->performCscSignHashRequest($signer, $hash),
            'legacy' => $this->performLegacySignRequest($signer, $hash),
            default => throw new RuntimeException('Unsupported remote signing API mode configured.'),
        };

        if ($response->failed()) {
            throw new RuntimeException('Remote signing provider request failed.');
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Remote signing provider returned an invalid response body.');
        }

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

        $certificate = $this->signerCertificateService->createOrRefreshProviderManagedForSigner(
            $signer,
            providerName: $providerName !== '' ? $providerName : 'remote_managed',
            providerReference: is_string($providerReference) && trim($providerReference) !== '' ? $providerReference : null,
            certificatePem: $certificatePem,
            issuerCertificatePem: $issuerCertificatePem,
            publicKeyPem: is_string($publicKeyPem) && trim($publicKeyPem) !== '' ? $publicKeyPem : null,
        );

        if (! $this->pkiSignatureService->verifySignature($hash, $signatureValue, $certificate->public_key_pem)) {
            throw new RuntimeException('Remote signing provider returned a signature that could not be verified.');
        }

        return new SignerSealResult(
            signerCertificateId: $certificate->id,
            signatureValue: $signatureValue,
            signatureHash: $hash,
            publicKeyFingerprint: $this->pkiSignatureService->fingerprint($certificate->public_key_pem),
            signatureAlgorithm: is_string($signatureAlgorithm) && trim($signatureAlgorithm) !== '' ? $signatureAlgorithm : 'RSA-SHA256',
            signingProvider: $providerName !== '' ? $providerName : 'remote_managed',
            signingProviderReference: is_string($providerReference) && trim($providerReference) !== '' ? $providerReference : null,
            signingProviderPayload: $this->extractEvidencePayload($payload),
        );
    }

    private function performCscSignHashRequest(DocumentSigner $signer, string $hash): Response
    {
        $credentialId = $this->resolveCredentialId($signer);
        $hashBinary = hex2bin($hash);
        if ($hashBinary === false) {
            throw new RuntimeException('Document hash could not be encoded for CSC signing.');
        }

        $encodedHash = base64_encode($hashBinary);

        return $this->request()->post(
            $this->endpoint((string) config('services.remote_signing.csc.sign_hash_endpoint', '/csc/v1/signatures/signHash')),
            [
                'credentialID' => $credentialId,
                'hash' => $encodedHash,
                'hashes' => [$encodedHash],
                'hashAlgo' => (string) config('services.remote_signing.csc.hash_algorithm', '2.16.840.1.101.3.4.2.1'),
                'signAlgo' => (string) config('services.remote_signing.csc.sign_algorithm', '1.2.840.113549.1.1.11'),
                'numSignatures' => 1,
                'clientData' => $this->encodeClientData($signer, $hash, $credentialId),
            ]
        );
    }

    private function performLegacySignRequest(DocumentSigner $signer, string $hash): Response
    {
        return $this->request()->post(
            $this->endpoint((string) config('services.remote_signing.legacy.sign_endpoint', '/sign')),
            [
                'signer' => [
                    'id' => $signer->id,
                    'name' => $signer->name,
                    'email' => $signer->email,
                ],
                'document_hash' => $hash,
            ]
        );
    }

    private function request(): PendingRequest
    {
        $request = Http::timeout((int) config('services.remote_signing.timeout', 10))->acceptJson();
        $apiKey = trim((string) config('services.remote_signing.api_key', ''));

        return $apiKey !== '' ? $request->withToken($apiKey) : $request;
    }

    private function endpoint(string $endpoint): string
    {
        $baseUrl = rtrim((string) config('services.remote_signing.base_url', ''), '/');
        if ($baseUrl === '') {
            throw new RuntimeException('Remote signing base URL is not configured.');
        }

        return $baseUrl.'/'.ltrim($endpoint, '/');
    }

    private function resolveCredentialId(DocumentSigner $signer): string
    {
        $credentialId = trim((string) ($signer->remote_credential_id ?: config('services.remote_signing.default_credential_id', '')));
        if ($credentialId === '') {
            throw new RuntimeException('Remote signing credential ID is not configured for the signer.');
        }

        return $credentialId;
    }

    private function encodeClientData(DocumentSigner $signer, string $hash, string $credentialId): string
    {
        try {
            return base64_encode(json_encode([
                'document_hash' => $hash,
                'credential_id' => $credentialId,
                'signer' => [
                    'id' => $signer->id,
                    'name' => $signer->name,
                    'email' => $signer->email,
                ],
            ], JSON_THROW_ON_ERROR));
        } catch (JsonException $exception) {
            throw new RuntimeException('Remote signing client data could not be encoded.', previous: $exception);
        }
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
