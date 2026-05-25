<?php

namespace Tests\Feature;

use App\Enums\EInvoiceStatus;
use App\Jobs\RefreshEInvoiceStatusJob;
use App\Jobs\SubmitEInvoiceJob;
use App\Models\BillingProfile;
use App\Models\EInvoice;
use App\Models\NotaryRequest;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EInvoiceRecoveryCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_recovery_command_re_dispatches_stale_queued_and_processing_einvoices(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $request = NotaryRequest::factory()->for($user)->create([
            'organization_id' => $user->organization_id,
        ]);

        $queuedPayment = Payment::query()->create([
            'organization_id' => $user->organization_id,
            'notary_request_id' => $request->id,
            'payer_user_id' => $user->id,
            'provider' => 'gatewayhub',
            'provider_payment_id' => 'recover-payment-1',
            'provider_transaction_id' => 'recover-payment-1',
            'gateway' => 'gcash',
            'reference' => 'RECOVER-QUEUED-'.$request->id,
            'amount' => 600.00,
            'currency' => 'PHP',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $processingPayment = Payment::query()->create([
            'organization_id' => $user->organization_id,
            'notary_request_id' => $request->id,
            'payer_user_id' => $user->id,
            'provider' => 'gatewayhub',
            'provider_payment_id' => 'recover-payment-2',
            'provider_transaction_id' => 'recover-payment-2',
            'gateway' => 'gcash',
            'reference' => 'RECOVER-PROC-'.$request->id,
            'amount' => 600.00,
            'currency' => 'PHP',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $freshPayment = Payment::query()->create([
            'organization_id' => $user->organization_id,
            'notary_request_id' => $request->id,
            'payer_user_id' => $user->id,
            'provider' => 'gatewayhub',
            'provider_payment_id' => 'recover-payment-3',
            'provider_transaction_id' => 'recover-payment-3',
            'gateway' => 'gcash',
            'reference' => 'RECOVER-FRESH-'.$request->id,
            'amount' => 600.00,
            'currency' => 'PHP',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $profile = BillingProfile::query()->create([
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

        $queuedInvoice = EInvoice::query()->create([
            'organization_id' => $user->organization_id,
            'billing_profile_id' => $profile->id,
            'notary_request_id' => $request->id,
            'payment_id' => $queuedPayment->id,
            'status' => EInvoiceStatus::Queued->value,
            'queued_at' => now()->subMinutes(20),
            'invoice_number' => 'INV-RECOVER-QUEUED',
            'currency' => 'PHP',
            'total_amount' => 600.00,
            'issue_date' => now(),
            'seller_name' => 'DocuTrust Test Seller',
            'seller_tin' => '123-456-789-000',
            'seller_branch_code' => '000',
        ]);

        $processingInvoice = EInvoice::query()->create([
            'organization_id' => $user->organization_id,
            'billing_profile_id' => $profile->id,
            'notary_request_id' => $request->id,
            'payment_id' => $processingPayment->id,
            'status' => EInvoiceStatus::Processing->value,
            'submitted_at' => now()->subMinutes(30),
            'submit_id' => 'recover-submit-1',
            'invoice_number' => 'INV-RECOVER-PROC',
            'currency' => 'PHP',
            'total_amount' => 600.00,
            'issue_date' => now(),
            'seller_name' => 'DocuTrust Test Seller',
            'seller_tin' => '123-456-789-000',
            'seller_branch_code' => '000',
        ]);

        $freshQueuedInvoice = EInvoice::query()->create([
            'organization_id' => $user->organization_id,
            'billing_profile_id' => $profile->id,
            'notary_request_id' => $request->id,
            'payment_id' => $freshPayment->id,
            'status' => EInvoiceStatus::Queued->value,
            'queued_at' => now()->subMinutes(2),
            'invoice_number' => 'INV-RECOVER-FRESH',
            'currency' => 'PHP',
            'total_amount' => 600.00,
            'issue_date' => now(),
            'seller_name' => 'DocuTrust Test Seller',
            'seller_tin' => '123-456-789-000',
            'seller_branch_code' => '000',
        ]);

        $this->assertSame(0, $this->artisan('app:recover-stale-einvoices', [
            '--queued-minutes' => 10,
            '--processing-minutes' => 15,
        ]));

        Queue::assertPushed(SubmitEInvoiceJob::class, function (SubmitEInvoiceJob $job) use ($queuedInvoice): bool {
            return $job->einvoiceId === $queuedInvoice->id;
        });

        Queue::assertPushed(RefreshEInvoiceStatusJob::class, function (RefreshEInvoiceStatusJob $job) use ($processingInvoice): bool {
            return $job->einvoiceId === $processingInvoice->id;
        });

        Queue::assertNotPushed(SubmitEInvoiceJob::class, function (SubmitEInvoiceJob $job) use ($freshQueuedInvoice): bool {
            return $job->einvoiceId === $freshQueuedInvoice->id;
        });
    }
}
