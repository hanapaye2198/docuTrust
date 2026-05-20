<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\NotarialRegisterEntry;
use App\Models\NotaryRequest;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use RuntimeException;

class NotaryPaymentService
{
    public function __construct(
        private readonly GatewayHubService $gatewayHubService,
        private readonly EInvoiceService $eInvoiceService,
    ) {}

    public function createGatewayPayment(NotaryRequest $request, string $gateway, ?int $createdByUserId = null): Payment
    {
        $request->loadMissing(['registerEntries', 'payments']);

        $registerEntry = $request->registerEntries
            ->sortByDesc('created_at')
            ->first();

        if (! $registerEntry instanceof NotarialRegisterEntry) {
            throw new RuntimeException('Create a notarial register entry before requesting payment.');
        }

        $amount = (float) $registerEntry->fees;
        if ($amount <= 0) {
            throw new RuntimeException('The register entry must have a fee amount before payment can be created.');
        }

        $latestPayment = $request->payments->sortByDesc('created_at')->first();
        if ($latestPayment instanceof Payment) {
            if ($latestPayment->status === PaymentStatus::Paid) {
                throw new RuntimeException('This request already has a settled payment.');
            }

            if (
                $latestPayment->status === PaymentStatus::Pending
                && ($latestPayment->expires_at === null || $latestPayment->expires_at->isFuture())
            ) {
                return $latestPayment;
            }
        }

        $reference = $this->generateReference($request);
        $payload = $this->gatewayHubService->createPayment($amount, 'PHP', $gateway, $reference);

        return Payment::query()->create([
            'organization_id' => $request->organization_id,
            'notary_request_id' => $request->id,
            'notarial_register_entry_id' => $registerEntry->id,
            'payer_user_id' => $request->user_id,
            'created_by_user_id' => $createdByUserId,
            'provider' => 'gatewayhub',
            'provider_payment_id' => $this->stringOrNull($payload['payment_id'] ?? null),
            'provider_transaction_id' => $this->stringOrNull($payload['transaction_id'] ?? null),
            'gateway' => $this->stringOrFallback($payload['gateway'] ?? null, $gateway),
            'reference' => $reference,
            'amount' => $amount,
            'currency' => $this->stringOrFallback($payload['currency'] ?? null, 'PHP'),
            'status' => $this->normalizeStatus($payload['status'] ?? null)->value,
            'qr_data' => $this->stringOrNull($payload['qr_data'] ?? null),
            'redirect_url' => $this->stringOrNull($payload['redirect_url'] ?? null),
            'checkout_url' => $this->stringOrNull($payload['checkout_url'] ?? null),
            'expires_at' => $this->parseDate($payload['expires_at'] ?? null),
            'provider_payload' => $payload,
        ]);
    }

    public function refreshGatewayPayment(Payment $payment): Payment
    {
        $providerPaymentId = trim((string) $payment->provider_payment_id);
        if ($providerPaymentId === '') {
            throw new RuntimeException('This payment is missing the provider payment ID.');
        }

        $payload = $this->gatewayHubService->fetchPaymentStatus($providerPaymentId);

        return $this->applyGatewayPayload($payment, $payload, false);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleGatewayWebhook(array $payload): ?Payment
    {
        $data = $payload['data'] ?? null;
        if (! is_array($data)) {
            return null;
        }

        $providerPaymentId = $this->stringOrNull($data['payment_id'] ?? null);
        $reference = $this->stringOrNull($data['reference'] ?? null);
        if ($providerPaymentId === null && $reference === null) {
            return null;
        }

        $payment = Payment::query()
            ->where(function ($query) use ($providerPaymentId, $reference): void {
                if ($providerPaymentId !== null) {
                    $query->where('provider_payment_id', $providerPaymentId);
                }

                if ($reference !== null) {
                    $method = $providerPaymentId !== null ? 'orWhere' : 'where';
                    $query->{$method}('reference', $reference);
                }
            })
            ->latest('id')
            ->first();

        if (! $payment instanceof Payment) {
            return null;
        }

        return $this->applyGatewayPayload($payment, $data, true, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $webhookEnvelope
     */
    private function applyGatewayPayload(Payment $payment, array $payload, bool $fromWebhook, ?array $webhookEnvelope = null): Payment
    {
        $wasPaid = $payment->status === PaymentStatus::Paid;
        $status = $this->normalizeStatus($payload['status'] ?? null);

        $payment->forceFill([
            'provider_payment_id' => $this->stringOrFallback($payload['payment_id'] ?? null, $payment->provider_payment_id),
            'provider_transaction_id' => $this->stringOrFallback($payload['transaction_id'] ?? null, $payment->provider_transaction_id),
            'gateway' => $this->stringOrFallback($payload['gateway'] ?? null, $payment->gateway),
            'reference' => $this->stringOrFallback($payload['reference'] ?? null, $payment->reference),
            'amount' => isset($payload['amount']) && is_numeric($payload['amount']) ? (float) $payload['amount'] : $payment->amount,
            'currency' => $this->stringOrFallback($payload['currency'] ?? null, $payment->currency),
            'status' => $status,
            'qr_data' => $this->stringOrFallback($payload['qr_data'] ?? null, $payment->qr_data),
            'redirect_url' => $this->stringOrFallback($payload['redirect_url'] ?? null, $payment->redirect_url),
            'checkout_url' => $this->stringOrFallback($payload['checkout_url'] ?? null, $payment->checkout_url),
            'provider_reference' => $this->stringOrFallback($payload['provider_reference'] ?? null, $payment->provider_reference),
            'failure_message' => $status === PaymentStatus::Paid ? null : $this->stringOrFallback($payload['error'] ?? null, $payment->failure_message),
            'expires_at' => $this->parseDate($payload['expires_at'] ?? null) ?? $payment->expires_at,
            'paid_at' => $this->parseDate($payload['paid_at'] ?? null) ?? ($status === PaymentStatus::Paid ? $payment->paid_at ?? now() : null),
            'last_verified_at' => $fromWebhook ? $payment->last_verified_at : now(),
            'provider_payload' => $fromWebhook ? $payment->provider_payload : $payload,
            'webhook_payload' => $fromWebhook ? $webhookEnvelope : $payment->webhook_payload,
        ])->save();

        $payment = $payment->fresh();

        if (! $wasPaid && $payment->status === PaymentStatus::Paid) {
            $this->eInvoiceService->createDraftFromPayment($payment);
        }

        return $payment;
    }

    private function normalizeStatus(mixed $status): PaymentStatus
    {
        $value = strtolower(trim((string) $status));

        return match ($value) {
            PaymentStatus::Paid->value, 'success', 'succeeded', 'completed' => PaymentStatus::Paid,
            PaymentStatus::Failed->value => PaymentStatus::Failed,
            PaymentStatus::Expired->value => PaymentStatus::Expired,
            PaymentStatus::Cancelled->value => PaymentStatus::Cancelled,
            default => PaymentStatus::Pending,
        };
    }

    private function generateReference(NotaryRequest $request): string
    {
        return sprintf(
            'NREQ-%d-%s',
            $request->id,
            Str::upper(Str::random(10))
        );
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return CarbonImmutable::parse($value);
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function stringOrFallback(mixed $value, ?string $fallback): ?string
    {
        return $this->stringOrNull($value) ?? $fallback;
    }
}
