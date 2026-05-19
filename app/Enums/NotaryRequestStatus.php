<?php

namespace App\Enums;

enum NotaryRequestStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case IdentityReviewRequired = 'identity_review_required';
    case IdentityVerified = 'identity_verified';
    case LocationReviewRequired = 'location_review_required';
    case LocationVerified = 'location_verified';
    case SessionScheduled = 'session_scheduled';
    case SessionInProgress = 'session_in_progress';
    case SessionCompleted = 'session_completed';
    case AttorneySigning = 'attorney_signing';
    case AttorneyApproved = 'attorney_approved';
    case Digitalized = 'digitalized';
    case Notarized = 'notarized';
    case Rejected = 'rejected';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
