<?php

namespace App\Http\Middleware;

use App\Models\NotaryRequest;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureEnotaryPortalAccess
{
    /**
     * @param  'manage'|'view'  $ability
     */
    public function handle(Request $request, Closure $next, string $ability = 'view'): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        if ($user->canManageNotaryRequestPortal()) {
            return $next($request);
        }

        if ($ability === 'manage') {
            abort(403);
        }

        $notaryRequest = $request->route('notaryRequest');

        if (! $notaryRequest instanceof NotaryRequest) {
            $notaryRequest = NotaryRequest::query()->whereKey($notaryRequest)->first();
        }

        if ($notaryRequest instanceof NotaryRequest) {
            if (Gate::forUser($user)->allows('view', $notaryRequest)) {
                return $next($request);
            }

            if ($user->isNotarySignerOn($notaryRequest)) {
                return $next($request);
            }
        }

        abort(403);
    }
}
