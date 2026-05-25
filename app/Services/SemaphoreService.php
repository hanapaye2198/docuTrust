<?php

namespace App\Services;

use App\Contracts\Sms\SmsProviderInterface;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

class SemaphoreService implements SmsProviderInterface
{
    private const OTP_ENDPOINT = 'https://api.semaphore.co/api/v4/otp';

    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    /**
     * @return array{success: bool, message_id: int|null, provider: string, raw: array<string, mixed>}
     */
    public function sendOtp(string $number, string $message, ?string $code = null): array
    {
        $apiKey = (string) config('services.semaphore.api_key', '');
        if ($apiKey === '') {
            throw new RuntimeException('Semaphore API key is not configured.');
        }

        $payload = [
            'apikey' => $apiKey,
            'number' => $this->normalizeNumber($number),
            'message' => $message,
            'sendername' => (string) config('services.semaphore.sender_name', 'DocuTrust'),
        ];

        if ($code !== null && $code !== '') {
            $payload['code'] = $code;
        }

        try {
            $response = $this->http
                ->asForm()
                ->timeout((int) config('services.semaphore.timeout', 15))
                ->post(self::OTP_ENDPOINT, $payload);
        } catch (Throwable $throwable) {
            report($throwable);

            return $this->failureResponse('request_failed', []);
        }

        return $this->parseResponse($response);
    }

    /**
     * @return array{success: bool, message_id: int|null, provider: string, raw: array<string, mixed>}
     */
    private function parseResponse(Response $response): array
    {
        if (! $response->successful()) {
            return $this->failureResponse('http_error', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);
        }

        $body = $response->json();
        $record = is_array($body) ? (is_array($body[0] ?? null) ? $body[0] : $body) : [];

        if (! is_array($record)) {
            return $this->failureResponse('invalid_response', []);
        }

        return [
            'success' => true,
            'message_id' => isset($record['message_id']) ? (int) $record['message_id'] : null,
            'provider' => 'semaphore',
            'raw' => $record,
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{success: bool, message_id: int|null, provider: string, raw: array<string, mixed>}
     */
    private function failureResponse(string $reason, array $raw): array
    {
        return [
            'success' => false,
            'message_id' => null,
            'provider' => 'semaphore',
            'raw' => array_merge(['reason' => $reason], $raw),
        ];
    }

    private function normalizeNumber(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number) ?? '';

        if (str_starts_with($digits, '63')) {
            return $digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '63'.substr($digits, 1);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            return '63'.$digits;
        }

        return $digits;
    }
}
