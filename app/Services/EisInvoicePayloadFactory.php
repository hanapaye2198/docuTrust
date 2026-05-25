<?php

namespace App\Services;

use App\Models\EInvoice;

class EisInvoicePayloadFactory
{
    /**
     * Build the internal submission payload that will later be transformed into
     * the encrypted BIR EIS request body.
     *
     * @return array<string, mixed>
     */
    public function make(EInvoice $invoice): array
    {
        $invoice->loadMissing(['billingProfile', 'payment', 'registerEntry', 'notaryRequest']);

        return [
            'invoice_number' => $invoice->invoice_number,
            'issue_date' => optional($invoice->issue_date)->toIso8601String(),
            'currency' => $invoice->currency,
            'document_title' => $invoice->document_title,
            'official_receipt_number' => $invoice->official_receipt_number,
            'totals' => [
                'grand_total' => (float) $invoice->total_amount,
                'amount_due' => (float) $invoice->total_amount,
            ],
            'seller' => [
                'name' => $invoice->seller_name,
                'tin' => $invoice->seller_tin,
                'branch_code' => $invoice->seller_branch_code,
                'address' => $invoice->seller_address,
                'email' => $invoice->seller_email,
            ],
            'buyer' => [
                'name' => $invoice->buyer_name,
                'tin' => $invoice->buyer_tin,
                'address' => $invoice->buyer_address,
                'email' => $invoice->buyer_email,
            ],
            'payment' => [
                'provider' => $invoice->payment?->provider,
                'provider_payment_id' => $invoice->payment?->provider_payment_id,
                'provider_reference' => $invoice->payment?->provider_reference,
                'gateway' => $invoice->payment?->gateway,
                'paid_at' => optional($invoice->payment?->paid_at)->toIso8601String(),
            ],
            'register_entry' => [
                'entry_number' => $invoice->registerEntry?->entry_number,
                'entry_year' => $invoice->registerEntry?->entry_year,
                'page_number' => $invoice->registerEntry?->page_number,
                'book_number' => $invoice->registerEntry?->book_number,
                'notarial_act_type' => $invoice->registerEntry?->notarial_act_type,
            ],
            'submission_context' => [
                'environment' => $invoice->billingProfile?->eis_environment ?? config('services.eis.environment', 'sandbox'),
                'organization_id' => $invoice->organization_id,
                'notary_request_id' => $invoice->notary_request_id,
                'payment_id' => $invoice->payment_id,
            ],
        ];
    }
}
