<?php

namespace App\Enums;

enum NotaryGeoVerificationStatus: string
{
    case Pending = 'pending';
    case Passed = 'passed';
    case Failed = 'failed';
}
