<?php

namespace Tests\Feature;

use App\Enums\EInvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\EInvoice;
use App\Models\NotaryRequest;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GatewayHubWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepts_a_valid_gatewayhub_webhook_and_marks_the_payment_as_paid(): void
    {
        config()->set('services.gatewayhub.webhook_secret', 'test-webhook-secret');

        $user = User::factory()->client()->create();
        $request = NotaryRequest::factory()->create([
            'user_id' => $user->id,
            'organization_id' => $user->organization_id,
        ]);

        $payment = Payment::query()->create([
            'organization_id' => $user->organization_id,
            'notary_request_id' => $request->id,
            'payer_user_id' => $user->id,
            'provider' => 'gatewayhub',
            'provider_payment_id' => 'payment-uuid-1',
            'provider_transaction_id' => 'payment-uuid-1',
            'gateway' => 'gcash',
            'reference' => 'NREQ-1-ABCDE12345',
            'amount' => 500.00,
            'currency' => 'PHP',
            'status' => PaymentStatus::Pending->value,
        ]);

        $payload = [
            'event' => 'payment.updated',
            'timestamp' => '2026-02-28T12:00:00+08:00',
            'data' => [
                'payment_id' => 'payment-uuid-1',
                'status' => 'paid',
                'amount' => 500,
                'currency' => 'PHP',
                'gateway' => 'gcash',
                'reference' => 'NREQ-1-ABCDE12345',
                'provider_reference' => 'provider-id-1',
                'paid_at' => '2026-02-28T12:02:00+08:00',
                'created_at' => '2026-02-28T12:00:00+08:00',
                'updated_at' => '2026-02-28T12:02:00+08:00',
            ],
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = '1700000000';
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'test-webhook-secret');

        $response = $this->call('POST', '/api/gatewayhub/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_MERCHANT_TIMESTAMP' => $timestamp,
            'HTTP_X_MERCHANT_SIGNATURE' => $signature,
        ], $body);

        $response->assertOk();

        $payment->refresh();

        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame('provider-id-1', $payment->provider_reference);
        $this->assertNotNull($payment->paid_at);
        $this->assertDatabaseHas('einvoices', [
            'payment_id' => $payment->id,
            'notary_request_id' => $request->id,
            'status' => EInvoiceStatus::Draft->value,
            'total_amount' => '500.00',
        ]);
    }

    public function test_duplicate_paid_webhooks_do_not_create_duplicate_einvoices(): void
    {
        config()->set('services.gatewayhub.webhook_secret', 'test-webhook-secret');

        $user = User::factory()->client()->create();
        $request = NotaryRequest::factory()->create([
            'user_id' => $user->id,
            'organization_id' => $user->organization_id,
        ]);

        $payment = Payment::query()->create([
            'organization_id' => $user->organization_id,
            'notary_request_id' => $request->id,
            'payer_user_id' => $user->id,
            'provider' => 'gatewayhub',
            'provider_payment_id' => 'payment-uuid-2',
            'provider_transaction_id' => 'payment-uuid-2',
            'gateway' => 'gcash',
            'reference' => 'NREQ-2-ABCDE12345',
            'amount' => 500.00,
            'currency' => 'PHP',
            'status' => PaymentStatus::Pending->value,
        ]);

        $payload = [
            'event' => 'payment.updated',
            'timestamp' => '2026-02-28T12:00:00+08:00',
            'data' => [
                'payment_id' => 'payment-uuid-2',
                'status' => 'paid',
                'amount' => 500,
                'currency' => 'PHP',
                'gateway' => 'gcash',
                'reference' => 'NREQ-2-ABCDE12345',
                'provider_reference' => 'provider-id-2',
                'paid_at' => '2026-02-28T12:02:00+08:00',
            ],
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = '1700000000';
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'test-webhook-secret');

        foreach ([1, 2] as $ignored) {
            $this->call('POST', '/api/gatewayhub/webhook', [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_MERCHANT_TIMESTAMP' => $timestamp,
                'HTTP_X_MERCHANT_SIGNATURE' => $signature,
            ], $body)->assertOk();
        }

        $this->assertSame(1, EInvoice::query()->where('payment_id', $payment->id)->count());
    }

    public function test_rejects_a_gatewayhub_webhook_with_an_invalid_signature(): void
    {
        config()->set('services.gatewayhub.webhook_secret', 'test-webhook-secret');

        $payload = [
            'event' => 'payment.updated',
            'data' => [
                'payment_id' => 'unknown-payment',
                'status' => 'paid',
            ],
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $response = $this->call('POST', '/api/gatewayhub/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_MERCHANT_TIMESTAMP' => '1700000000',
            'HTTP_X_MERCHANT_SIGNATURE' => 'invalid-signature',
        ], $body);

        $response->assertUnauthorized();
    }

    public function test_refreshing_gatewayhub_payment_treats_success_status_as_paid(): void
    {
        config()->set('services.gatewayhub.api_key', 'test-api-key');

        Http::fake([
            'https://gatewayhub.io/api/payments/payment-uuid-3/status' => Http::response([
                'success' => true,
                'data' => [
                    'payment_id' => 'payment-uuid-3',
                    'status' => 'success',
                    'amount' => 500,
                    'currency' => 'PHP',
                    'gateway' => 'gcash',
                    'reference' => 'NREQ-3-ABCDE12345',
                ],
                'error' => null,
            ], 200),
        ]);

        $user = User::factory()->client()->create();
        $request = NotaryRequest::factory()->create([
            'user_id' => $user->id,
            'organization_id' => $user->organization_id,
        ]);

        $payment = Payment::query()->create([
            'organization_id' => $user->organization_id,
            'notary_request_id' => $request->id,
            'payer_user_id' => $user->id,
            'provider' => 'gatewayhub',
            'provider_payment_id' => 'payment-uuid-3',
            'provider_transaction_id' => 'payment-uuid-3',
            'gateway' => 'gcash',
            'reference' => 'NREQ-3-ABCDE12345',
            'amount' => 500.00,
            'currency' => 'PHP',
            'status' => PaymentStatus::Pending->value,
        ]);

        $updated = app(\App\Services\NotaryPaymentService::class)->refreshGatewayPayment($payment);

        $this->assertSame(PaymentStatus::Paid, $updated->status);
        $this->assertNotNull($updated->paid_at);
        $this->assertNotNull($updated->last_verified_at);
    }
}
