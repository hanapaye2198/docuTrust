<?php

namespace App\Models;

use Database\Factories\NotarialRegisterEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
     * Format the entry number as a document reference (e.g., "Doc. No. 001; Page No. 01; Book No. I; Series of 2026").
     */
    public function formattedReference(): string
    {
        return sprintf(
            'Doc. No. %s; Series of %d',
            str_pad((string) $this->entry_number, 3, '0', STR_PAD_LEFT),
            $this->entry_year,
        );
    }
}
