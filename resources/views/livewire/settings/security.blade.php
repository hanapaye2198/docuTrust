<?php

use App\Services\TwoFactorAuthenticationService;
use App\Services\TrustedDeviceService;
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

    /**
     * @var list<string>
     */
    public array $newRecoveryCodes = [];

    public function revokeTrustedDevice(int $deviceId): void
    {
        app(TrustedDeviceService::class)->revoke(Auth::user(), $deviceId);
        Session::flash('status', __('Trusted device revoked.'));
    }

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

        $this->newRecoveryCodes = $twoFactor->enableForUser(Auth::user(), $secret);

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

        app(TwoFactorAuthenticationService::class)->disableForUser(Auth::user());

        $this->disablePassword = '';
        $this->newRecoveryCodes = [];
        Session::flash('status', __('Two-factor authentication has been turned off.'));
    }

    public function regenerateRecoveryCodes(): void
    {
        $user = Auth::user();
        if (! $user->two_factor_enabled) {
            return;
        }

        $this->newRecoveryCodes = app(TwoFactorAuthenticationService::class)->regenerateRecoveryCodes($user);
        Session::flash('status', __('Recovery codes regenerated successfully.'));
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
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('Confirmed at: :date', ['date' => optional(auth()->user()->two_factor_confirmed_at)->toDayDateTimeString() ?? __('N/A')]) }}
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <flux:button variant="ghost" type="button" wire:click="regenerateRecoveryCodes">
                            {{ __('Regenerate recovery codes') }}
                        </flux:button>
                        <a
                            href="{{ route('two-factor.recovery-codes.download') }}"
                            class="inline-flex items-center rounded-lg border border-zinc-300 px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800"
                        >
                            {{ __('Download recovery codes (.txt)') }}
                        </a>
                    </div>
                    @if ($newRecoveryCodes !== [])
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm dark:border-amber-700/40 dark:bg-amber-900/20">
                            <p class="font-medium text-amber-900 dark:text-amber-200">{{ __('Save these new recovery codes now.') }}</p>
                            <div class="mt-3 grid grid-cols-2 gap-2 font-mono">
                                @foreach ($newRecoveryCodes as $recoveryCode)
                                    <span class="rounded-md bg-white px-2 py-1 text-amber-900 dark:bg-zinc-900 dark:text-amber-200">{{ $recoveryCode }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif
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

                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <p class="text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ __('Trusted devices') }}</p>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Devices marked as trusted can skip MFA challenge for up to 30 days.') }}</p>
                        <div class="mt-3 space-y-2">
                            @forelse (auth()->user()->trustedDevices()->whereNull('revoked_at')->where('expires_at', '>=', now())->get() as $trustedDevice)
                                <div class="flex items-start justify-between gap-3 rounded-lg border border-zinc-200 px-3 py-2 text-xs dark:border-zinc-700">
                                    <div>
                                        <p class="font-medium text-zinc-800 dark:text-zinc-100">{{ $trustedDevice->device_name ?: __('Unknown device') }}</p>
                                        <p class="text-zinc-500 dark:text-zinc-400">{{ __('Last used: :date', ['date' => optional($trustedDevice->last_used_at)->toDayDateTimeString() ?? __('N/A')]) }}</p>
                                        <p class="text-zinc-500 dark:text-zinc-400">{{ __('Expires: :date', ['date' => optional($trustedDevice->expires_at)->toDayDateTimeString() ?? __('N/A')]) }}</p>
                                    </div>
                                    <flux:button variant="danger" size="sm" type="button" wire:click="revokeTrustedDevice({{ $trustedDevice->id }})">
                                        {{ __('Revoke') }}
                                    </flux:button>
                                </div>
                            @empty
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('No active trusted devices.') }}</p>
                            @endforelse
                        </div>
                    </div>
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
