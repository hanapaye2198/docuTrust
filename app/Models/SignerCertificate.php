<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SignerCertificate extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'document_signer_id',
        'certificate_authority_id',
        'certificate_source',
        'provider_name',
        'provider_reference',
        'subject_dn',
        'issuer_dn',
        'serial_number',
        'public_key_pem',
        'certificate_pem',
        'issuer_certificate_pem',
        'fingerprint_sha256',
        'valid_from',
        'valid_to',
        'revoked_at',
        'revocation_reason',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DocumentSigner, $this>
     */
    public function documentSigner(): BelongsTo
    {
        return $this->belongsTo(DocumentSigner::class);
    }

    /**
     * @return BelongsTo<CertificateAuthority, $this>
     */
    public function certificateAuthority(): BelongsTo
    {
        return $this->belongsTo(CertificateAuthority::class);
    }

    /**
     * @return HasMany<Signature, $this>
     */
    public function signatures(): HasMany
    {
        return $this->hasMany(Signature::class);
    }
}
