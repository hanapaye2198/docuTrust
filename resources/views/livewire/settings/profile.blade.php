<?php

use App\Livewire\Actions\Logout;
use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use App\Services\TrustedDeviceService;
use App\Support\AuthSession;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component {
    private const TABS = ['profile', 'password', 'security', 'appearance', 'danger'];

    #[Url(as: 'tab', history: true, keep: true)]
    public string $tab = 'profile';

    public string $name = '';

    public string $email = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $confirmCode = '';

    public string $disablePassword = '';

    public ?string $setupQrUrl = null;

    /**
     * @var list<string>
     */
    public array $newRecoveryCodes = [];

    public function mount(): void
    {
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'profile';
        }

        $user = Auth::user();
        $this->name = $user->name;
        $this->email = $user->email;
    }

    public function updatedTab(string $value): void
    {
        if (! in_array($value, self::TABS, true)) {
            $this->tab = 'profile';
        }
    }

    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id),
            ],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: $user->intendedHomeUrl());

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ]);
        } catch (ValidationException $exception) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $exception;
        }

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }

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

@php
    $authUser = auth()->user();
    $trustedDevices = $authUser->trustedDevices()->whereNull('revoked_at')->where('expires_at', '>=', now())->get();
@endphp

<section class="w-full">
    <x-settings.layout
        :heading="__('Settings')"
        :subheading="__('Manage sign-in, security, appearance, and account preferences.')"
    >
        <p class="mb-6 text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('Verification, signatures, and eNOTARY readiness are in') }}
            <a href="{{ route('settings.trust-profile') }}" wire:navigate class="font-medium text-teal-700 hover:underline dark:text-teal-400">
                {{ __('Trust profile') }}
            </a>.
        </p>

        <div class="flex flex-col lg:flex-row lg:items-start lg:gap-8 xl:gap-10">
            <x-settings.tab-nav :active="$tab" />

            <div class="min-w-0 flex-1 space-y-6">
                @if (session('status') && is_string(session('status')) && session('status') !== 'verification-link-sent')
                    <div class="rounded-xl border border-emerald-200/90 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-100">
                        {{ session('status') }}
                    </div>
                @endif

        {{-- Profile --}}
        <div @class(['space-y-6', 'hidden' => $tab !== 'profile']) role="tabpanel">
            <div class="ui-panel p-6">
                <flux:heading size="lg" class="!mb-1">{{ __('Profile') }}</flux:heading>
                <p class="mb-6 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Name and email used to sign in.') }}</p>

                <form wire:submit="updateProfileInformation" class="space-y-6">
                    <flux:input wire:model="name" label="{{ __('Name') }}" type="text" name="name" required autocomplete="name" />

                    <div>
                        <flux:input wire:model="email" label="{{ __('Email') }}" type="email" name="email" required autocomplete="email" />

                        @if ($authUser instanceof MustVerifyEmail && ! $authUser->hasVerifiedEmail())
                            <div class="mt-3 rounded-xl border border-amber-200/80 bg-amber-50/80 px-3 py-2.5 text-sm dark:border-amber-500/30 dark:bg-amber-500/10">
                                <p class="font-medium text-amber-900 dark:text-amber-200">{{ __('Your email address is unverified.') }}</p>
                                <button
                                    type="button"
                                    wire:click.prevent="resendVerificationNotification"
                                    class="mt-1 font-medium text-teal-700 underline hover:text-teal-800 dark:text-teal-400"
                                >
                                    {{ __('Resend verification email') }}
                                </button>
                                @if (session('status') === 'verification-link-sent')
                                    <p class="mt-2 text-xs font-medium text-emerald-700 dark:text-emerald-400">
                                        {{ __('A new verification link has been sent.') }}
                                    </p>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-4">
                        <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
                        <x-action-message on="profile-updated">{{ __('Saved.') }}</x-action-message>
                    </div>
                </form>
            </div>
        </div>

        {{-- Password --}}
        <div @class(['space-y-6', 'hidden' => $tab !== 'password']) role="tabpanel">
            <div class="ui-panel p-6">
                <flux:heading size="lg" class="!mb-1">{{ __('Password') }}</flux:heading>
                <p class="mb-6 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Used to sign in and to confirm sensitive changes.') }}</p>

                <form wire:submit="updatePassword" class="space-y-6">
                    <flux:input
                        wire:model="current_password"
                        label="{{ __('Current password') }}"
                        type="password"
                        autocomplete="current-password"
                        required
                    />
                    <flux:input
                        wire:model="password"
                        label="{{ __('New password') }}"
                        type="password"
                        autocomplete="new-password"
                        required
                    />
                    <flux:input
                        wire:model="password_confirmation"
                        label="{{ __('Confirm new password') }}"
                        type="password"
                        autocomplete="new-password"
                        required
                    />

                    <div class="flex flex-wrap items-center gap-4">
                        <flux:button variant="primary" type="submit">{{ __('Update password') }}</flux:button>
                        <x-action-message on="password-updated">{{ __('Saved.') }}</x-action-message>
                    </div>
                </form>
            </div>
        </div>

        {{-- Security --}}
        <div @class(['space-y-6', 'hidden' => $tab !== 'security']) role="tabpanel">
            <div class="ui-panel space-y-6 p-6">
                <div>
                    <flux:heading size="lg" class="!mb-1">{{ __('Two-factor authentication') }}</flux:heading>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Add a second step with an authenticator app (TOTP).') }}</p>
                </div>

                @if ($authUser->two_factor_enabled)
                    <div class="space-y-4">
                        <div class="inline-flex items-center gap-2 rounded-xl border border-emerald-200/80 bg-emerald-50/80 px-3 py-2 text-sm font-medium text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
                            <flux:icon.check-badge variant="mini" class="size-4" />
                            {{ __('Two-factor authentication is enabled') }}
                        </div>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Confirmed: :date', ['date' => $authUser->two_factor_confirmed_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? __('N/A')]) }}
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <flux:button variant="ghost" type="button" wire:click="regenerateRecoveryCodes">
                                {{ __('Regenerate recovery codes') }}
                            </flux:button>
                            <a
                                href="{{ route('two-factor.recovery-codes.download') }}"
                                class="inline-flex items-center rounded-lg border border-zinc-300 px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800"
                            >
                                {{ __('Download recovery codes') }}
                            </a>
                        </div>
                        @if ($newRecoveryCodes !== [])
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm dark:border-amber-700/40 dark:bg-amber-900/20">
                                <p class="font-medium text-amber-900 dark:text-amber-200">{{ __('Save these recovery codes now.') }}</p>
                                <div class="mt-3 grid grid-cols-2 gap-2 font-mono text-xs sm:grid-cols-3">
                                    @foreach ($newRecoveryCodes as $recoveryCode)
                                        <span class="rounded-md bg-white px-2 py-1 text-amber-900 dark:bg-zinc-900 dark:text-amber-200">{{ $recoveryCode }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        <form wire:submit="disableTwoFactor" class="max-w-md space-y-4 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                            <flux:input
                                wire:model="disablePassword"
                                label="{{ __('Current password to disable 2FA') }}"
                                type="password"
                                autocomplete="current-password"
                                required
                            />
                            <flux:error name="disablePassword" />
                            <flux:button variant="danger" type="submit">{{ __('Turn off two-factor') }}</flux:button>
                        </form>
                    </div>
                @else
                    @if ($setupQrUrl)
                        <div class="space-y-4">
                            <ol class="list-inside list-decimal space-y-1 text-sm text-zinc-600 dark:text-zinc-400">
                                <li>{{ __('Scan the QR code with your authenticator app.') }}</li>
                                <li>{{ __('Enter the 6-digit code to confirm.') }}</li>
                            </ol>
                            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                                <img src="{{ $setupQrUrl }}" width="200" height="200" alt="" class="mx-auto" />
                            </div>
                            <form wire:submit="confirmEnrollment" class="max-w-xs space-y-4">
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
                @endif
            </div>

            <div class="ui-panel p-6">
                <flux:heading size="lg" class="!mb-1">{{ __('Trusted devices') }}</flux:heading>
                <p class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Trusted devices can skip MFA for up to 30 days.') }}</p>
                <div class="space-y-2">
                    @forelse ($trustedDevices as $trustedDevice)
                        <div class="flex items-start justify-between gap-3 rounded-xl border border-zinc-200/80 px-4 py-3 dark:border-zinc-700">
                            <div class="min-w-0 text-sm">
                                <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $trustedDevice->device_name ?: __('Unknown device') }}</p>
                                <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ __('Last used :date · expires :expires', [
                                        'date' => $trustedDevice->last_used_at?->diffForHumans() ?? __('never'),
                                        'expires' => $trustedDevice->expires_at?->format('M j, Y') ?? __('N/A'),
                                    ]) }}
                                </p>
                            </div>
                            <flux:button variant="danger" size="sm" type="button" wire:click="revokeTrustedDevice({{ $trustedDevice->id }})">
                                {{ __('Revoke') }}
                            </flux:button>
                        </div>
                    @empty
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No active trusted devices.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Appearance --}}
        <div @class(['space-y-6', 'hidden' => $tab !== 'appearance']) role="tabpanel">
            <div class="ui-panel p-6">
                <flux:heading size="lg" class="!mb-1">{{ __('Appearance') }}</flux:heading>
                <p class="mb-6 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Choose how DocuTrust looks on this device.') }}</p>

                <div
                    x-data
                    class="inline-flex w-full max-w-md rounded-xl border border-zinc-200/90 bg-zinc-100/90 p-1 dark:border-zinc-700 dark:bg-zinc-800/80"
                    role="group"
                    aria-label="{{ __('Theme') }}"
                >
                    @foreach ([
                        'light' => ['sun', __('Light')],
                        'dark' => ['moon', __('Dark')],
                        'system' => ['computer-desktop', __('System')],
                    ] as $value => $meta)
                        <button
                            type="button"
                            @click="$flux.appearance = '{{ $value }}'"
                            :class="$flux.appearance === '{{ $value }}'
                                ? 'bg-white text-teal-800 shadow-sm ring-1 ring-zinc-200/80 dark:bg-zinc-700 dark:text-teal-300 dark:ring-zinc-600'
                                : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100'"
                            class="flex flex-1 items-center justify-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium transition"
                        >
                            <flux:icon :name="$meta[0]" variant="mini" class="size-4" />
                            {{ $meta[1] }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Danger zone --}}
        <div @class(['space-y-6', 'hidden' => $tab !== 'danger']) role="tabpanel">
            <div class="rounded-2xl border border-rose-200/90 bg-rose-50/50 p-6 dark:border-rose-900/50 dark:bg-rose-950/20">
                <flux:heading size="lg" class="!mb-1 text-rose-900 dark:text-rose-200">{{ __('Delete account') }}</flux:heading>
                <p class="mb-6 text-sm text-rose-800/90 dark:text-rose-300/90">
                    {{ __('Permanently delete your account and all associated data. This cannot be undone.') }}
                </p>
                <livewire:settings.delete-user-form />
            </div>
        </div>
            </div>
        </div>
    </x-settings.layout>
</section>
