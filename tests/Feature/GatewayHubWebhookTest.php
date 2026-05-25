<?php

namespace Tests\Feature;

use App\Enums\EInvoiceStatus;
use App\Enums\PaymentStatus;
use App\Jobs\SubmitEInvoiceJob;
use App\Models\BillingProfile;
use App\Models\EInvoice;
use App\Models\NotaryRequest;
use App\Models\Payment;
use App\Models\User;
use App\Services\NotaryPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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
            'status' => EInvoiceStatus::NeedsCorrection->value,
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

    public function test_paid_payment_with_ready_billing_profile_auto_queues_einvoice_submission(): void
    {
        Queue::fake();

        config()->set('services.gatewayhub.webhook_secret', 'test-webhook-secret');

        $user = User::factory()->client()->create();
        $request = NotaryRequest::factory()->create([
            'user_id' => $user->id,
            'organization_id' => $user->organization_id,
        ]);

        BillingProfile::query()->create([
            'organization_id' => $user->organization_id,
            'registered_name' => 'DocuTrust Test Seller',
            'tin' => '123-456-789-000',
            'branch_code' => '000',
            'email' => 'billing@example.test',
            'address_line' => '123 Test Street',
            'city' => 'Davao City',
            'state' => 'Davao del Sur',
            'postal_code' => '8000',
            'country_code' => 'PH',
            'eis_environment' => 'sandbox',
            'eis_accreditation_id' => 'ACCRED-1',
            'eis_application_id' => 'APP-1',
            'eis_username' => 'eis-user',
            'eis_password' => 'eis-pass',
            'eis_certificate_id' => 'CERT-1',
            'is_active' => true,
        ]);

        $payment = Payment::query()->create([
            'organization_id' => $user->organization_id,
            'notary_request_id' => $request->id,
            'payer_user_id' => $user->id,
            'provider' => 'gatewayhub',
            'provider_payment_id' => 'payment-uuid-2b',
            'provider_transaction_id' => 'payment-uuid-2b',
            'gateway' => 'gcash',
            'reference' => 'NREQ-22-ABCDE12345',
            'amount' => 500.00,
            'currency' => 'PHP',
            'status' => PaymentStatus::Pending->value,
        ]);

        $payload = [
            'event' => 'payment.updated',
            'timestamp' => '2026-02-28T12:00:00+08:00',
            'data' => [
                'payment_id' => 'payment-uuid-2b',
                'status' => 'paid',
                'amount' => 500,
                'currency' => 'PHP',
                'gateway' => 'gcash',
                'reference' => 'NREQ-22-ABCDE12345',
                'provider_reference' => 'provider-id-2b',
                'paid_at' => '2026-02-28T12:02:00+08:00',
            ],
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = '1700000000';
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'test-webhook-secret');

        $this->call('POST', '/api/gatewayhub/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_MERCHANT_TIMESTAMP' => $timestamp,
            'HTTP_X_MERCHANT_SIGNATURE' => $signature,
        ], $body)->assertOk();

        $invoice = EInvoice::query()->where('payment_id', $payment->id)->first();

        $this->assertNotNull($invoice);
        $this->assertSame(EInvoiceStatus::Queued, $invoice->status);

        Queue::assertPushed(SubmitEInvoiceJob::class, function (SubmitEInvoiceJob $job) use ($invoice): bool {
            return $job->einvoiceId === $invoice->id;
        });
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

        $updated = app(NotaryPaymentService::class)->refreshGatewayPayment($payment);

        $this->assertSame(PaymentStatus::Paid, $updated->status);
        $this->assertNotNull($updated->paid_at);
        $this->assertNotNull($updated->last_verified_at);
    }
}
