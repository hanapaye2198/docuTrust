<?php

namespace App\Enums;

enum OnboardingStep: string
{
    case Registered = 'registered';
    case EmailVerified = 'email_verified';
    case PhoneVerified = 'phone_verified';
    case EkycPending = 'ekyc_pending';
    case EkycVerified = 'ekyc_verified';
    case MfaSetup = 'mfa_setup';
    case Completed = 'completed';
}
