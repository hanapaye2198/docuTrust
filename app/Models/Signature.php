<?php

namespace App\Models;

use Database\Factories\SignatureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Signature extends Model
{
    /** @use HasFactory<SignatureFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'document_id',
        'signer_id',
        'signer_certificate_id',
        'signature_field_id',
        'signature_path',
        'submitted_value',
        'signature_value',
        'signature_hash',
        'public_key_fingerprint',
        'signature_algorithm',
        'signing_provider',
        'signing_provider_reference',
        'signing_provider_payload',
        'position_data',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signing_provider_payload' => 'array',
            'position_data' => 'array',
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
     * @return BelongsTo<SignerCertificate, $this>
     */
    public function signerCertificate(): BelongsTo
    {
        return $this->belongsTo(SignerCertificate::class);
    }

    /**
     * @return BelongsTo<SignatureField, $this>
     */
    public function signatureField(): BelongsTo
    {
        return $this->belongsTo(SignatureField::class);
    }
}
