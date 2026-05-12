<?php

namespace App\Models;

use App\Enums\NotaryGeoVerificationStatus;
use Database\Factories\NotaryGeoLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaryGeoLog extends Model
{
    /** @use HasFactory<NotaryGeoLogFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'notary_request_id',
        'notary_signer_id',
        'ip_address',
        'country',
        'city',
        'latitude',
        'longitude',
        'vpn_detected',
        'verification_status',
        'verified_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verification_status' => NotaryGeoVerificationStatus::class,
            'vpn_detected' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (NotaryGeoLog $log): void {
            if ($log->notary_request_id !== null) {
                return;
            }

            if ($log->notary_signer_id === null) {
                return;
            }

            $requestId = NotarySigner::query()
                ->whereKey($log->notary_signer_id)
                ->value('notary_request_id');

            if ($requestId !== null) {
                $log->notary_request_id = $requestId;
            }
        });
    }

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
    public function signer(): BelongsTo
    {
        return $this->belongsTo(NotarySigner::class, 'notary_signer_id');
    }
}
