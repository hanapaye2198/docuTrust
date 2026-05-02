<?php

use App\Services\TwoFactorAuthenticationService;
use App\Support\AuthSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component {
    public string $confirmCode = '';

    public string $disablePassword = '';

    public ?string $setupQrUrl = null;

    public function startEnrollment(TwoFactorAuthenticationService $twoFactor): void
    {
        $user = Auth::user();
        if ($user->two_factor_enabled) {
            return;
        }

        $secret = $twoFactor->generateSecretKey();
        Session::put(AuthSession::SETUP_SECRET, $secret);
        $this->setupQrUrl = $twoFactor->qrCodeData($user, $secret)['inline'];
    }

    public function confirmEnrollment(TwoFactorAuthenticationService $twoFactor): void
    {
        $this->validate([
            'confirmCode' => ['required', 'string', 'size:6', 'regex:/^[0-9]+$/'],
        ]);

        $secret = Session::get(AuthSession::SETUP_SECRET);
        if (! is_string($secret) || $secret === '') {
            $this->setupQrUrl = null;

            throw ValidationException::withMessages([
                'confirmCode' => __('Start over and scan the QR code again.'),
            ]);
        }

        if (! $twoFactor->verifyRawSecret($secret, $this->confirmCode)) {
            throw ValidationException::withMessages([
                'confirmCode' => __('Invalid code.'),
            ]);
        }

        Auth::user()->update([
            'two_factor_secret' => $secret,
            'two_factor_enabled' => true,
        ]);

        Session::forget(AuthSession::SETUP_SECRET);
        $this->setupQrUrl = null;
        $this->confirmCode = '';
        Session::flash('status', __('Two-factor authentication is now enabled.'));
    }

    public function cancelEnrollment(): void
    {
        Session::forget(AuthSession::SETUP_SECRET);
        $this->setupQrUrl = null;
        $this->confirmCode = '';
    }

    public function disableTwoFactor(): void
    {
        $this->validate([
            'disablePassword' => ['required', 'string'],
        ]);

        if (! Hash::check($this->disablePassword, Auth::user()->password)) {
            throw ValidationException::withMessages([
                'disablePassword' => __('auth.password'),
            ]);
        }

        Auth::user()->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
        ]);

        $this->disablePassword = '';
        Session::flash('status', __('Two-factor authentication has been turned off.'));
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout
        heading="{{ __('Security') }}"
        subheading="{{ __('Protect your account with two-factor authentication (TOTP / Google Authenticator).') }}"
    >
        @if (session('status'))
            <div
                class="mb-6 rounded-xl border border-emerald-200/90 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-100"
            >
                {{ session('status') }}
            </div>
        @endif

        <div class="mt-6 space-y-8">
            @if (auth()->user()->two_factor_enabled)
                <div class="space-y-4">
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Two-factor authentication is enabled. You will be asked for a code after entering your password.') }}
                    </p>
                    <form wire:submit="disableTwoFactor" class="space-y-4">
                        <flux:input
                            wire:model="disablePassword"
                            label="{{ __('Current password') }}"
                            type="password"
                            autocomplete="current-password"
                            required
                        />
                        <flux:error name="disablePassword" />
                        <flux:button variant="danger" type="submit">{{ __('Turn off two-factor') }}</flux:button>
                    </form>
                </div>
            @else
                <div class="space-y-6">
                    @if ($setupQrUrl)
                        <div class="space-y-4">
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                {{ __('Scan this QR code with Google Authenticator or another TOTP app, then enter the 6-digit code to confirm.') }}
                            </p>
                            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                                <img src="{{ $setupQrUrl }}" width="200" height="200" alt="" class="mx-auto" />
                            </div>
                            <form wire:submit="confirmEnrollment" class="space-y-4">
                                <flux:input
                                    wire:model="confirmCode"
                                    label="{{ __('Verification code') }}"
                                    type="text"
                                    inputmode="numeric"
                                    maxlength="6"
                                    autocomplete="one-time-code"
                                    required
                                />
                                <flux:error name="confirmCode" />
                                <div class="flex flex-wrap gap-2">
                                    <flux:button variant="primary" type="submit">{{ __('Confirm and enable') }}</flux:button>
                                    <flux:button variant="ghost" type="button" wire:click="cancelEnrollment">{{ __('Cancel') }}</flux:button>
                                </div>
                            </form>
                        </div>
                    @else
                        <flux:button variant="primary" type="button" wire:click="startEnrollment">
                            {{ __('Set up two-factor authentication') }}
                        </flux:button>
                    @endif
                </div>
            @endif
        </div>
    </x-settings.layout>
</section>
