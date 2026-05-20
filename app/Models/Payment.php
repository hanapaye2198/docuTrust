<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'notary_request_id',
        'notarial_register_entry_id',
        'payer_user_id',
        'created_by_user_id',
        'provider',
        'provider_payment_id',
        'provider_transaction_id',
        'gateway',
        'reference',
        'amount',
        'currency',
        'status',
        'qr_data',
        'redirect_url',
        'checkout_url',
        'provider_reference',
        'failure_message',
        'expires_at',
        'paid_at',
        'last_verified_at',
        'provider_payload',
        'webhook_payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => PaymentStatus::class,
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'last_verified_at' => 'datetime',
            'provider_payload' => 'array',
            'webhook_payload' => 'array',
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
     * @return BelongsTo<User, $this>
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasOne<EInvoice, $this>
     */
    public function eInvoice(): HasOne
    {
        return $this->hasOne(EInvoice::class)->latestOfMany();
    }
}
