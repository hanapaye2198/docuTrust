<?php

namespace App\Enums;

enum NotaryCredentialStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Rejected = 'rejected';
    case Expired = 'expired';
}
