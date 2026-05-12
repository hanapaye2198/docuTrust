<?php

namespace App\Enums;

enum NotaryIdentityVerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
}
