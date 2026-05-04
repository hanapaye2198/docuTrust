<?php

use App\Enums\OnboardingStep;
use App\Services\OnboardingAuditLogger;
use App\Services\TwoFactorAuthenticationService;
use App\Support\AuthSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth.register')] class extends Component {
    public string $code = '';

    public string $qrInlineUrl = '';

    public string $qrFallbackUrl = '';

    public string $manualSecret = '';

    public function mount(TwoFactorAuthenticationService $twoFactor): void
    {
        $user = Auth::user();
        if ($user === null) {
            $this->redirect(route('login', absolute: false), navigate: true);

            return;
        }

        if ($user->onboarding_step !== OnboardingStep::Mfa) {
            $this->redirect(route($user->onboardingRouteName(), absolute: false), navigate: true);

            return;
        }

        $secret = Session::get(AuthSession::SETUP_SECRET);
        if (! is_string($secret) || $secret === '') {
            $secret = $twoFactor->generateSecretKey();
            Session::put(AuthSession::SETUP_SECRET, $secret);
        }

        $this->manualSecret = $secret;

        $qrData = $twoFactor->registrationQrCodeData($user->email, $secret);
        $this->qrInlineUrl = $qrData['inline'];
        $this->qrFallbackUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&data='.rawurlencode($qrData['uri']);
    }

    public function verify(TwoFactorAuthenticationService $twoFactor, OnboardingAuditLogger $auditLogger): void
    {
        $user = Auth::user();
        if ($user === null) {
            $this->redirect(route('login', absolute: false), navigate: true);

            return;
        }

        $this->validate([
            'code' => ['required', 'string', 'size:6', 'regex:/^[0-9]+$/'],
        ]);

        $secret = Session::get(AuthSession::SETUP_SECRET);
        if (! is_string($secret) || $secret === '') {
            throw ValidationException::withMessages([
                'code' => __('Refresh the page and scan the QR code again.'),
            ]);
        }

        if (! $twoFactor->verifyRawSecret($secret, $this->code)) {
            throw ValidationException::withMessages([
                'code' => __('Invalid authentication code.'),
            ]);
        }

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_enabled' => true,
            'mfa_enabled' => true,
            'two_factor_onboarding_completed_at' => now(),
            'onboarding_step' => OnboardingStep::Completed,
        ])->save();

        $auditLogger->log($user, 'mfa_enabled');

        Session::put(AuthSession::TWO_FACTOR_PASSED, true);
        Session::forget(AuthSession::SETUP_SECRET);

        $this->redirect(route('documents.index', absolute: false), navigate: true);
    }
}; ?>

<x-auth.onboarding-wizard-shell :active-step="4">
    <h1 class="text-2xl font-semibold text-[#1F2937] dark:text-zinc-100">{{ __('Set up multi-factor authentication') }}</h1>
    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Use your authenticator app to scan the QR code or enter the secret manually. Then enter the 6-digit code to confirm.') }}</p>

    <x-auth-session-status class="mt-4 rounded-lg bg-[#2EC4B6]/10 px-3 py-2 text-center text-sm text-[#1B5E20] dark:text-teal-300" :status="session('status')" />

    <div class="mt-6 flex justify-center rounded-2xl border border-gray-200 bg-gray-50/80 p-4 transition duration-300 dark:border-zinc-700 dark:bg-zinc-800/50">
        <img
            src="{{ $qrInlineUrl }}"
            data-fallback-src="{{ $qrFallbackUrl }}"
            onerror="if (this.dataset.fallbackSrc && this.src !== this.dataset.fallbackSrc) { this.src = this.dataset.fallbackSrc; return; }"
            alt="{{ __('Authenticator QR code') }}"
            width="280"
            height="280"
            class="rounded-xl border border-gray-200 bg-white p-2 shadow-sm transition duration-300 dark:border-zinc-600"
        />
    </div>

    <div class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/80">
        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-zinc-400">{{ __('Manual secret key') }}</p>
        <p class="mt-2 select-all break-all font-mono text-sm leading-relaxed text-[#1F2937] dark:text-zinc-200">{{ $manualSecret }}</p>
        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-500">{{ __('Keep this private. Only enter it in a trusted authenticator app.') }}</p>
    </div>

    <form wire:submit="verify" class="mt-6 flex flex-col gap-6">
        <div>
            <p class="text-sm font-medium text-[#1F2937] dark:text-zinc-200">{{ __('Authentication code') }}</p>
            <x-auth.otp-inputs model="code" :auto-submit="true" />
            <div class="mt-2">
                <flux:error name="code" />
            </div>
        </div>

        <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="verify" class="w-full bg-[#2EC4B6] text-white transition hover:bg-[#1B5E20] hover:text-white">
            <span wire:loading.remove wire:target="verify">{{ __('Verify and continue') }}</span>
            <span wire:loading wire:target="verify">{{ __('Verifying…') }}</span>
        </flux:button>
    </form>
</x-auth.onboarding-wizard-shell>
