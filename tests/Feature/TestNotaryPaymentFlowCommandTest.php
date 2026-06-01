<?php

namespace Tests\Feature;

use App\Enums\NotaryRequestStatus;
use App\Enums\PaymentStatus;
use App\Mail\NotaryPaymentReadyMail;
use App\Models\AttorneyNotarialRegistry;
use App\Models\NotaryRequest;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TestNotaryPaymentFlowCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_prepare_mode_creates_payment_and_can_queue_payment_email(): void
    {
        Mail::fake();

        Http::fake([
            'https://gatewayhub.io/api/payments' => Http::response([
                'success' => true,
                'data' => [
                    'payment_id' => 'payment-command-prepare-1',
                    'transaction_id' => 'payment-command-prepare-1',
                    'gateway' => 'coins',
                    'amount' => 1250,
                    'currency' => 'PHP',
                    'status' => 'pending',
                    'qr_data' => '000201-command-prepare',
                    'expires_at' => now()->addMinutes(30)->toIso8601String(),
                    'redirect_url' => null,
                    'checkout_url' => 'https://gatewayhub.test/checkout/command-prepare',
                ],
                'error' => null,
            ], 200),
        ]);

        config()->set('services.gatewayhub.api_key', 'test-key');

        $client = User::factory()->enotarySigner()->create();
        $notary = User::factory()->for($client->organization)->notary()->create();

        $request = NotaryRequest::factory()->for($client)->create([
            'organization_id' => $client->organization_id,
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
            'title' => 'Command prepare request',
        ]);

        AttorneyNotarialRegistry::factory()->create([
            'notary_request_id' => $request->id,
            'fees' => 1250.00,
        ]);

        $exitCode = Artisan::call('notary:test-payment-flow', [
            'action' => 'prepare',
            '--request' => $request->id,
            '--gateway' => 'coins',
            '--email' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Prepared notary payment flow.', $output);
        $this->assertStringContainsString('request_id: '.$request->id, $output);
        $this->assertStringContainsString('payment_gateway: coins', $output);
        $this->assertStringContainsString('payment_status: pending', $output);
        $this->assertStringContainsString('qr_available: yes', $output);
        $this->assertStringContainsString('payment_ready_email: queued', $output);
        $this->assertStringContainsString('public_payment_url: ', $output);

        $payment = Payment::query()
            ->where('notary_request_id', $request->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($payment);
        $this->assertSame('payment-command-prepare-1', $payment->provider_payment_id);
        $this->assertSame(PaymentStatus::Pending, $payment->status);

        Mail::assertQueued(NotaryPaymentReadyMail::class, function (NotaryPaymentReadyMail $mail) use ($request, $payment): bool {
            return $mail->notaryRequest->is($request)
                && $mail->payment->is($payment);
        });
    }

    public function test_verify_mode_refreshes_latest_payment_and_reports_paid_status(): void
    {
        Http::fake([
            'https://gatewayhub.io/api/payments/payment-command-verify-1/status' => Http::response([
                'success' => true,
                'data' => [
                    'payment_id' => 'payment-command-verify-1',
                    'status' => 'success',
                    'amount' => 1250,
                    'currency' => 'PHP',
                    'gateway' => 'coins',
                    'reference' => 'NREQ-CMD-VERIFY-1',
                    'provider_reference' => 'provider-command-verify-1',
                    'paid_at' => now()->toIso8601String(),
                ],
                'error' => null,
            ], 200),
        ]);

        config()->set('services.gatewayhub.api_key', 'test-key');

        $client = User::factory()->enotarySigner()->create();
        $notary = User::factory()->for($client->organization)->notary()->create();

        $request = NotaryRequest::factory()->for($client)->create([
            'organization_id' => $client->organization_id,
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
            'title' => 'Command verify request',
        ]);

        Payment::query()->create([
            'organization_id' => $request->organization_id,
            'notary_request_id' => $request->id,
            'payer_user_id' => $client->id,
            'created_by_user_id' => $notary->id,
            'provider' => 'gatewayhub',
            'provider_payment_id' => 'payment-command-verify-1',
            'provider_transaction_id' => 'payment-command-verify-1',
            'gateway' => 'coins',
            'reference' => 'NREQ-CMD-VERIFY-1',
            'amount' => 1250.00,
            'currency' => 'PHP',
            'status' => PaymentStatus::Pending,
        ]);

        $exitCode = Artisan::call('notary:test-payment-flow', [
            'action' => 'verify',
            '--request' => $request->id,
            '--refresh' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Verified notary payment flow.', $output);
        $this->assertStringContainsString('request_id: '.$request->id, $output);
        $this->assertStringContainsString('payment_status: paid', $output);
        $this->assertStringContainsString('provider_reference: provider-command-verify-1', $output);

        $payment = Payment::query()
            ->where('notary_request_id', $request->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($payment);
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);
    }
}
