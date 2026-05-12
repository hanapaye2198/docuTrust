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
        'role',
    ];

    /**
     * @return BelongsTo<NotaryRequest, $this>
     */
    public function notaryRequest(): BelongsTo
    {
        return $this->belongsTo(NotaryRequest::class);
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
}
