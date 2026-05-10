<?php

namespace App\Enums;

enum DocumentSignerStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Signed = 'signed';
    case Notified = 'notified';

    public function isCompleted(): bool
    {
        return in_array($this, [
            self::Approved,
            self::Signed,
            self::Notified,
        ], true);
    }
}
