<?php

namespace App\Models;

use App\Enums\NotaryRequestStatus;
use Database\Factories\NotaryRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotaryRequest extends Model
{
    /** @use HasFactory<NotaryRequestFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'user_id',
        'notary_user_id',
        'title',
        'status',
        'request_type',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'completed_at',
        'rejection_reason',
        'metadata',
        'document_path',
        'remarks',
        'id_document_type',
        'id_document_number',
        'id_document_path',
        'selfie_path',
        'identity_verified_at',
        'verified_at',
        'location_verified_at',
        'location_ip_address',
        'location_country_code',
        'location_latitude',
        'location_longitude',
        'location_vpn_detected',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => NotaryRequestStatus::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'completed_at' => 'datetime',
            'notarized_at' => 'datetime',
            'identity_verified_at' => 'datetime',
            'verified_at' => 'datetime',
            'location_verified_at' => 'datetime',
            'location_vpn_detected' => 'boolean',
            'location_latitude' => 'decimal:7',
            'location_longitude' => 'decimal:7',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Backward-compatible alias for factory relationship helpers.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->requester();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function notary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'notary_user_id');
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * @return HasMany<NotarySession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(NotarySession::class)->latest('scheduled_for');
    }

    /**
     * @return HasMany<NotaryJournal, $this>
     */
    public function journals(): HasMany
    {
        return $this->hasMany(NotaryJournal::class)->latest('recorded_at');
    }

    /**
     * @return HasMany<NotarySigner, $this>
     */
    public function signers(): HasMany
    {
        return $this->hasMany(NotarySigner::class)->orderBy('id');
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
     * @return HasMany<NotarialRegisterEntry, $this>
     */
    public function registerEntries(): HasMany
    {
        return $this->hasMany(NotarialRegisterEntry::class)->orderByDesc('entry_number');
    }

    public function markSubmitted(): void
    {
        $this->forceFill([
            'status' => NotaryRequestStatus::Submitted,
            'submitted_at' => now(),
        ])->save();
    }

    public function markApproved(): void
    {
        $this->forceFill([
            'status' => NotaryRequestStatus::AttorneyApproved,
            'approved_at' => now(),
            'rejected_at' => null,
            'rejection_reason' => null,
        ])->save();
    }

    public function markRejected(string $reason): void
    {
        $this->forceFill([
            'status' => NotaryRequestStatus::Rejected,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ])->save();
    }

    public function markNotarized(): void
    {
        $now = now();
        $this->forceFill([
            'status' => NotaryRequestStatus::Notarized,
            'completed_at' => $now,
            'notarized_at' => $now,
        ])->save();
    }

    public function markIdentityVerified(): void
    {
        $now = now();
        $this->forceFill([
            'status' => NotaryRequestStatus::IdentityVerified,
            'identity_verified_at' => $now,
            'verified_at' => $now,
        ])->save();
    }

    public function markCancelled(): void
    {
        $this->forceFill([
            'status' => NotaryRequestStatus::Cancelled,
        ])->save();
    }

    public function markFailed(string $reason = ''): void
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];
        $metadata['failure_reason'] = $reason;

        $this->forceFill([
            'status' => NotaryRequestStatus::Failed,
            'metadata' => $metadata,
        ])->save();
    }

    protected static function booted(): void
    {
        static::creating(function (NotaryRequest $request): void {
            if ($request->organization_id !== null) {
                return;
            }

            if ($request->user_id === null) {
                return;
            }

            $request->organization_id = User::query()
                ->whereKey($request->user_id)
                ->value('organization_id');
        });
    }
}
