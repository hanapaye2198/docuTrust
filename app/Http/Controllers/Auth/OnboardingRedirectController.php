<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OnboardingRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('login');
        }

        if ($user->hasCompletedOnboarding()) {
            return redirect()->route($user->homeRouteName());
        }

        return redirect()->route($user->onboardingRouteName());
    }
}
