<?php

namespace App\Enums;

enum NotaryRequestStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case IdentityVerified = 'identity_verified';
    case LocationVerified = 'location_verified';
    case SessionScheduled = 'session_scheduled';
    case SessionInProgress = 'session_in_progress';
    case AttorneyApproved = 'attorney_approved';
    case Notarized = 'notarized';
    case Rejected = 'rejected';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
