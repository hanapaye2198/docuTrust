<?php

namespace App\Services;

use App\Enums\EInvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\BillingProfile;
use App\Models\EInvoice;
use App\Models\Payment;
use Illuminate\Support\Str;
use RuntimeException;

class EInvoiceService
{
    public function createDraftFromPayment(Payment $payment): EInvoice
    {
        $payment->loadMissing([
            'organization',
            'payer',
            'notaryRequest.requester',
            'registerEntry',
        ]);

        if ($payment->status !== PaymentStatus::Paid) {
            throw new RuntimeException('Only paid payments can be converted into e-invoices.');
        }

        $existing = EInvoice::query()
            ->where('payment_id', $payment->id)
            ->latest('id')
            ->first();

        if ($existing instanceof EInvoice) {
            return $existing;
        }

        $billingProfile = BillingProfile::query()
            ->where('organization_id', $payment->organization_id)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        $request = $payment->notaryRequest;
        $registerEntry = $payment->registerEntry;
        $buyer = $request?->requester ?? $payment->payer;
        $organization = $payment->organization;

        return EInvoice::query()->create([
            'organization_id' => $payment->organization_id,
            'billing_profile_id' => $billingProfile?->id,
            'notary_request_id' => $payment->notary_request_id,
            'notarial_register_entry_id' => $payment->notarial_register_entry_id,
            'payment_id' => $payment->id,
            'status' => EInvoiceStatus::Draft,
            'invoice_number' => $this->generateInvoiceNumber($payment),
            'currency' => $payment->currency,
            'total_amount' => $payment->amount,
            'issue_date' => $payment->paid_at ?? now(),
            'official_receipt_number' => $registerEntry?->official_receipt_number,
            'document_title' => $registerEntry?->document_title ?? $request?->title,
            'seller_name' => $billingProfile?->registered_name ?? $organization?->name,
            'seller_tin' => $billingProfile?->tin,
            'seller_branch_code' => $billingProfile?->branch_code,
            'seller_address' => $this->sellerAddress($billingProfile),
            'seller_email' => $billingProfile?->email,
            'buyer_name' => $buyer?->name,
            'buyer_tin' => null,
            'buyer_address' => $buyer?->address,
            'buyer_email' => $buyer?->email,
            'source_payload' => [
                'payment' => [
                    'id' => $payment->id,
                    'reference' => $payment->reference,
                    'provider_payment_id' => $payment->provider_payment_id,
                    'gateway' => $payment->gateway,
                    'amount' => (float) $payment->amount,
                    'currency' => $payment->currency,
                    'paid_at' => optional($payment->paid_at)->toIso8601String(),
                ],
                'notary_request' => [
                    'id' => $request?->id,
                    'title' => $request?->title,
                ],
                'register_entry' => [
                    'id' => $registerEntry?->id,
                    'entry_number' => $registerEntry?->entry_number,
                    'entry_year' => $registerEntry?->entry_year,
                    'document_title' => $registerEntry?->document_title,
                    'official_receipt_number' => $registerEntry?->official_receipt_number,
                ],
            ],
        ]);
    }

    public function queueForSubmission(EInvoice $invoice): EInvoice
    {
        if ($invoice->status !== EInvoiceStatus::Draft) {
            return $invoice;
        }

        $invoice->forceFill([
            'status' => EInvoiceStatus::Queued,
            'queued_at' => now(),
        ])->save();

        return $invoice->fresh();
    }

    private function generateInvoiceNumber(Payment $payment): string
    {
        $reference = Str::upper(Str::slug((string) $payment->reference, ''));

        return sprintf(
            'INV-%s-%s',
            now()->format('Ymd'),
            Str::limit($reference !== '' ? $reference : (string) $payment->id, 16, '')
        );
    }

    private function sellerAddress(?BillingProfile $billingProfile): ?string
    {
        if (! $billingProfile instanceof BillingProfile) {
            return null;
        }

        $parts = array_filter([
            $billingProfile->address_line,
            $billingProfile->city,
            $billingProfile->state,
            $billingProfile->postal_code,
            $billingProfile->country_code,
        ], fn (?string $value): bool => is_string($value) && trim($value) !== '');

        if ($parts === []) {
            return null;
        }

        return implode(', ', $parts);
    }
}
