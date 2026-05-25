<?php

namespace App\Services;

use App\Enums\EInvoiceStatus;
use App\Enums\PaymentStatus;
use App\Jobs\RefreshEInvoiceStatusJob;
use App\Jobs\SubmitEInvoiceJob;
use App\Models\BillingProfile;
use App\Models\EInvoice;
use App\Models\EInvoiceSubmission;
use App\Models\Payment;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

class EInvoiceService
{
    public function __construct(
        private readonly EisInvoicePayloadFactory $payloadFactory,
        private readonly EisSubmissionService $submissionService,
        private readonly EisInquiryService $inquiryService,
    ) {}

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
        $invoice->loadMissing(['billingProfile', 'submissions']);

        if (! in_array($invoice->status, [EInvoiceStatus::Draft, EInvoiceStatus::NeedsCorrection], true)) {
            return $invoice;
        }

        $errors = $this->submissionPreflightErrors($invoice);
        if ($errors !== []) {
            $invoice->forceFill([
                'status' => EInvoiceStatus::NeedsCorrection,
                'error_message' => implode(' ', $errors),
            ])->save();

            throw new RuntimeException(implode(' ', $errors));
        }

        $payload = $this->payloadFactory->make($invoice);

        $invoice->forceFill([
            'status' => EInvoiceStatus::Queued,
            'queued_at' => now(),
            'error_message' => null,
        ])->save();

        EInvoiceSubmission::query()->create([
            'einvoice_id' => $invoice->id,
            'status' => EInvoiceStatus::Queued->value,
            'request_payload' => $payload,
            'response_payload' => [
                'message' => 'Queued for EIS submission.',
            ],
        ]);

        return $invoice->fresh();
    }

    public function submitQueuedInvoice(EInvoice $invoice): EInvoice
    {
        $invoice->loadMissing(['billingProfile', 'submissions']);

        if ($invoice->status === EInvoiceStatus::Draft) {
            $invoice = $this->queueForSubmission($invoice);
        }

        $errors = $this->submissionPreflightErrors($invoice);
        if ($errors !== []) {
            $invoice->forceFill([
                'status' => EInvoiceStatus::NeedsCorrection,
                'error_message' => implode(' ', $errors),
            ])->save();

            throw new RuntimeException(implode(' ', $errors));
        }

        $invoice = $this->submissionService->submit($invoice);

        if (in_array($invoice->status, [EInvoiceStatus::Submitted, EInvoiceStatus::Processing], true)) {
            RefreshEInvoiceStatusJob::dispatch($invoice->id)->delay(now()->addMinute());
        }

        return $invoice;
    }

    public function queueForBackgroundSubmission(EInvoice $invoice): EInvoice
    {
        $invoice = $this->queueForSubmission($invoice);

        SubmitEInvoiceJob::dispatch($invoice->id);

        return $invoice;
    }

    public function refreshSubmittedInvoice(EInvoice $invoice): EInvoice
    {
        $invoice->loadMissing(['billingProfile', 'submissions']);

        if (! in_array($invoice->status, [
            EInvoiceStatus::Submitted,
            EInvoiceStatus::Processing,
            EInvoiceStatus::Accepted,
            EInvoiceStatus::Rejected,
        ], true)) {
            throw new RuntimeException('Only submitted or processing e-invoices can be refreshed from EIS.');
        }

        return $this->inquiryService->refresh($invoice);
    }

    /**
     * @return array{queued:int, inquiry:int}
     */
    public function dispatchStaleInvoices(int $queuedAfterMinutes = 10, int $processingAfterMinutes = 15): array
    {
        $queuedCutoff = now()->subMinutes(max(1, $queuedAfterMinutes));
        $processingCutoff = now()->subMinutes(max(1, $processingAfterMinutes));

        $queuedInvoices = EInvoice::query()
            ->where('status', EInvoiceStatus::Queued)
            ->where(function ($query) use ($queuedCutoff): void {
                $query->where('queued_at', '<=', $queuedCutoff)
                    ->orWhere(function ($innerQuery) use ($queuedCutoff): void {
                        $innerQuery->whereNull('queued_at')
                            ->where('updated_at', '<=', $queuedCutoff);
                    });
            })
            ->get(['id']);

        $processingInvoices = EInvoice::query()
            ->whereIn('status', [EInvoiceStatus::Submitted, EInvoiceStatus::Processing])
            ->where(function ($query) use ($processingCutoff): void {
                $query->where('submitted_at', '<=', $processingCutoff)
                    ->orWhere(function ($innerQuery) use ($processingCutoff): void {
                        $innerQuery->whereNull('submitted_at')
                            ->where('updated_at', '<=', $processingCutoff);
                    });
            })
            ->get(['id']);

        $this->dispatchSubmitJobs($queuedInvoices);
        $this->dispatchInquiryJobs($processingInvoices);

        return [
            'queued' => $queuedInvoices->count(),
            'inquiry' => $processingInvoices->count(),
        ];
    }

    /**
     * @return list<string>
     */
    public function submissionPreflightErrors(EInvoice $invoice): array
    {
        $invoice->loadMissing('billingProfile');

        $profile = $invoice->billingProfile;
        $errors = [];

        if (! $profile instanceof BillingProfile || ! $profile->is_active) {
            $errors[] = 'An active billing profile is required before queueing this e-invoice.';

            return $errors;
        }

        if ($invoice->seller_name === null || trim($invoice->seller_name) === '') {
            $errors[] = 'Seller name is missing from the e-invoice.';
        }

        if ($invoice->seller_tin === null || trim($invoice->seller_tin) === '') {
            $errors[] = 'Seller TIN is required for EIS submission.';
        }

        if ($invoice->seller_branch_code === null || trim($invoice->seller_branch_code) === '') {
            $errors[] = 'Seller branch code is required for EIS submission.';
        }

        if ($profile->eis_accreditation_id === null || trim($profile->eis_accreditation_id) === '') {
            $errors[] = 'Billing profile is missing the EIS accreditation ID.';
        }

        if ($profile->eis_application_id === null || trim($profile->eis_application_id) === '') {
            $errors[] = 'Billing profile is missing the EIS application ID.';
        }

        if ($profile->eis_username === null || trim($profile->eis_username) === '') {
            $errors[] = 'Billing profile is missing the EIS username.';
        }

        if ($profile->eis_password === null || trim($profile->eis_password) === '') {
            $errors[] = 'Billing profile is missing the EIS password.';
        }

        if ($profile->eis_certificate_id === null || trim($profile->eis_certificate_id) === '') {
            $errors[] = 'Billing profile is missing the EIS certificate ID.';
        }

        return $errors;
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

    /**
     * @param  Collection<int, EInvoice>  $invoices
     */
    private function dispatchSubmitJobs(Collection $invoices): void
    {
        foreach ($invoices as $invoice) {
            SubmitEInvoiceJob::dispatch($invoice->id);
        }
    }

    /**
     * @param  Collection<int, EInvoice>  $invoices
     */
    private function dispatchInquiryJobs(Collection $invoices): void
    {
        foreach ($invoices as $invoice) {
            RefreshEInvoiceStatusJob::dispatch($invoice->id);
        }
    }
}
