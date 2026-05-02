<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Signature extends Model
{
    /** @use HasFactory<\Database\Factories\SignatureFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'document_id',
        'signer_id',
        'signature_field_id',
        'signature_path',
        'signature_value',
        'signature_hash',
        'public_key_fingerprint',
        'position_data',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
     * @return BelongsTo<SignatureField, $this>
     */
    public function signatureField(): BelongsTo
    {
        return $this->belongsTo(SignatureField::class);
    }
}
