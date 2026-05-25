<?php

namespace Tests\Feature;

use App\Enums\EInvoiceStatus;
use App\Enums\UserRole;
use App\Models\BillingProfile;
use App\Models\EInvoice;
use App\Models\NotaryRequest;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotaryAdminEInvoicesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_notary_admin_can_view_einvoices_page_and_filter_by_status(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::NotaryAdmin,
        ]);

        $client = User::factory()->client()->create([
            'organization_id' => $admin->organization_id,
        ]);

        $request = NotaryRequest::factory()->create([
            'user_id' => $client->id,
            'organization_id' => $admin->organization_id,
        ]);

        $profile = BillingProfile::query()->create([
            'organization_id' => $admin->organization_id,
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

        $acceptedPayment = Payment::query()->create([
            'organization_id' => $admin->organization_id,
            'notary_request_id' => $request->id,
            'payer_user_id' => $client->id,
            'provider' => 'gatewayhub',
            'provider_payment_id' => 'page-payment-1',
            'provider_transaction_id' => 'page-payment-1',
            'gateway' => 'gcash',
            'reference' => 'PAGE-REQ-1',
            'amount' => 500.00,
            'currency' => 'PHP',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $rejectedPayment = Payment::query()->create([
            'organization_id' => $admin->organization_id,
            'notary_request_id' => $request->id,
            'payer_user_id' => $client->id,
            'provider' => 'gatewayhub',
            'provider_payment_id' => 'page-payment-2',
            'provider_transaction_id' => 'page-payment-2',
            'gateway' => 'maya',
            'reference' => 'PAGE-REQ-2',
            'amount' => 750.00,
            'currency' => 'PHP',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        EInvoice::query()->create([
            'organization_id' => $admin->organization_id,
            'billing_profile_id' => $profile->id,
            'notary_request_id' => $request->id,
            'payment_id' => $acceptedPayment->id,
            'status' => EInvoiceStatus::Accepted->value,
            'invoice_number' => 'INV-ADMIN-ACCEPTED',
            'currency' => 'PHP',
            'total_amount' => 500.00,
            'issue_date' => now(),
            'seller_name' => 'DocuTrust Test Seller',
            'seller_tin' => '123-456-789-000',
            'seller_branch_code' => '000',
            'buyer_name' => $client->name,
            'document_title' => 'Accepted invoice',
            'accepted_at' => now(),
        ]);

        EInvoice::query()->create([
            'organization_id' => $admin->organization_id,
            'billing_profile_id' => $profile->id,
            'notary_request_id' => $request->id,
            'payment_id' => $rejectedPayment->id,
            'status' => EInvoiceStatus::Rejected->value,
            'invoice_number' => 'INV-ADMIN-REJECTED',
            'currency' => 'PHP',
            'total_amount' => 750.00,
            'issue_date' => now(),
            'seller_name' => 'DocuTrust Test Seller',
            'seller_tin' => '123-456-789-000',
            'seller_branch_code' => '000',
            'buyer_name' => $client->name,
            'document_title' => 'Rejected invoice',
            'error_message' => 'Buyer TIN is invalid.',
            'rejected_at' => now(),
        ]);

        $outsideAdmin = User::factory()->create([
            'role' => UserRole::NotaryAdmin,
        ]);

        $outsideClient = User::factory()->client()->create([
            'organization_id' => $outsideAdmin->organization_id,
        ]);

        $outsideRequest = NotaryRequest::factory()->create([
            'user_id' => $outsideClient->id,
            'organization_id' => $outsideAdmin->organization_id,
        ]);

        $outsideProfile = BillingProfile::query()->create([
            'organization_id' => $outsideAdmin->organization_id,
            'registered_name' => 'Other Seller',
            'tin' => '999-999-999-999',
            'branch_code' => '001',
            'email' => 'other@example.test',
            'address_line' => '999 Other Street',
            'city' => 'Cebu City',
            'state' => 'Cebu',
            'postal_code' => '6000',
            'country_code' => 'PH',
            'eis_environment' => 'sandbox',
            'eis_accreditation_id' => 'ACCRED-2',
            'eis_application_id' => 'APP-2',
            'eis_username' => 'eis-user-2',
            'eis_password' => 'eis-pass-2',
            'eis_certificate_id' => 'CERT-2',
            'is_active' => true,
        ]);

        $outsidePayment = Payment::query()->create([
            'organization_id' => $outsideAdmin->organization_id,
            'notary_request_id' => $outsideRequest->id,
            'payer_user_id' => $outsideClient->id,
            'provider' => 'gatewayhub',
            'provider_payment_id' => 'page-payment-3',
            'provider_transaction_id' => 'page-payment-3',
            'gateway' => 'gcash',
            'reference' => 'PAGE-REQ-3',
            'amount' => 900.00,
            'currency' => 'PHP',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        EInvoice::query()->create([
            'organization_id' => $outsideAdmin->organization_id,
            'billing_profile_id' => $outsideProfile->id,
            'notary_request_id' => $outsideRequest->id,
            'payment_id' => $outsidePayment->id,
            'status' => EInvoiceStatus::Accepted->value,
            'invoice_number' => 'INV-OTHER-ORG',
            'currency' => 'PHP',
            'total_amount' => 900.00,
            'issue_date' => now(),
            'seller_name' => 'Other Seller',
            'seller_tin' => '999-999-999-999',
            'seller_branch_code' => '001',
            'buyer_name' => $outsideClient->name,
            'document_title' => 'Outside org invoice',
            'accepted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('notary-admin.einvoices'))
            ->assertOk()
            ->assertSee('E-Invoices')
            ->assertSee('INV-ADMIN-ACCEPTED')
            ->assertSee('INV-ADMIN-REJECTED')
            ->assertSee('Buyer TIN is invalid.')
            ->assertDontSee('INV-OTHER-ORG');

        $this->actingAs($admin)
            ->get(route('notary-admin.einvoices', ['status' => EInvoiceStatus::Accepted->value]))
            ->assertOk()
            ->assertSee('INV-ADMIN-ACCEPTED')
            ->assertDontSee('INV-ADMIN-REJECTED');
    }

    public function test_client_cannot_access_notary_admin_einvoices_page(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)
            ->get(route('notary-admin.einvoices'))
            ->assertRedirect(route($client->homeRouteName()));
    }
}
