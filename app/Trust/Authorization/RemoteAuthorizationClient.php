<?php

namespace App\Trust\Authorization;

use App\Models\DocumentSigner;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RemoteAuthorizationClient
{
    /**
     * @return array<string, mixed>
     */
    public function authorize(DocumentSigner $signer, int $numSignatures = 1): array
    {
        $credentialId = trim((string) ($signer->remote_credential_id ?: config('services.remote_signing.default_credential_id', '')));
        if ($credentialId === '') {
            throw new RuntimeException('Remote signing credential ID is not configured for the signer.');
        }

        $response = $this->request()->post(
            $this->endpoint((string) config('services.remote_signing.csc.authorize_endpoint', '/csc/v2/credentials/authorize')),
            [
                'credentialID' => $credentialId,
                'numSignatures' => $numSignatures,
                'clientData' => base64_encode(json_encode([
                    'signer' => [
                        'id' => $signer->id,
                        'name' => $signer->name,
                        'email' => $signer->email,
                    ],
                ], JSON_THROW_ON_ERROR)),
            ]
        );

        if ($response->failed()) {
            throw new RuntimeException('Remote signing authorization request failed.');
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Remote signing authorization response is invalid.');
        }

        return [
            'http_status' => $response->status(),
            'payload' => $payload,
            'credential_id' => $credentialId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function checkAuthorization(string $handle): array
    {
        $response = $this->request()->post(
            $this->endpoint((string) config('services.remote_signing.csc.authorize_check_endpoint', '/csc/v2/credentials/authorizeCheck')),
            [
                'handle' => $handle,
            ]
        );

        if ($response->failed()) {
            throw new RuntimeException('Remote signing authorization status request failed.');
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Remote signing authorization status response is invalid.');
        }

        return [
            'http_status' => $response->status(),
            'payload' => $payload,
        ];
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
}
