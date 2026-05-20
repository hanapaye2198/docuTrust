<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EInvoiceSubmission extends Model
{
    protected $table = 'einvoice_submissions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'einvoice_id',
        'status',
        'submit_id',
        'request_payload',
        'response_payload',
        'submitted_at',
        'resolved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'submitted_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<EInvoice, $this>
     */
    public function eInvoice(): BelongsTo
    {
        return $this->belongsTo(EInvoice::class, 'einvoice_id');
    }
}
