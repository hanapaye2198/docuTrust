<?php

namespace App\Http\Middleware;

use App\Enums\OnboardingStep;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingProgress
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ($request->is('livewire*')) {
            return $next($request);
        }

        if ($request->routeIs('logout', 'session.reset', 'verification.verify', 'onboarding.change-email')) {
            return $next($request);
        }

        if ($user->hasCompletedOnboarding()) {
            return $next($request);
        }

        $allowedRoute = $this->allowedRouteName($user);

        if ($request->routeIs($allowedRoute)) {
            return $next($request);
        }

        return redirect()->route($allowedRoute);
    }

    private function allowedRouteName(User $user): string
    {
        if ($user->onboarding_step === OnboardingStep::Completed && ! $user->mfa_enabled) {
            return 'onboarding.mfa';
        }

        return match ($user->onboarding_step) {
            OnboardingStep::EmailVerification => 'onboarding.email.verify',
            OnboardingStep::MobileVerification => 'onboarding.mobile',
            OnboardingStep::Kyc => 'onboarding.kyc',
            OnboardingStep::Mfa => 'onboarding.mfa',
            OnboardingStep::Completed => 'onboarding.mfa',
            default => 'onboarding.email.verify',
        };
    }
}
