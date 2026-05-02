<?php

namespace App\Http\Middleware;

use App\Support\AuthSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        Log::debug('EnsureTwoFactorIsVerified', [
            'auth_check' => $request->user() !== null,
            'two_factor_passed' => (bool) $request->session()->get(AuthSession::TWO_FACTOR_PASSED, false),
            'route' => $request->route()?->getName(),
        ]);

        if ($user === null) {
            return $next($request);
        }

        if ($request->routeIs('two-factor.challenge', 'two-factor.verify', 'login')) {
            return $next($request);
        }

        if (! $user->hasCompletedOnboarding()) {
            $request->session()->put(AuthSession::TWO_FACTOR_PASSED, true);

            return $next($request);
        }

        if (! $user->two_factor_enabled) {
            $request->session()->put(AuthSession::TWO_FACTOR_PASSED, true);

            return $next($request);
        }

        if ((bool) $request->session()->get(AuthSession::TWO_FACTOR_PASSED, false)) {
            return $next($request);
        }

        return redirect()->route('two-factor.challenge');
    }
}
