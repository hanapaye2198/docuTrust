<?php

namespace App\Services\Ekyc\Sumsub;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SumsubApiClient
{
    private readonly string $appToken;

    private readonly string $secretKey;

    private readonly string $baseUrl;

    public function __construct()
    {
        $this->appToken = (string) config('ekyc.sumsub.app_token');
        $this->secretKey = (string) config('ekyc.sumsub.secret_key');
        $this->baseUrl = rtrim((string) config('ekyc.sumsub.base_url', 'https://api.sumsub.com'), '/');

        if ($this->appToken === '' || $this->secretKey === '') {
            throw new RuntimeException('Sumsub app_token and secret_key must be configured.');
        }
    }

    /**
     * Create an applicant in Sumsub.
     *
     * @param  array<string, string|null>|null  $fixedInfo
     * @return array<string, mixed>
     */
    public function createApplicant(string $externalUserId, string $levelName, ?array $fixedInfo = null): array
    {
        $body = [
            'externalUserId' => $externalUserId,
        ];

        if ($fixedInfo !== null) {
            $body['fixedInfo'] = array_filter($fixedInfo, fn ($v) => $v !== null && $v !== '');
        }

        $response = $this->request(
            method: 'POST',
            path: '/resources/applicants',
            query: ['levelName' => $levelName],
            body: $body,
        );

        return $response->json();
    }

    /**
     * Generate an access token for the WebSDK.
     */
    public function generateAccessToken(string $externalUserId, string $levelName, int $ttlInSecs = 600): string
    {
        $response = $this->request(
            method: 'POST',
            path: "/resources/accessTokens?userId={$externalUserId}&levelName={$levelName}&ttlInSecs={$ttlInSecs}",
        );

        $data = $response->json();

        return $data['token'] ?? '';
    }

    /**
     * Get the applicant's verification status.
     *
     * @return array<string, mixed>
     */
    public function getApplicantStatus(string $applicantId): array
    {
        $response = $this->request(
            method: 'GET',
            path: "/resources/applicants/{$applicantId}/requiredIdDocsStatus",
        );

        return $response->json();
    }

    /**
     * Get full applicant information including extracted document data.
     *
     * @return array<string, mixed>
     */
    public function getApplicantInfo(string $applicantId): array
    {
        $response = $this->request(
            method: 'GET',
            path: "/resources/applicants/{$applicantId}/one",
        );

        return $response->json();
    }

    /**
     * Reset an applicant's verification to allow re-submission.
     */
    public function resetApplicant(string $applicantId): void
    {
        $this->request(
            method: 'POST',
            path: "/resources/applicants/{$applicantId}/reset",
        );
    }

    /**
     * Send a signed HTTP request to the Sumsub API.
     */
    private function request(string $method, string $path, ?array $query = null, ?array $body = null): Response
    {
        $url = $this->baseUrl.$path;

        if ($query !== null && $query !== []) {
            $separator = str_contains($path, '?') ? '&' : '?';
            $url .= $separator.http_build_query($query);
        }

        $bodyJson = ($body !== null && $body !== []) ? json_encode($body) : '';
        $headers = $this->signRequest($method, $path.($query !== null && $query !== [] ? (str_contains($path, '?') ? '&' : '?').http_build_query($query) : ''), $bodyJson);

        /** @var PendingRequest $pending */
        $pending = Http::withHeaders($headers)
            ->timeout(30)
            ->retry(2, 500);

        if ($bodyJson !== '') {
            $pending = $pending->withBody($bodyJson, 'application/json');
        }

        $response = $pending->send($method, $url);

        if ($response->failed()) {
            throw new RuntimeException(
                "Sumsub API error [{$response->status()}]: ".$response->body()
            );
        }

        return $response;
    }

    /**
     * Generate HMAC-SHA256 signature headers for Sumsub API authentication.
     *
     * @return array<string, string>
     */
    private function signRequest(string $method, string $urlPath, string $body = ''): array
    {
        $ts = (string) time();

        $signature = hash_hmac(
            'sha256',
            $ts.strtoupper($method).$urlPath.$body,
            $this->secretKey,
        );

        return [
            'X-App-Token' => $this->appToken,
            'X-App-Access-Ts' => $ts,
            'X-App-Access-Sig' => $signature,
        ];
    }
}
