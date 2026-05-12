<?php

namespace App\Models;

use App\Enums\NotaryIdentityVerificationStatus;
use Database\Factories\NotaryIdentityVerificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaryIdentityVerification extends Model
{
    /** @use HasFactory<NotaryIdentityVerificationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'notary_request_id',
        'notary_signer_id',
        'id_type',
        'id_number',
        'id_image_path',
        'selfie_image_path',
        'verification_status',
        'verified_by',
        'verified_at',
        'rejection_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verification_status' => NotaryIdentityVerificationStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (NotaryIdentityVerification $verification): void {
            if ($verification->notary_request_id !== null) {
                return;
            }

            if ($verification->notary_signer_id === null) {
                return;
            }

            $requestId = NotarySigner::query()
                ->whereKey($verification->notary_signer_id)
                ->value('notary_request_id');

            if ($requestId !== null) {
                $verification->notary_request_id = $requestId;
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
