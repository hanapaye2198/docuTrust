<?php

namespace App\Models;

use App\Enums\SignatureFieldType;
use Database\Factories\SignatureFieldFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SignatureField extends Model
{
    /** @use HasFactory<SignatureFieldFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'document_id',
        'signer_id',
        'type',
        'page_number',
        'position_data',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SignatureFieldType::class,
            'page_number' => 'integer',
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
     * @return HasOne<Signature, $this>
     */
    public function signature(): HasOne
    {
        return $this->hasOne(Signature::class, 'signature_field_id');
    }
}
