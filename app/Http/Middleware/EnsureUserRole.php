<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        $allowed = collect(explode(',', $roles))
            ->map(fn (string $r): string => trim($r))
            ->map(fn (string $r): string => UserRole::from($r)->value)
            ->all();

        if (in_array($user->role->value, $allowed, true)) {
            return $next($request);
        }

        return redirect()->route($user->homeRouteName());
    }
}
