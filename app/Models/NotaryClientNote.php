<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaryClientNote extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'notary_user_id',
        'client_user_id',
        'note',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function notary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'notary_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }
}
