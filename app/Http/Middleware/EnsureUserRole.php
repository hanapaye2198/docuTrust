<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * @return list<string>
     */
    private function expandRoleAlias(string $role): array
    {
        return match ($role) {
            'admin' => [UserRole::NotaryAdmin->value],
            'signer' => [UserRole::Client->value],
            default => [UserRole::from($role)->value],
        };
    }

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        $allowed = collect($roles)
            ->flatMap(fn (string $group) => explode(',', $group))
            ->map(fn (string $r): string => trim($r))
            ->flatMap(fn (string $r): array => $this->expandRoleAlias($r))
            ->filter()
            ->unique()
            ->all();

        if (in_array($user->role->value, $allowed, true)) {
            return $next($request);
        }

        return redirect()->route($user->homeRouteName());
    }
}
