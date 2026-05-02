<?php

namespace App\Http\Controllers\Auth;

use App\Enums\OnboardingStep;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OnboardingAuditLogger;
use App\Support\AuthSession;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

class VerifyEmailController extends Controller
{
    /**
     * Mark the user's email address as verified (signed URL).
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

        if ($user->onboarding_step === OnboardingStep::EmailVerification) {
            $user->forceFill([
                'onboarding_step' => OnboardingStep::MobileVerification,
                'email_otp' => null,
                'email_otp_expires_at' => null,
            ])->save();
        }

        $auditLogger->log($user->fresh(), 'email_verified', $request);
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put(AuthSession::TWO_FACTOR_PASSED, true);

        $fresh = $user->fresh();
        if ($fresh === null) {
            return redirect()->route('login');
        }

        if ($fresh->hasCompletedOnboarding()) {
            $url = route($fresh->homeRouteName(), absolute: false).'?verified=1';

            return redirect()->to($url);
        }

        return redirect()->route('onboarding.mobile');
    }
}
