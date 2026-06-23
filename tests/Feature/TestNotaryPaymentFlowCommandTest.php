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

    public function test_notary_payments_page_shows_summary_and_history_for_assigned_cases(): void
    {
        $client = User::factory()->enotarySigner()->create();
        $notary = User::factory()->for($client->organization)->notary()->create();
        $otherNotary = User::factory()->for($client->organization)->notary()->create();

        $paidRequest = NotaryRequest::factory()->for($client)->create([
            'organization_id' => $client->organization_id,
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::Notarized,
            'title' => 'Paid affidavit request',
        ]);

        $pendingRequest = NotaryRequest::factory()->for($client)->create([
            'organization_id' => $client->organization_id,
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
            'title' => 'Pending deed request',
        ]);

        $otherRequest = NotaryRequest::factory()->for($client)->create([
            'organization_id' => $client->organization_id,
            'notary_user_id' => $otherNotary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
            'title' => 'Other attorney payment',
        ]);

        $noFeeRequest = NotaryRequest::factory()->for($client)->create([
            'organization_id' => $client->organization_id,
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
            'title' => 'No fee certification request',
        ]);
        AttorneyNotarialRegistry::factory()->create([
            'notary_request_id' => $noFeeRequest->id,
            'fees' => 0,
        ]);

        Payment::query()->create([
            'organization_id' => $paidRequest->organization_id,
            'notary_request_id' => $paidRequest->id,
            'payer_user_id' => $client->id,
            'created_by_user_id' => $notary->id,
            'provider' => 'gatewayhub',
            'provider_payment_id' => 'payment-page-paid-1',
            'provider_transaction_id' => 'payment-page-paid-1',
            'gateway' => 'coins',
            'reference' => 'NREQ-PAGE-PAID-1',
            'amount' => 1000.00,
            'currency' => 'PHP',
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        Payment::query()->create([
            'organization_id' => $pendingRequest->organization_id,
            'notary_request_id' => $pendingRequest->id,
            'payer_user_id' => $client->id,
            'created_by_user_id' => $notary->id,
            'provider' => 'gatewayhub',
            'provider_payment_id' => 'payment-page-pending-1',
            'provider_transaction_id' => 'payment-page-pending-1',
            'gateway' => 'coins',
            'reference' => 'NREQ-PAGE-PENDING-1',
            'amount' => 250.00,
            'currency' => 'PHP',
            'status' => PaymentStatus::Pending,
        ]);

        Payment::query()->create([
            'organization_id' => $otherRequest->organization_id,
            'notary_request_id' => $otherRequest->id,
            'payer_user_id' => $client->id,
            'created_by_user_id' => $otherNotary->id,
            'provider' => 'gatewayhub',
            'provider_payment_id' => 'payment-page-other-1',
            'provider_transaction_id' => 'payment-page-other-1',
            'gateway' => 'coins',
            'reference' => 'NREQ-PAGE-OTHER-1',
            'amount' => 9999.00,
            'currency' => 'PHP',
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        $this->actingAs($notary)
            ->get(route('notary.payments'))
            ->assertOk()
            ->assertSee(__('Fees collected across all your notarization cases'), false)
            ->assertSee('₱1,000.00', false)
            ->assertSee('₱250.00', false)
            ->assertSee('Paid affidavit request', false)
            ->assertSee('Pending deed request', false)
            ->assertSee('No fee certification request', false)
            ->assertSee(__('Not required'), false)
            ->assertSee(route('notary.requests.show', $paidRequest), false)
            ->assertDontSee('Other attorney payment', false)
            ->assertDontSee('₱9,999.00', false);
    }

    public function test_notary_payments_page_filters_by_status_and_period(): void
    {
        $client = User::factory()->enotarySigner()->create();
        $notary = User::factory()->for($client->organization)->notary()->create();

        $currentRequest = NotaryRequest::factory()->for($client)->create([
            'organization_id' => $client->organization_id,
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::Notarized,
            'title' => 'Current paid request',
        ]);

        $lastMonthRequest = NotaryRequest::factory()->for($client)->create([
            'organization_id' => $client->organization_id,
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
            'title' => 'Last month pending request',
        ]);

        Payment::query()->create([
            'organization_id' => $currentRequest->organization_id,
            'notary_request_id' => $currentRequest->id,
            'payer_user_id' => $client->id,
            'created_by_user_id' => $notary->id,
            'provider' => 'gatewayhub',
            'provider_payment_id' => 'payment-filter-paid-1',
            'provider_transaction_id' => 'payment-filter-paid-1',
            'gateway' => 'coins',
            'reference' => 'NREQ-FILTER-PAID-1',
            'amount' => 400.00,
            'currency' => 'PHP',
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        $lastMonthPayment = Payment::query()->create([
            'organization_id' => $lastMonthRequest->organization_id,
            'notary_request_id' => $lastMonthRequest->id,
            'payer_user_id' => $client->id,
            'created_by_user_id' => $notary->id,
            'provider' => 'gatewayhub',
            'provider_payment_id' => 'payment-filter-pending-1',
            'provider_transaction_id' => 'payment-filter-pending-1',
            'gateway' => 'coins',
            'reference' => 'NREQ-FILTER-PENDING-1',
            'amount' => 175.00,
            'currency' => 'PHP',
            'status' => PaymentStatus::Pending,
        ]);
        $lastMonthPayment->forceFill([
            'created_at' => now()->subMonthNoOverflow(),
            'updated_at' => now()->subMonthNoOverflow(),
        ])->save();

        $this->actingAs($notary)
            ->get(route('notary.payments', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('NREQ-FILTER-PENDING-1', false)
            ->assertDontSee('NREQ-FILTER-PAID-1', false);

        $this->actingAs($notary)
            ->get(route('notary.payments', ['period' => 'last_month']))
            ->assertOk()
            ->assertSee('NREQ-FILTER-PENDING-1', false)
            ->assertDontSee('NREQ-FILTER-PAID-1', false);
    }

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

        $recipientEmail = 'command-payment@example.test';

        $exitCode = Artisan::call('notary:test-payment-flow', [
            'action' => 'prepare',
            '--request' => $request->id,
            '--gateway' => 'coins',
            '--recipient' => $recipientEmail,
            '--email' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Prepared notary payment flow.', $output);
        $this->assertStringContainsString('request_id: '.$request->id, $output);
        $this->assertStringContainsString('recipient_email: '.$recipientEmail, $output);
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

        Mail::assertQueued(NotaryPaymentReadyMail::class, function (NotaryPaymentReadyMail $mail) use ($request, $payment, $recipientEmail): bool {
            return $mail->notaryRequest->is($request)
                && $mail->payment->is($payment)
                && $mail->hasTo($recipientEmail);
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
