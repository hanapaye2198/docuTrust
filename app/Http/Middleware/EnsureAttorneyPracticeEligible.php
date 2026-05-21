<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Services\AttorneyApplicationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAttorneyPracticeEligible
{
    public function __construct(
        private readonly AttorneyApplicationService $attorneyApplications,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->role !== UserRole::Notary) {
            return $next($request);
        }

        $eligibility = $this->attorneyApplications->practiceEligibility($user);

        if ($eligibility['allowed']) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, $eligibility['message'] ?? __('Attorney practice is not enabled for this account.'));
        }

        return redirect()
            ->route('settings.attorney-application')
            ->with('attorney-application-status', $eligibility['message']);
    }
}
