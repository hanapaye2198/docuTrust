<?php

namespace App\Models;

use Database\Factories\NotarySignerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotarySigner extends Model
{
    /** @use HasFactory<NotarySignerFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'notary_request_id',
        'full_name',
        'email',
        'phone',
        'address',
        'id_document_path',
        'role',
        'witnessed_signer_id',
    ];

    /**
     * @return BelongsTo<NotaryRequest, $this>
     */
    public function notaryRequest(): BelongsTo
    {
        return $this->belongsTo(NotaryRequest::class);
    }

    /**
     * @return BelongsTo<NotarySigner, $this>
     */
    public function witnessedSigner(): BelongsTo
    {
        return $this->belongsTo(self::class, 'witnessed_signer_id');
    }

    /**
     * @return HasMany<NotarySigner, $this>
     */
    public function witnesses(): HasMany
    {
        return $this->hasMany(self::class, 'witnessed_signer_id');
    }

    /**
     * @return HasMany<NotaryIdentityVerification, $this>
     */
    public function identityVerifications(): HasMany
    {
        return $this->hasMany(NotaryIdentityVerification::class)->latest();
    }

    /**
     * @return HasMany<NotaryGeoLog, $this>
     */
    public function geoLogs(): HasMany
    {
        return $this->hasMany(NotaryGeoLog::class)->latest();
    }

    /**
     * @return HasMany<NotarySession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(NotarySession::class);
    }
}
