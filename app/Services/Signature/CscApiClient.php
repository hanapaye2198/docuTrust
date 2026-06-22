<?php

namespace App\Services\Signature;

use App\Exceptions\CscApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CscApiClient
{
    /**
     * @return array<string, mixed>
     */
    public function getInfo(): array
    {
        return $this->postJson('csc/v2/info', '/csc/v2/info');
    }

    /**
     * @return array<string, mixed>
     */
    public function listCredentials(string $accessToken): array
    {
        return $this->postJson('csc/v2/credentials/list', '/csc/v2/credentials/list', [
            'maxResults' => 10,
        ], $accessToken);
    }

    /**
     * @return array<string, mixed>
     */
    public function getCredentialInfo(string $accessToken, string $credentialId): array
    {
        return $this->postJson('csc/v2/credentials/info', '/csc/v2/credentials/info', [
            'credentialID' => $credentialId,
            'certificates' => 'chain',
            'certInfo' => true,
            'authInfo' => true,
        ], $accessToken);
    }

    /**
     * @return array<string, mixed>
     */
    public function authorize(
        string $accessToken,
        string $credentialId,
        int $numSignatures,
        string $hash,
        string $description,
    ): array {
        return $this->postJson('csc/v2/credentials/authorize', '/csc/v2/credentials/authorize', [
            'credentialID' => $credentialId,
            'numSignatures' => $numSignatures,
            'hash' => [$hash],
            'description' => $description,
        ], $accessToken);
    }

    /**
     * @return array<string, mixed>
     */
    public function signHash(
        string $accessToken,
        string $sad,
        string $credentialId,
        string $hash,
        string $signAlgo = '1.2.840.113549.1.1.11',
    ): array {
        return $this->postJson('csc/v2/signatures/signHash', '/csc/v2/signatures/signHash', [
            'credentialID' => $credentialId,
            'SAD' => $sad,
            'hash' => [$hash],
            'signAlgo' => $signAlgo,
        ], $accessToken);
    }

    /**
     * @return array<string, mixed>
     */
    public function getAccessToken(string $authCode, string $redirectUri): array
    {
        return $this->postForm('oauth2/token', '/oauth2/token', [
            'grant_type' => 'authorization_code',
            'code' => $authCode,
            'redirect_uri' => $redirectUri,
            'client_id' => (string) config('services.csc.client_id', ''),
            'client_secret' => (string) config('services.csc.client_secret', ''),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        return $this->postForm('oauth2/token', '/oauth2/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => (string) config('services.csc.client_id', ''),
            'client_secret' => (string) config('services.csc.client_secret', ''),
        ]);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function postJson(string $endpointName, string $endpoint, array $body = [], ?string $accessToken = null): array
    {
        $url = $this->url($endpoint);
        $request = $this->request()->acceptJson();

        if (is_string($accessToken) && $accessToken !== '') {
            $request = $request->withToken($accessToken);
        }

        $this->logRequest($endpointName, $url, $body, $accessToken !== null);

        try {
            $response = $request->post($url, $body);
            $this->logResponse($endpointName, $response);
            $response->throw();

            return $this->decodedResponseBody($response);
        } catch (RequestException $exception) {
            throw $this->exceptionFor($endpointName, $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function postForm(string $endpointName, string $endpoint, array $body): array
    {
        $url = $this->url($endpoint);
        $this->logRequest($endpointName, $url, $body, false, 'form');

        try {
            $response = $this->request()
                ->asForm()
                ->acceptJson()
                ->post($url, $body);
            $this->logResponse($endpointName, $response);
            $response->throw();

            return $this->decodedResponseBody($response);
        } catch (RequestException $exception) {
            throw $this->exceptionFor($endpointName, $exception);
        }
    }

    private function request(): PendingRequest
    {
        return Http::timeout((int) config('services.csc.timeout', 30));
    }

    private function url(string $endpoint): string
    {
        $baseUrl = rtrim((string) config('services.csc.base_url', ''), '/');
        if ($baseUrl === '') {
            throw new RuntimeException('CSC base URL is not configured.');
        }

        return $baseUrl.'/'.ltrim($endpoint, '/');
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function logRequest(
        string $endpointName,
        string $url,
        array $body,
        bool $hasAuthorization,
        string $encoding = 'json',
    ): void {
        Log::channel('signature')->debug('CSC API request', [
            'endpoint' => $endpointName,
            'method' => 'POST',
            'url' => $url,
            'encoding' => $encoding,
            'headers' => $hasAuthorization ? ['Authorization' => 'Bearer [REDACTED]'] : [],
            'body' => $this->redact($body),
        ]);
    }

    private function logResponse(string $endpointName, Response $response): void
    {
        Log::channel('signature')->debug('CSC API response', [
            'endpoint' => $endpointName,
            'status' => $response->status(),
            'body' => $this->redact($this->decodedResponseBody($response)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodedResponseBody(Response $response): array
    {
        $body = $response->json();

        if (is_array($body)) {
            return $body;
        }

        $rawBody = $response->body();

        return $rawBody !== '' ? ['raw' => $rawBody] : [];
    }

    private function exceptionFor(string $endpointName, RequestException $exception): CscApiException
    {
        $response = $exception->response;

        if ($response === null) {
            return new CscApiException($endpointName, 0, [
                'error' => $exception->getMessage(),
            ], $exception);
        }

        return new CscApiException(
            $endpointName,
            $response->status(),
            $this->decodedResponseBody($response),
            $exception,
        );
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private function redact(array $value): array
    {
        $redacted = [];

        foreach ($value as $key => $item) {
            $normalizedKey = strtolower((string) $key);
            if (in_array($normalizedKey, ['sad', 'client_secret'], true)) {
                $redacted[$key] = '[REDACTED]';

                continue;
            }

            $redacted[$key] = is_array($item) ? $this->redact($item) : $item;
        }

        return $redacted;
    }
}
