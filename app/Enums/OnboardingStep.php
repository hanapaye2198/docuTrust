<?php

namespace App\Enums;

enum OnboardingStep: int
{
    case EmailVerification = 1;
    case MobileVerification = 2;
    case Kyc = 3;
    case Mfa = 4;
    case Completed = 5;
}
