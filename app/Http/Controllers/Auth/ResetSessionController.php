<?php

namespace App\Http\Controllers\Auth;

use App\Support\AuthSession;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class ResetSessionController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->forget([
            AuthSession::TWO_FACTOR_PASSED,
            AuthSession::REGISTER_PENDING_DATA,
            AuthSession::PENDING_TWO_FACTOR_USER_ID,
            AuthSession::PENDING_TWO_FACTOR_REMEMBER,
            AuthSession::PENDING_TWO_FACTOR_STARTED_AT,
            AuthSession::SETUP_SECRET,
            AuthSession::REGISTER_TWO_FACTOR_SECRET,
            AuthSession::REGISTER_TWO_FACTOR_USER_ID,
        ]);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('status', __('Session reset complete. Please sign in again.'));
    }
}
