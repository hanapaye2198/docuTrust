<?php

namespace App\Http\Middleware;

use App\Enums\EkycStatus;
use App\Enums\OnboardingStep;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingStep
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || $user->hasCompletedOnboarding()) {
            return $next($request);
        }

        if ($request->routeIs(
            'onboarding.start',
            'onboarding.email.notice',
            'verification.verify',
            'logout',
            'session.reset',
        )) {
            return $next($request);
        }

        $targetRoute = $this->routeForStep($user->onboarding_step);

        if (
            in_array($user->onboarding_step, [OnboardingStep::EkycVerified, OnboardingStep::MfaSetup], true)
            && $user->ekyc_status !== EkycStatus::Verified
        ) {
            $targetRoute = 'onboarding.ekyc';
        }

        if ($request->routeIs($targetRoute)) {
            return $next($request);
        }

        return redirect()->route($targetRoute);
    }

    private function routeForStep(OnboardingStep $step): string
    {
        return match ($step) {
            OnboardingStep::Registered => 'onboarding.email.notice',
            OnboardingStep::EmailVerified => 'onboarding.phone.verify',
            OnboardingStep::PhoneVerified,
            OnboardingStep::EkycPending => 'onboarding.ekyc',
            OnboardingStep::EkycVerified,
            OnboardingStep::MfaSetup => 'onboarding.mfa-setup',
            OnboardingStep::Completed => 'documents.index',
        };
    }
}
