<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignatureEvidenceRecord extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'document_id',
        'signer_id',
        'signature_id',
        'signer_identity',
        'ip_address',
        'user_agent',
        'device_info',
        'signed_at',
        'document_hash',
        'signature_hash',
        'signature_algorithm',
        'blockchain_txn',
        'otp_verified',
        'otp_method',
        'signing_provider',
        'signing_provider_payload',
        'audit_trail_snapshot',
        'pades_profile',
        'cms_signature_hash',
        'tsr_hash',
        'ltv_applied',
        'csc_provider',
        'csc_transaction_id',
        'validation_snapshot',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signer_identity' => 'array',
            'signing_provider_payload' => 'array',
            'audit_trail_snapshot' => 'array',
            'ltv_applied' => 'boolean',
            'validation_snapshot' => 'array',
            'signed_at' => 'datetime',
            'otp_verified' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<DocumentSigner, $this>
     */
    public function signer(): BelongsTo
    {
        return $this->belongsTo(DocumentSigner::class, 'signer_id');
    }

    /**
     * @return BelongsTo<Signature, $this>
     */
    public function signature(): BelongsTo
    {
        return $this->belongsTo(Signature::class);
    }
}
