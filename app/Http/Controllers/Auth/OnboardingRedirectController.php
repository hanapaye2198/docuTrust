<?php

namespace App\Http\Controllers\Auth;

use App\Enums\EkycStatus;
use App\Enums\OnboardingStep;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OnboardingRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('login');
        }

        if ($user->hasCompletedOnboarding()) {
            return redirect()->route($user->homeRouteName());
        }

        $routeName = match ($user->onboarding_step) {
            OnboardingStep::Registered => 'onboarding.email.notice',
            OnboardingStep::EmailVerified => 'onboarding.phone.verify',
            OnboardingStep::PhoneVerified,
            OnboardingStep::EkycPending => 'onboarding.ekyc',
            OnboardingStep::EkycVerified,
            OnboardingStep::MfaSetup => 'onboarding.mfa-setup',
            OnboardingStep::Completed => $user->homeRouteName(),
        };

        if (
            in_array($user->onboarding_step, [OnboardingStep::EkycVerified, OnboardingStep::MfaSetup], true)
            && $user->ekyc_status !== EkycStatus::Verified
        ) {
            $routeName = 'onboarding.ekyc';
        }

        return redirect()->route($routeName);
    }
}
