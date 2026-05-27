<?php

namespace App\Models;

use Database\Factories\AttorneyNotarialRegistryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttorneyNotarialRegistry extends Model
{
    /** @use HasFactory<AttorneyNotarialRegistryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'notary_request_id',
        'entry_no',
        'title',
        'description',
        'parties',
        'witnesses',
        'competent_evidence',
        'notarization_timestamps',
        'notarial_act_type',
        'fees',
        'official_receipt_no',
        'notary_signature_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parties' => 'array',
            'witnesses' => 'array',
            'competent_evidence' => 'array',
            'notarization_timestamps' => 'array',
            'fees' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<NotaryRequest, $this>
     */
    public function notaryRequest(): BelongsTo
    {
        return $this->belongsTo(NotaryRequest::class);
    }
}
