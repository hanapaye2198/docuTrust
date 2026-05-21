<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Enums\UserWorkspace;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserWorkspace
{
    public function handle(Request $request, Closure $next, string $workspace): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        if ($this->canAccessWorkspace($user, $workspace)) {
            return $next($request);
        }

        return redirect()->route($user->homeRouteName());
    }

    private function canAccessWorkspace(User $user, string $workspace): bool
    {
        if (in_array($user->role, [UserRole::SuperAdmin, UserRole::NotaryAdmin], true)) {
            return true;
        }

        if ($user->role === UserRole::Notary) {
            return $workspace === UserWorkspace::Enotary->value;
        }

        if ($user->role !== UserRole::Client) {
            return false;
        }

        return match ($workspace) {
            UserWorkspace::Signing->value => $user->canAccessSigningWorkspace(),
            UserWorkspace::Enotary->value => $user->canAccessEnotaryWorkspace(),
            default => false,
        };
    }
}
