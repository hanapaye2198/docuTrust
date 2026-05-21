<?php

namespace App\Models;

use App\Enums\NotaryCredentialStatus;
use Database\Factories\NotaryCredentialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotaryCredential extends Model
{
    /** @use HasFactory<NotaryCredentialFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'commission_number',
        'commission_jurisdiction',
        'commission_issued_at',
        'commission_expires_at',
        'roll_number',
        'ibp_number',
        'ptr_number',
        'mcle_compliance_number',
        'seal_image_path',
        'signature_image_path',
        'commission_document_path',
        'ibp_document_path',
        'ptr_document_path',
        'mcle_document_path',
        'status',
        'rejection_reason',
        'reviewed_by_user_id',
        'reviewed_at',
        'submitted_at',
        'is_renewal',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'commission_issued_at' => 'date',
            'commission_expires_at' => 'date',
            'reviewed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'is_renewal' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /**
     * @return HasMany<NotarialRegisterEntry, $this>
     */
    public function registerEntries(): HasMany
    {
        return $this->hasMany(NotarialRegisterEntry::class)->orderByDesc('entry_number');
    }

    public function isPending(): bool
    {
        return $this->status === NotaryCredentialStatus::Pending->value;
    }

    public function isActive(): bool
    {
        return $this->status === NotaryCredentialStatus::Active->value
            && $this->commission_expires_at !== null
            && ! $this->commission_expires_at->copy()->endOfDay()->isPast();
    }

    public function isExpired(): bool
    {
        return $this->commission_expires_at !== null
            && $this->commission_expires_at->copy()->endOfDay()->isPast();
    }

    public function nextEntryNumber(): int
    {
        $currentYear = (int) now()->format('Y');

        $lastEntry = $this->registerEntries()
            ->where('entry_year', $currentYear)
            ->orderByDesc('entry_number')
            ->first();

        return $lastEntry !== null ? $lastEntry->entry_number + 1 : 1;
    }
}
