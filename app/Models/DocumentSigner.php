<?php

namespace App\Models;

use App\Enums\DocumentSignerStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentSigner extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentSignerFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'document_id',
        'role_name',
        'name',
        'email',
        'access_token',
        'status',
        'signing_order',
        'signed_at',
        'expires_at',
        'signing_public_key',
        'signing_private_key',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'signing_private_key',
    ];

    public function getRouteKeyName(): string
    {
        return 'access_token';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DocumentSignerStatus::class,
            'signed_at' => 'datetime',
            'expires_at' => 'datetime',
            'signing_private_key' => 'encrypted',
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
     * @return HasMany<Signature, $this>
     */
    public function signatures(): HasMany
    {
        return $this->hasMany(Signature::class, 'signer_id');
    }

    /**
     * @return HasMany<SignatureField, $this>
     */
    public function signatureFields(): HasMany
    {
        return $this->hasMany(SignatureField::class, 'signer_id');
    }
}
