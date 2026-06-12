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

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Submitted => __('Submitted'),
            self::IdentityReviewRequired => __('Identity review'),
            self::IdentityVerified => __('Identity verified'),
            self::LocationReviewRequired => __('Location review'),
            self::LocationVerified => __('Location verified'),
            self::SessionScheduled => __('Video call scheduled'),
            self::SessionInProgress => __('Video call in progress'),
            self::SessionCompleted => __('Video verification done'),
            self::AttorneySigning => __('Ready for you to sign'),
            self::AttorneyApproved => __('Attorney reviewed'),
            self::Digitalized => __('Digitally notarized'),
            self::Notarized => __('Notarized'),
            self::Rejected => __('Rejected'),
            self::Failed => __('Failed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function fluxColor(): string
    {
        return match ($this) {
            self::Notarized => 'emerald',
            self::Rejected, self::Failed, self::Cancelled => 'red',
            self::Submitted, self::SessionScheduled, self::SessionInProgress => 'sky',
            self::IdentityVerified, self::LocationVerified, self::AttorneyApproved, self::Digitalized => 'teal',
            self::IdentityReviewRequired, self::LocationReviewRequired, self::SessionCompleted, self::AttorneySigning => 'amber',
            default => 'zinc',
        };
    }
}
