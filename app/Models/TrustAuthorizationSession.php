<?php

namespace App\Models;

use Database\Factories\TrustAuthorizationSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrustAuthorizationSession extends Model
{
    /** @use HasFactory<TrustAuthorizationSessionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'document_id',
        'document_signer_id',
        'provider_name',
        'credential_id',
        'authorization_mode',
        'status',
        'authorization_reference',
        'sad',
        'access_token',
        'expires_at',
        'completed_at',
        'consumed_at',
        'payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
            'consumed_at' => 'datetime',
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
        return $this->belongsTo(DocumentSigner::class, 'document_signer_id');
    }
}
