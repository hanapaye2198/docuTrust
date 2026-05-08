<?php

use App\Console\Commands\MoveRootCaKeyToExternalStore;
use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnsureOnboardingProgress;
use App\Http\Middleware\EnsurePendingTwoFactorChallenge;
use App\Http\Middleware\EnsureTwoFactorIsVerified;
use App\Http\Middleware\EnsureUserRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        MoveRootCaKeyToExternalStore::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->authenticateSessions();

        $middleware->alias([
            'onboarding.progress' => EnsureOnboardingProgress::class,
            'pending.two.factor' => EnsurePendingTwoFactorChallenge::class,
            'role' => EnsureUserRole::class,
            'two.factor.verified' => EnsureTwoFactorIsVerified::class,
        ]);

        $middleware->appendToGroup('web', [
            AddSecurityHeaders::class,
            EnsureOnboardingProgress::class,
            EnsureTwoFactorIsVerified::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));

        $middleware->redirectUsersTo(function (Request $request) {
            $user = $request->user();

            if ($user !== null && ! $user->hasCompletedOnboarding()) {
                return route($user->onboardingRouteName(), absolute: false);
            }

            if ($user !== null) {
                return $user->intendedHomeUrl();
            }

            return route('home', absolute: false);
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $throwable, Request $request) {
            Log::channel('errors')->error('Unhandled exception', [
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'user_id' => $request->user()?->id,
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('Unexpected server error. Please try again later.'),
                ], 500);
            }

            if ((bool) config('app.debug')) {
                return null;
            }

            return response()->view('errors.generic', [], 500);
        });
    })->create();
