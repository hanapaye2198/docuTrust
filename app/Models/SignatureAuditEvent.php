<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignatureAuditEvent extends Model
{
    public const ACTION_PLACED = 'placed';

    public const ACTION_SIGNED = 'signed';

    public const ACTION_COMPLETED = 'completed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'document_id',
        'signer_id',
        'action',
        'ip_address',
    ];

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<DocumentSigner, $this>
     */
    public function signer(): BelongsTo
    {
        return $this->belongsTo(DocumentSigner::class, 'signer_id');
    }
}
