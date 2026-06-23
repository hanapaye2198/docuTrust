<?php

namespace App\Models;

use Database\Factories\NotaryAppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaryAppointment extends Model
{
    /** @use HasFactory<NotaryAppointmentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'notary_availability_slot_id',
        'notary_request_id',
        'client_user_id',
        'notary_user_id',
        'status',
        'notes',
        'meeting_link',
        'confirmed_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<NotaryAvailabilitySlot, $this>
     */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(NotaryAvailabilitySlot::class, 'notary_availability_slot_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function notary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'notary_user_id');
    }

    /**
     * @return BelongsTo<NotaryRequest, $this>
     */
    public function notaryRequest(): BelongsTo
    {
        return $this->belongsTo(NotaryRequest::class);
    }
}
