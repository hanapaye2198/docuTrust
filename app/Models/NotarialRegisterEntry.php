<?php

namespace App\Models;

use Database\Factories\NotarialRegisterEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotarialRegisterEntry extends Model
{
    /** @use HasFactory<NotarialRegisterEntryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'notary_request_id',
        'notary_credential_id',
        'document_id',
        'entry_number',
        'entry_year',
        'page_number',
        'book_number',
        'document_title',
        'document_description',
        'parties',
        'witnesses',
        'competent_evidence',
        'notarized_at',
        'notarial_act_type',
        'fees',
        'official_receipt_number',
        'notary_signature_path',
        'qr_code_path',
        'qr_verification_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parties' => 'array',
            'witnesses' => 'array',
            'competent_evidence' => 'array',
            'notarized_at' => 'datetime',
            'fees' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<NotaryRequest, $this>
     */
    public function notaryRequest(): BelongsTo
    {
        return $this->belongsTo(NotaryRequest::class);
    }

    /**
     * @return BelongsTo<NotaryCredential, $this>
     */
    public function notaryCredential(): BelongsTo
    {
        return $this->belongsTo(NotaryCredential::class);
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'notarial_register_entry_id')->latest('created_at');
    }

    /**
     * @return HasMany<EInvoice, $this>
     */
    public function eInvoices(): HasMany
    {
        return $this->hasMany(EInvoice::class, 'notarial_register_entry_id')->latest('created_at');
    }

    /**
     * Format the entry number as a document reference (e.g., "Doc. No. 001; Page No. 01; Book No. I; Series of 2026").
     */
    public function formattedReference(): string
    {
        $parts = [
            sprintf('Doc. No. %s', str_pad((string) $this->entry_number, 3, '0', STR_PAD_LEFT)),
        ];

        if ($this->page_number !== null) {
            $parts[] = sprintf('Page No. %s', str_pad((string) $this->page_number, 2, '0', STR_PAD_LEFT));
        }

        if ($this->book_number !== null && $this->book_number !== '') {
            $parts[] = sprintf('Book No. %s', $this->book_number);
        }

        $parts[] = sprintf('Series of %d', $this->entry_year);

        return implode('; ', $parts);
    }
}
