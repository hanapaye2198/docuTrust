<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Completed = 'completed';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
    case Archived = 'archived';
}
