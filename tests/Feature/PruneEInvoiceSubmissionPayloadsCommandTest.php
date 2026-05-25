<?php

namespace Tests\Feature;

use App\Models\EInvoice;
use App\Models\EInvoiceSubmission;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneEInvoiceSubmissionPayloadsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_command_compacts_old_resolved_submission_payloads(): void
    {
        $admin = User::factory()->create();
        $request = \App\Models\NotaryRequest::factory()->for($admin)->create([
            'organization_id' => $admin->organization_id,
        ]);
        $payment = Payment::query()->create([
            'organization_id' => $admin->organization_id,
            'notary_request_id' => $request->id,
            'payer_user_id' => $admin->id,
            'provider' => 'gatewayhub',
            'provider_payment_id' => 'retention-payment-1',
            'provider_transaction_id' => 'retention-payment-1',
            'gateway' => 'gcash',
            'reference' => 'RETENTION-REQ-1',
            'amount' => 100.00,
            'currency' => 'PHP',
            'status' => 'paid',
            'paid_at' => now(),
        ]);
        $invoice = EInvoice::query()->create([
            'organization_id' => $admin->organization_id,
            'notary_request_id' => $request->id,
            'payment_id' => $payment->id,
            'status' => 'accepted',
            'invoice_number' => 'INV-RETENTION-1',
            'currency' => 'PHP',
            'total_amount' => 100.00,
            'issue_date' => now(),
            'seller_name' => 'DocuTrust Seller',
        ]);

        $oldResolved = EInvoiceSubmission::query()->create([
            'einvoice_id' => $invoice->id,
            'status' => 'accepted',
            'submit_id' => 'SUB-OLD',
            'request_payload' => ['invoice_number' => 'INV-RETENTION-1'],
            'response_payload' => ['status' => 'accepted'],
            'submitted_at' => now()->subDays(45),
            'resolved_at' => now()->subDays(40),
        ]);

        $recentResolved = EInvoiceSubmission::query()->create([
            'einvoice_id' => $invoice->id,
            'status' => 'accepted',
            'submit_id' => 'SUB-RECENT',
            'request_payload' => ['invoice_number' => 'INV-RETENTION-1'],
            'response_payload' => ['status' => 'accepted'],
            'submitted_at' => now()->subDays(5),
            'resolved_at' => now()->subDays(4),
        ]);

        $processing = EInvoiceSubmission::query()->create([
            'einvoice_id' => $invoice->id,
            'status' => 'processing',
            'submit_id' => 'SUB-PROCESSING',
            'request_payload' => ['invoice_number' => 'INV-RETENTION-1'],
            'response_payload' => ['status' => 'processing'],
            'submitted_at' => now()->subDays(40),
            'resolved_at' => null,
        ]);

        $exitCode = $this->artisan('app:prune-einvoice-submission-payloads', [
            '--days' => 30,
            '--limit' => 100,
        ]);

        $this->assertSame(0, $exitCode);

        $oldResolved->refresh();
        $recentResolved->refresh();
        $processing->refresh();

        $this->assertNull($oldResolved->request_payload);
        $this->assertNull($oldResolved->response_payload);
        $this->assertNotNull($oldResolved->payload_pruned_at);

        $this->assertIsArray($recentResolved->request_payload);
        $this->assertIsArray($recentResolved->response_payload);
        $this->assertNull($recentResolved->payload_pruned_at);

        $this->assertIsArray($processing->request_payload);
        $this->assertIsArray($processing->response_payload);
        $this->assertNull($processing->payload_pruned_at);
    }
}
