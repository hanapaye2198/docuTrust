<?php

namespace App\Models;

use Database\Factories\NotaryAvailabilitySlotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class NotaryAvailabilitySlot extends Model
{
    /** @use HasFactory<NotaryAvailabilitySlotFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'notary_user_id',
        'date',
        'start_time',
        'end_time',
        'is_booked',
        'is_blocked',
        'duration_minutes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_booked' => 'boolean',
            'is_blocked' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function notary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'notary_user_id');
    }

    /**
     * @return HasOne<NotaryAppointment, $this>
     */
    public function appointment(): HasOne
    {
        return $this->hasOne(NotaryAppointment::class);
    }

    public function isAvailable(): bool
    {
        return ! $this->is_booked
            && ! $this->is_blocked
            && ! $this->date->isPast();
    }
}
