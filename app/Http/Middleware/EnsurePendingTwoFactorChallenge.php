<?php

namespace App\Http\Middleware;

use App\Support\AuthSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsurePendingTwoFactorChallenge
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        Log::debug('EnsurePendingTwoFactorChallenge', [
            'auth_check' => $request->user() !== null,
            'two_factor_passed' => (bool) $request->session()->get(AuthSession::TWO_FACTOR_PASSED, false),
            'route' => $request->route()?->getName(),
        ]);

        if ($user === null) {
            return redirect()->route('login');
        }

        if (! $user->two_factor_enabled || $user->two_factor_confirmed_at === null) {
            $request->session()->put(AuthSession::TWO_FACTOR_PASSED, true);

            return redirect()->route($user->homeRouteName());
        }

        if (! $user->hasCompletedOnboarding()) {
            $request->session()->put(AuthSession::TWO_FACTOR_PASSED, true);

            return redirect()->route($user->onboardingRouteName());
        }

        if ((bool) $request->session()->get(AuthSession::TWO_FACTOR_PASSED, false)) {
            return redirect()->route($user->homeRouteName());
        }

        $startedAt = $request->session()->get(AuthSession::PENDING_TWO_FACTOR_STARTED_AT);
        $isStartedAtValid = is_int($startedAt) || (is_numeric($startedAt) && (string) ((int) $startedAt) === (string) $startedAt);
        $isExpired = ! $isStartedAtValid || now()->diffInSeconds(now()->setTimestamp((int) $startedAt), absolute: true) > 600;

        if ($isExpired) {
            $request->session()->forget([
                AuthSession::PENDING_TWO_FACTOR_USER_ID,
                AuthSession::PENDING_TWO_FACTOR_REMEMBER,
                AuthSession::PENDING_TWO_FACTOR_STARTED_AT,
                AuthSession::TWO_FACTOR_PASSED,
            ]);
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('status', __('Your verification session has expired. Please sign in again.'));
        }

        return $next($request);
    }
}
