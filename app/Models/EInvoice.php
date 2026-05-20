<?php

namespace App\Models;

use App\Enums\EInvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EInvoice extends Model
{
    protected $table = 'einvoices';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'billing_profile_id',
        'notary_request_id',
        'notarial_register_entry_id',
        'payment_id',
        'status',
        'invoice_number',
        'currency',
        'total_amount',
        'issue_date',
        'official_receipt_number',
        'document_title',
        'seller_name',
        'seller_tin',
        'seller_branch_code',
        'seller_address',
        'seller_email',
        'buyer_name',
        'buyer_tin',
        'buyer_address',
        'buyer_email',
        'source_payload',
        'eis_unique_id',
        'submit_id',
        'error_message',
        'queued_at',
        'submitted_at',
        'accepted_at',
        'rejected_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EInvoiceStatus::class,
            'total_amount' => 'decimal:2',
            'issue_date' => 'datetime',
            'queued_at' => 'datetime',
            'submitted_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'source_payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<BillingProfile, $this>
     */
    public function billingProfile(): BelongsTo
    {
        return $this->belongsTo(BillingProfile::class);
    }

    /**
     * @return BelongsTo<NotaryRequest, $this>
     */
    public function notaryRequest(): BelongsTo
    {
        return $this->belongsTo(NotaryRequest::class);
    }

    /**
     * @return BelongsTo<NotarialRegisterEntry, $this>
     */
    public function registerEntry(): BelongsTo
    {
        return $this->belongsTo(NotarialRegisterEntry::class, 'notarial_register_entry_id');
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return HasMany<EInvoiceSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(EInvoiceSubmission::class)->latest('created_at');
    }
}
