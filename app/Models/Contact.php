<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    /** @use HasFactory<\Database\Factories\ContactFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'user_id',
        'name',
        'email',
        'phone',
        'company',
    ];

    protected static function booted(): void
    {
        static::saving(function (Contact $contact): void {
            if ($contact->email !== null && $contact->email !== '') {
                $contact->email = strtolower($contact->email);
            }

            if ($contact->organization_id === null && $contact->user_id !== null) {
                $contact->organization_id = User::query()->whereKey($contact->user_id)->value('organization_id');
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
