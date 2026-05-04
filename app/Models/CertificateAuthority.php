<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CertificateAuthority extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'subject_dn',
        'issuer_dn',
        'serial_number',
        'public_key_pem',
        'private_key_pem',
        'certificate_pem',
        'fingerprint_sha256',
        'valid_from',
        'valid_to',
        'is_root',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'private_key_pem' => 'encrypted',
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
            'is_root' => 'boolean',
        ];
    }

    /**
     * @return HasMany<SignerCertificate, $this>
     */
    public function signerCertificates(): HasMany
    {
        return $this->hasMany(SignerCertificate::class);
    }
}
