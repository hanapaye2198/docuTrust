<?php

namespace App\Models;

use Database\Factories\NotarySessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotarySession extends Model
{
    /** @use HasFactory<NotarySessionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'notary_request_id',
        'notary_signer_id',
        'notary_user_id',
        'provider_name',
        'status',
        'room_name',
        'meeting_url',
        'access_token',
        'joined_at',
        'left_at',
        'host_reference',
        'scheduled_for',
        'invitation_sent_at',
        'started_at',
        'ended_at',
        'evidence',
        'verification_checklist',
        'recording_path',
        'signer_confirmed',
        'signer_confirmed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'invitation_sent_at' => 'datetime',
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'signer_confirmed_at' => 'datetime',
            'signer_confirmed' => 'boolean',
            'evidence' => 'array',
            'verification_checklist' => 'array',
        ];
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
    public function notarySigner(): BelongsTo
    {
        return $this->belongsTo(NotarySigner::class);
    }

    public function isSignerScoped(): bool
    {
        return $this->notary_signer_id !== null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function notaryUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'notary_user_id');
    }
}
