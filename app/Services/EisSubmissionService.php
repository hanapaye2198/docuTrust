<?php

namespace App\Services;

use App\Enums\EInvoiceStatus;
use App\Models\EInvoice;
use App\Models\EInvoiceSubmission;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EisSubmissionService
{
    public function __construct(
        private readonly EisAuthService $authService,
        private readonly EisCryptoService $cryptoService,
        private readonly EisInvoicePayloadFactory $payloadFactory,
    ) {}

    public function submit(EInvoice $invoice): EInvoice
    {
        $invoice->loadMissing(['billingProfile', 'payment', 'registerEntry']);

        $profile = $invoice->billingProfile;
        if ($profile === null) {
            throw new RuntimeException('An active billing profile is required before submitting this e-invoice.');
        }

        $baseUrl = trim((string) config('services.eis.base_url', ''));
        $endpoint = trim((string) config('services.eis.submit_endpoint', ''));
        $timeout = (int) config('services.eis.timeout', 30);

        if ($baseUrl === '') {
            throw new RuntimeException('EIS base URL is not configured.');
        }

        if ($endpoint === '') {
            throw new RuntimeException('EIS submit endpoint is not configured.');
        }

        $auth = $this->authService->authenticate($profile);
        $payload = $this->payloadFactory->make($invoice);
        $jws = $this->cryptoService->signInvoicePayload($payload);
        $secretKey = (string) ($auth['secretKey'] ?? $auth['secret_key'] ?? $this->cryptoService->generateSessionKey());
        $encrypted = $this->cryptoService->encryptSubmissionPayload($jws, $secretKey);

        $body = [
            'data' => $encrypted['data'],
            'iv' => $encrypted['iv'],
        ];

        $bodyJson = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $timestamp = now()->timezone('Asia/Manila')->toIso8601String();
        $headers = array_filter([
            'accreditationId' => $profile->eis_accreditation_id,
            'applicationId' => $profile->eis_application_id,
            'datetime' => $timestamp,
            'Authorization' => $secretKey !== '' ? $this->cryptoService->makeAuthorizationSignature($timestamp, $bodyJson, $secretKey) : null,
            'authToken' => $auth['authToken'] ?? $auth['auth_token'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        $response = Http::baseUrl(rtrim($baseUrl, '/'))
            ->acceptJson()
            ->withHeaders($headers)
            ->timeout($timeout)
            ->withBody($bodyJson, 'application/json')
            ->post($endpoint);

        $responseData = $response->json();
        $submitId = $this->stringOrNull(
            data_get($responseData, 'data.submitId')
            ?? data_get($responseData, 'data.submit_id')
            ?? data_get($responseData, 'submitId')
            ?? data_get($responseData, 'submit_id')
        );

        EInvoiceSubmission::query()->create([
            'einvoice_id' => $invoice->id,
            'status' => $response->successful() ? EInvoiceStatus::Submitted->value : EInvoiceStatus::Rejected->value,
            'submit_id' => $submitId,
            'request_payload' => $payload,
            'response_payload' => is_array($responseData) ? $responseData : ['body' => $response->body()],
            'submitted_at' => now(),
            'resolved_at' => $response->successful() ? null : now(),
        ]);

        if (! $response->successful()) {
            $message = is_array($responseData)
                ? (string) ($responseData['error'] ?? $response->body())
                : $response->body();

            $invoice->forceFill([
                'status' => EInvoiceStatus::NeedsCorrection,
                'error_message' => 'EIS submission failed: '.trim($message),
            ])->save();

            throw new RuntimeException($invoice->error_message ?? 'EIS submission failed.');
        }

        $invoice->forceFill([
            'status' => $submitId !== null ? EInvoiceStatus::Processing : EInvoiceStatus::Submitted,
            'submit_id' => $submitId,
            'submitted_at' => now(),
            'error_message' => null,
        ])->save();

        return $invoice->fresh();
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
