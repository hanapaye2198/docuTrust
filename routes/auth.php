<?php

use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\ResetSessionController;
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

Route::redirect('onboarding/email', '/documents')->name('onboarding.email.notice');

Route::middleware('auth')->group(function () {
    Route::redirect('onboarding', '/documents')->name('onboarding');
    Route::redirect('onboarding/start', '/documents')->name('onboarding.start');
    Route::redirect('onboarding/phone-verification', '/documents')->name('onboarding.phone.verify');
    Route::redirect('onboarding/ekyc', '/documents')->name('onboarding.ekyc');
    Route::redirect('onboarding/mfa-setup', '/documents')->name('onboarding.mfa-setup');
    Route::redirect('verify-email', '/documents')->name('verification.notice');

    Volt::route('confirm-password', 'auth.confirm-password')
        ->name('password.confirm');
});

Route::redirect('verify-email/{id}/{hash}', '/documents')->name('verification.verify');

Route::redirect('register/two-factor', '/documents');

Route::post('logout', App\Livewire\Actions\Logout::class)
    ->name('logout');

Route::get('reset-session', ResetSessionController::class)
    ->name('session.reset');
