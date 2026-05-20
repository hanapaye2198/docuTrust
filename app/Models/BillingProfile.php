<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingProfile extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'registered_name',
        'tin',
        'branch_code',
        'email',
        'phone',
        'address_line',
        'city',
        'state',
        'postal_code',
        'country_code',
        'eis_environment',
        'eis_accreditation_id',
        'eis_application_id',
        'eis_username',
        'eis_password',
        'eis_certificate_id',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'eis_password' => 'encrypted',
            'is_active' => 'boolean',
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
     * @return HasMany<EInvoice, $this>
     */
    public function eInvoices(): HasMany
    {
        return $this->hasMany(EInvoice::class)->latest('created_at');
    }
}
