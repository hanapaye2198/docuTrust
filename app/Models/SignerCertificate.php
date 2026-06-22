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
        'csc_credential_id',
        'subject_dn',
        'issuer_dn',
        'serial_number',
        'public_key_pem',
        'certificate_pem',
        'issuer_certificate_pem',
        'certificate_chain',
        'fingerprint_sha256',
        'valid_from',
        'valid_to',
        'valid_until',
        'key_algorithm',
        'key_size',
        'ocsp_url',
        'crl_url',
        'ocsp_staple',
        'ocsp_checked_at',
        'revocation_status',
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
            'certificate_chain' => 'array',
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
            'valid_until' => 'datetime',
            'ocsp_checked_at' => 'datetime',
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
