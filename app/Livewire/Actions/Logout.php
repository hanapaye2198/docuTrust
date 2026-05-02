<?php

namespace App\Livewire\Actions;

use App\Support\AuthSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Logout
{
    /**
     * Log the current user out of the application.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $request->session()->forget([
            AuthSession::TWO_FACTOR_PASSED,
            AuthSession::PENDING_TWO_FACTOR_USER_ID,
            AuthSession::PENDING_TWO_FACTOR_REMEMBER,
            AuthSession::PENDING_TWO_FACTOR_STARTED_AT,
            AuthSession::SETUP_SECRET,
        ]);

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
