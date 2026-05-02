<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorOnboardingComplete
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->needsTwoFactorOnboarding()) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if (
            (is_string($routeName) && Str::of($routeName)->endsWith('livewire.update'))
            || $request->is('livewire/update')
        ) {
            return $next($request);
        }

        if ($request->routeIs(
            'register.two-factor',
            'logout',
            'two-factor.challenge',
            'two-factor.verify',
            'verification.notice',
            'verification.verify',
            'password.confirm',
        )) {
            return $next($request);
        }

        return redirect()->route('register.two-factor');
    }
}
