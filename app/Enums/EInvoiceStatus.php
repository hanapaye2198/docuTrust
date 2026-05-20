<?php

namespace App\Enums;

enum EInvoiceStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Submitted = 'submitted';
    case Processing = 'processing';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case NeedsCorrection = 'needs_correction';

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Accepted,
            self::Rejected,
        ], true);
    }
}
