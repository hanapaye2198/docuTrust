<?php

namespace App\Models;

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
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'commission_issued_at' => 'date',
            'commission_expires_at' => 'date',
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
     * @return HasMany<NotarialRegisterEntry, $this>
     */
    public function registerEntries(): HasMany
    {
        return $this->hasMany(NotarialRegisterEntry::class)->orderByDesc('entry_number');
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
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
