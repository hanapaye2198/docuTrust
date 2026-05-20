<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GatewayHubService
{
    /**
     * @return list<array{code: string, name: string}>
     */
    public function enabledGateways(): array
    {
        $response = $this->request('get', '/api/gateways/enabled');
        $gateways = $response->json('data.gateways', []);

        return is_array($gateways) ? array_values(array_filter($gateways, 'is_array')) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function createPayment(float $amount, string $currency, string $gateway, string $reference): array
    {
        $response = $this->request('post', '/api/payments', [
            'amount' => round($amount, 2),
            'currency' => strtoupper($currency),
            'gateway' => $gateway,
            'reference' => $reference,
        ]);

        $data = $response->json('data');

        if (! is_array($data)) {
            throw new RuntimeException('GatewayHub payment response was missing the payment payload.');
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchPaymentStatus(string $paymentId): array
    {
        $response = $this->request('get', '/api/payments/'.$paymentId.'/status');
        $data = $response->json('data');

        if (! is_array($data)) {
            throw new RuntimeException('GatewayHub status response was missing the payment payload.');
        }

        return $data;
    }

    public function verifyWebhookSignature(?string $timestamp, ?string $signature, string $rawBody): bool
    {
        $secret = (string) config('services.gatewayhub.webhook_secret', '');

        if ($secret === '' || $timestamp === null || $signature === null) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function request(string $method, string $path, array $payload = []): Response
    {
        $apiKey = (string) config('services.gatewayhub.api_key', '');
        $baseUrl = rtrim((string) config('services.gatewayhub.base_url', 'https://gatewayhub.io'), '/');
        $timeout = (int) config('services.gatewayhub.timeout', 15);

        if ($apiKey === '') {
            throw new RuntimeException('GatewayHub API key is not configured.');
        }

        $response = Http::baseUrl($baseUrl)
            ->withToken($apiKey)
            ->acceptJson()
            ->timeout($timeout)
            ->send($method, $path, $payload === [] ? [] : ['json' => $payload]);

        if ($response->successful()) {
            return $response;
        }

        $message = $response->json('error');
        if (! is_string($message) || $message === '') {
            $message = 'GatewayHub request failed with status '.$response->status().'.';
        }

        throw new RuntimeException($message);
    }
}
