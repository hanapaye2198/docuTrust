<?php

namespace App\Services;

use App\Enums\EInvoiceStatus;
use App\Models\EInvoice;
use App\Models\EInvoiceSubmission;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EisInquiryService
{
    public function __construct(
        private readonly EisAuthService $authService,
    ) {}

    public function refresh(EInvoice $invoice): EInvoice
    {
        $invoice->loadMissing(['billingProfile', 'submissions']);

        $profile = $invoice->billingProfile;
        if ($profile === null) {
            throw new RuntimeException('An active billing profile is required before querying EIS status.');
        }

        $submitId = $invoice->submit_id;
        if (! is_string($submitId) || trim($submitId) === '') {
            throw new RuntimeException('This e-invoice does not have a submit ID yet.');
        }

        $baseUrl = trim((string) config('services.eis.base_url', ''));
        $endpoint = trim((string) config('services.eis.inquiry_endpoint', ''));
        $timeout = (int) config('services.eis.timeout', 30);

        if ($baseUrl === '') {
            throw new RuntimeException('EIS base URL is not configured.');
        }

        if ($endpoint === '') {
            throw new RuntimeException('EIS inquiry endpoint is not configured.');
        }

        $auth = $this->authService->authenticate($profile);
        $timestamp = now()->timezone('Asia/Manila')->toIso8601String();
        $headers = array_filter([
            'accreditationId' => $profile->eis_accreditation_id,
            'applicationId' => $profile->eis_application_id,
            'datetime' => $timestamp,
            'authToken' => $auth['authToken'] ?? $auth['auth_token'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        $response = Http::baseUrl(rtrim($baseUrl, '/'))
            ->acceptJson()
            ->withHeaders($headers)
            ->timeout($timeout)
            ->get($endpoint, [
                'submitId' => $submitId,
            ]);

        $responseData = $response->json();
        if (! $response->successful()) {
            EInvoiceSubmission::query()->create([
                'einvoice_id' => $invoice->id,
                'status' => 'inquiry_failed',
                'submit_id' => $submitId,
                'request_payload' => [
                    'submitId' => $submitId,
                ],
                'response_payload' => is_array($responseData) ? $responseData : ['body' => $response->body()],
                'submitted_at' => now(),
                'resolved_at' => now(),
            ]);

            $message = is_array($responseData)
                ? (string) ($responseData['error'] ?? $response->body())
                : $response->body();

            throw new RuntimeException('EIS inquiry failed: '.trim($message));
        }

        $status = strtolower(trim((string) (
            data_get($responseData, 'data.status')
            ?? data_get($responseData, 'status')
            ?? data_get($responseData, 'data.result.status')
            ?? ''
        )));

        $eisUniqueId = $this->stringOrNull(
            data_get($responseData, 'data.eisUniqueId')
            ?? data_get($responseData, 'data.eis_unique_id')
            ?? data_get($responseData, 'eisUniqueId')
            ?? data_get($responseData, 'eis_unique_id')
        );

        $errorMessage = $this->stringOrNull(
            data_get($responseData, 'data.errorMessage')
            ?? data_get($responseData, 'data.error_message')
            ?? data_get($responseData, 'error')
            ?? data_get($responseData, 'message')
        );

        $mappedStatus = match ($status) {
            'accepted', 'success', 'successful', 'completed' => EInvoiceStatus::Accepted,
            'rejected', 'failed', 'failure', 'invalid' => EInvoiceStatus::Rejected,
            default => EInvoiceStatus::Processing,
        };

        EInvoiceSubmission::query()->create([
            'einvoice_id' => $invoice->id,
            'status' => $mappedStatus->value,
            'submit_id' => $submitId,
            'request_payload' => [
                'submitId' => $submitId,
            ],
            'response_payload' => is_array($responseData) ? $responseData : ['body' => $response->body()],
            'submitted_at' => now(),
            'resolved_at' => $mappedStatus->isTerminal() ? now() : null,
        ]);

        $invoice->forceFill([
            'status' => $mappedStatus,
            'eis_unique_id' => $eisUniqueId ?? $invoice->eis_unique_id,
            'accepted_at' => $mappedStatus === EInvoiceStatus::Accepted ? ($invoice->accepted_at ?? now()) : null,
            'rejected_at' => $mappedStatus === EInvoiceStatus::Rejected ? ($invoice->rejected_at ?? now()) : null,
            'error_message' => $mappedStatus === EInvoiceStatus::Rejected ? $errorMessage : null,
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
