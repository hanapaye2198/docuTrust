<?php

namespace App\Http\Controllers\Auth;

use App\Enums\OnboardingStep;
use App\Models\User;
use App\Services\OnboardingAuditLogger;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(Request $request, OnboardingAuditLogger $auditLogger): RedirectResponse
    {
        if (! URL::hasValidSignature($request)) {
            abort(403);
        }

        $userId = (int) $request->route('id');
        $hash = (string) $request->route('hash');
        $user = User::query()->findOrFail($userId);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403);
        }

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        if ($user->onboarding_step === OnboardingStep::Registered) {
            $user->forceFill([
                'onboarding_step' => OnboardingStep::EmailVerified,
            ])->save();
        }

        $auditLogger->log($user, 'email_verified', $request);
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put(\App\Support\AuthSession::TWO_FACTOR_PASSED, true);

        $redirectRoute = $user->hasCompletedOnboarding()
            ? $user->homeRouteName()
            : 'onboarding.phone.verify';

        return redirect()->route($redirectRoute);
    }
}
