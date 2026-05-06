<?php

namespace App\Trust\RemoteSigning;

use App\Models\DocumentSigner;
use App\Trust\Authorization\TrustAuthorizationSessionService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;

class RemoteSigningClient
{
    public function __construct(
        private readonly TrustAuthorizationSessionService $trustAuthorizationSessionService,
    ) {}

    public function signHash(DocumentSigner $signer, string $hash): RemoteSignatureMaterial
    {
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

        $material = app(RemoteSignatureResponseMapper::class)->map($payload);
        $timestampEvidence = $this->shouldRequestTimestamp()
            ? $this->requestTimestampEvidence($signer, $hash)
            : null;

        if ($timestampEvidence === null) {
            return $material;
        }

        return new RemoteSignatureMaterial(
            signatureValue: $material->signatureValue,
            certificatePem: $material->certificatePem,
            issuerCertificatePem: $material->issuerCertificatePem,
            providerReference: $material->providerReference,
            signatureAlgorithm: $material->signatureAlgorithm,
            publicKeyPem: $material->publicKeyPem,
            evidence: [
                ...($material->evidence ?? []),
                ...$timestampEvidence,
            ],
        );
    }

    private function performCscSignHashRequest(DocumentSigner $signer, string $hash): Response
    {
        $providerName = trim((string) config('services.remote_signing.provider_name', 'remote_managed'));
        $authorization = $this->trustAuthorizationSessionService->activeForSigner($signer, $providerName);
        $credentialId = $this->resolveCredentialId($signer, $authorization?->credentialId);
        $hashBinary = hex2bin($hash);
        if ($hashBinary === false) {
            throw new RuntimeException('Document hash could not be encoded for CSC signing.');
        }

        $encodedHash = base64_encode($hashBinary);
        $payload = [
            'credentialID' => $credentialId,
            'hash' => $encodedHash,
            'hashes' => [$encodedHash],
            'hashAlgo' => (string) config('services.remote_signing.csc.hash_algorithm', '2.16.840.1.101.3.4.2.1'),
            'signAlgo' => (string) config('services.remote_signing.csc.sign_algorithm', '1.2.840.113549.1.1.11'),
            'numSignatures' => 1,
            'clientData' => $this->encodeClientData($signer, $hash, $credentialId, $authorization?->authorizationReference),
        ];

        if (is_string($authorization?->sad) && $authorization->sad !== '') {
            $payload['SAD'] = $authorization->sad;
        } elseif (is_string($authorization?->accessToken) && $authorization->accessToken !== '') {
            $payload['SAD'] = $authorization->accessToken;
        }

        return $this->request()->post(
            $this->endpoint((string) config('services.remote_signing.csc.sign_hash_endpoint', '/csc/v1/signatures/signHash')),
            $payload
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

    /**
     * @return array<string, mixed>|null
     */
    private function requestTimestampEvidence(DocumentSigner $signer, string $hash): ?array
    {
        $hashBinary = hex2bin($hash);
        if ($hashBinary === false) {
            throw new RuntimeException('Document hash could not be encoded for CSC timestamping.');
        }

        $nonce = bin2hex(random_bytes(16));
        $encodedHash = base64_encode($hashBinary);
        $response = $this->request()->post(
            $this->endpoint((string) config('services.remote_signing.csc.timestamp_endpoint', '/csc/v1/signatures/timestamp')),
            [
                'hash' => $encodedHash,
                'hashAlgo' => (string) config('services.remote_signing.csc.hash_algorithm', '2.16.840.1.101.3.4.2.1'),
                'nonce' => $nonce,
                'clientData' => $this->encodeTimestampClientData($signer, $hash, $nonce),
            ]
        );

        if ($response->failed()) {
            throw new RuntimeException('Remote signing provider timestamp request failed.');
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Remote signing provider returned an invalid timestamp response body.');
        }

        $token = $payload['timestamp'] ?? null;
        if (! is_string($token) || trim($token) === '') {
            throw new RuntimeException('Remote signing provider did not return an RFC3161 timestamp token.');
        }

        $evidence = [
            'timestamp_token' => $token,
            'timestamp_hash' => strtolower($hash),
            'timestamp_hash_algorithm' => (string) config('services.remote_signing.csc.hash_algorithm', '2.16.840.1.101.3.4.2.1'),
            'timestamp_request_nonce' => $nonce,
        ];

        foreach ([
            'transactionID' => 'timestamp_transaction_id',
            'transaction_id' => 'timestamp_transaction_id',
            'timestamp' => 'timestamp_token',
            'timestampNonce' => 'timestamp_request_nonce',
            'timestamp_nonce' => 'timestamp_request_nonce',
        ] as $sourceKey => $targetKey) {
            $value = $payload[$sourceKey] ?? null;
            if ($value !== null) {
                $evidence[$targetKey] = $value;
            }
        }

        return $evidence;
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

    private function shouldRequestTimestamp(): bool
    {
        return (string) config('services.remote_signing.api_mode', 'csc') === 'csc'
            && (bool) config('services.remote_signing.csc.timestamp_enabled', false);
    }

    private function resolveCredentialId(DocumentSigner $signer, ?string $authorizedCredentialId = null): string
    {
        $credentialId = trim((string) ($authorizedCredentialId ?: $signer->remote_credential_id ?: config('services.remote_signing.default_credential_id', '')));
        if ($credentialId === '') {
            throw new RuntimeException('Remote signing credential ID is not configured for the signer.');
        }

        return $credentialId;
    }

    private function encodeClientData(DocumentSigner $signer, string $hash, string $credentialId, ?string $authorizationReference = null): string
    {
        try {
            $payload = [
                'document_hash' => $hash,
                'credential_id' => $credentialId,
                'signer' => [
                    'id' => $signer->id,
                    'name' => $signer->name,
                    'email' => $signer->email,
                ],
            ];

            if (is_string($authorizationReference) && $authorizationReference !== '') {
                $payload['authorization_reference'] = $authorizationReference;
            }

            return base64_encode(json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (JsonException $exception) {
            throw new RuntimeException('Remote signing client data could not be encoded.', previous: $exception);
        }
    }

    private function encodeTimestampClientData(DocumentSigner $signer, string $hash, string $nonce): string
    {
        try {
            return base64_encode(json_encode([
                'document_hash' => $hash,
                'timestamp_nonce' => $nonce,
                'signer' => [
                    'id' => $signer->id,
                    'name' => $signer->name,
                    'email' => $signer->email,
                ],
            ], JSON_THROW_ON_ERROR));
        } catch (JsonException $exception) {
            throw new RuntimeException('Remote signing timestamp client data could not be encoded.', previous: $exception);
        }
    }
}
