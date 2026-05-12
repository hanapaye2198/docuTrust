<?php

namespace App\Models;

use Database\Factories\NotaryJournalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaryJournal extends Model
{
    /** @use HasFactory<NotaryJournalFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'notary_request_id',
        'notary_user_id',
        'entry_type',
        'summary',
        'legal_assertions',
        'recorded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'legal_assertions' => 'array',
            'recorded_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function notary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'notary_user_id');
    }
}
