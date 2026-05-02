<?php

use App\Http\Controllers\Auth\ResetSessionController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth', 'pending.two.factor'])->group(function () {
    Route::get('two-factor-challenge', [TwoFactorChallengeController::class, 'show'])->name('two-factor.challenge');
    Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'verify'])
        ->middleware('throttle:otp-verification')
        ->name('two-factor.verify');
});

Route::middleware('guest')->group(function () {
    Volt::route('login', 'auth.login')
        ->name('login');

    Volt::route('register', 'auth.register')
        ->name('register');

    Volt::route('forgot-password', 'auth.forgot-password')
        ->name('password.request');

    Volt::route('reset-password/{token}', 'auth.reset-password')
        ->name('password.reset');

});

Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::middleware('auth')->group(function () {
    Volt::route('onboarding/email-verify', 'auth.onboarding-email-verify')->name('onboarding.email.verify');
    Volt::route('onboarding/mobile', 'auth.onboarding-mobile')->name('onboarding.mobile');
    Volt::route('onboarding/kyc', 'auth.onboarding-kyc')->name('onboarding.kyc');
    Volt::route('onboarding/mfa', 'auth.onboarding-mfa')->name('onboarding.mfa');

    Route::get('onboarding/change-email', function () {
        Auth::guard('web')->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('register');
    })->name('onboarding.change-email');

    Route::redirect('onboarding', '/onboarding/email-verify')->name('onboarding');
    Route::redirect('onboarding/start', '/onboarding/email-verify')->name('onboarding.start');
    Route::redirect('onboarding/email', '/onboarding/email-verify')->name('onboarding.email.notice');
    Route::redirect('onboarding/phone-verification', '/onboarding/mobile')->name('onboarding.phone.verify');
    Route::redirect('onboarding/ekyc', '/onboarding/kyc')->name('onboarding.ekyc');
    Route::redirect('onboarding/mfa-setup', '/onboarding/mfa')->name('onboarding.mfa-setup');

    Route::redirect('verify-email', '/onboarding/email-verify')->name('verification.notice');

    Volt::route('confirm-password', 'auth.confirm-password')
        ->name('password.confirm');
});

Route::redirect('register/two-factor', '/documents');

Route::post('logout', Logout::class)
    ->name('logout');

Route::get('reset-session', ResetSessionController::class)
    ->name('session.reset');
