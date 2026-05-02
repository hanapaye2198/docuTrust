<?php

namespace App\Enums;

enum EkycStatus: string
{
    case NotSubmitted = 'not_submitted';
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
}
