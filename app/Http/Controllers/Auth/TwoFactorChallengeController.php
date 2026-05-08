<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\TwoFactorVerifyRequest;
use App\Services\TrustedDeviceService;
use App\Services\TwoFactorAuthenticationService;
use App\Support\AuthSession;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorChallengeController extends Controller
{
    public function show(Request $request): View
    {
        Log::debug('TwoFactorChallengeController@show', [
            'auth_check' => $request->user() !== null,
            'two_factor_passed' => (bool) $request->session()->get(AuthSession::TWO_FACTOR_PASSED, false),
        ]);

        return view('auth.two-factor');
    }

    public function verify(
        TwoFactorVerifyRequest $request,
        TrustedDeviceService $trustedDevices,
        TwoFactorAuthenticationService $twoFactor,
    ): RedirectResponse {
        $user = $request->user();
        Log::debug('TwoFactorChallengeController@verify', [
            'auth_check' => $request->user() !== null,
            'two_factor_passed' => (bool) $request->session()->get(AuthSession::TWO_FACTOR_PASSED, false),
        ]);

        if ($user === null) {
            $request->session()->forget([
                AuthSession::PENDING_TWO_FACTOR_USER_ID,
                AuthSession::PENDING_TWO_FACTOR_REMEMBER,
                AuthSession::PENDING_TWO_FACTOR_STARTED_AT,
                AuthSession::TWO_FACTOR_PASSED,
            ]);

            return redirect()->route('login');
        }

        $userId = (int) $user->id;
        $this->ensureIsNotRateLimited($userId);

        if (! $twoFactor->hasAtLeastOneRecoveryCode($user)) {
            $twoFactor->regenerateRecoveryCodes($user);
            $user->refresh();
        }

        $code = (string) ($request->validated('code') ?? '');
        $recoveryCode = (string) ($request->validated('recovery_code') ?? '');
        $isValidCode = $code !== '' ? $twoFactor->verify($user, $code) : false;
        $isValidRecoveryCode = ! $isValidCode && $recoveryCode !== ''
            ? $twoFactor->consumeRecoveryCode($user, $recoveryCode)
            : false;

        if (! $isValidCode && ! $isValidRecoveryCode) {
            RateLimiter::hit($this->throttleKey($userId));
            $request->session()->put(AuthSession::TWO_FACTOR_PASSED, false);

            throw ValidationException::withMessages([
                'code' => __('Invalid authentication code.'),
            ]);
        }

        RateLimiter::clear($this->throttleKey($userId));
        $request->session()->put(AuthSession::TWO_FACTOR_PASSED, true);
        if ((bool) $request->boolean('remember_device')) {
            $trustedDevices->trustCurrentDevice($user, $request, 30);
        } else {
            $request->session()->forget(AuthSession::TRUSTED_DEVICE_UNTIL);
        }
        $request->session()->forget([
            AuthSession::PENDING_TWO_FACTOR_USER_ID,
            AuthSession::PENDING_TWO_FACTOR_REMEMBER,
            AuthSession::PENDING_TWO_FACTOR_STARTED_AT,
        ]);

        return redirect()->route($user->homeRouteName());
    }

    protected function ensureIsNotRateLimited(int $userId): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($userId), 10)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey($userId));

        throw ValidationException::withMessages([
            'code' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected function throttleKey(int $userId): string
    {
        return '2fa|'.$userId.'|'.Str::lower((string) request()->ip());
    }
}
