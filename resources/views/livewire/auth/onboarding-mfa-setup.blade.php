<?php

use App\Enums\OnboardingStep;
use App\Enums\EkycStatus;
use App\Services\TwoFactorAuthenticationService;
use App\Services\OnboardingAuditLogger;
use App\Support\AuthSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $code = '';
    public string $qrInlineUrl = '';
    public string $qrFallbackUrl = '';

    public function mount(TwoFactorAuthenticationService $twoFactor): void
    {
        $user = Auth::user();
        if ($user === null) {
            $this->redirect(route('login', absolute: false), navigate: true);

            return;
        }

        if (
            ($user->onboarding_step !== OnboardingStep::EkycVerified && $user->onboarding_step !== OnboardingStep::MfaSetup)
            || $user->ekyc_status !== EkycStatus::Verified
        ) {
            $this->redirect(route('onboarding.start', absolute: false), navigate: true);

            return;
        }

        if ($user->onboarding_step === OnboardingStep::EkycVerified) {
            $user->forceFill([
                'onboarding_step' => OnboardingStep::MfaSetup,
            ])->save();
        }

        $secret = Session::get(AuthSession::SETUP_SECRET);
        if (! is_string($secret) || $secret === '') {
            $secret = $twoFactor->generateSecretKey();
            Session::put(AuthSession::SETUP_SECRET, $secret);
        }

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
            'two_factor_onboarding_completed_at' => now(),
            'onboarding_step' => OnboardingStep::Completed,
        ])->save();
        $auditLogger->log($user, 'mfa_enabled');

        Session::put(AuthSession::TWO_FACTOR_PASSED, true);
        Session::forget(AuthSession::SETUP_SECRET);

        $this->redirect(route($user->homeRouteName(), absolute: false), navigate: true);
    }
}; ?>

<div class="mx-auto w-full max-w-xl rounded-2xl border border-gray-200 bg-white p-6 shadow-lg sm:p-8">
    <x-auth.onboarding-progress current="mfa" />
    <h1 class="text-2xl font-semibold text-[#1F2937]">{{ __('Set up authenticator app') }}</h1>
    <p class="mt-2 text-sm text-gray-600">{{ __('Scan the QR code and enter the 6-digit code to complete onboarding.') }}</p>

    <div class="mt-6 flex justify-center">
        <img
            src="{{ $qrInlineUrl }}"
            data-fallback-src="{{ $qrFallbackUrl }}"
            onerror="if (this.dataset.fallbackSrc && this.src !== this.dataset.fallbackSrc) { this.src = this.dataset.fallbackSrc; return; }"
            alt="{{ __('Authenticator QR code') }}"
            width="280"
            height="280"
            class="rounded-xl border border-gray-200 p-2"
        />
    </div>

    <form wire:submit="verify" class="mt-6 space-y-4" x-data="{
        digits: ['', '', '', '', '', ''],
        syncModel() {
            $refs.hiddenCode.value = this.digits.join('');
            $refs.hiddenCode.dispatchEvent(new Event('input', { bubbles: true }));
        },
        focus(index) {
            this.$nextTick(() => this.$el.querySelectorAll('.otp-input')[index]?.focus());
        },
        onInput(index, event) {
            const value = String(event.target.value ?? '').replace(/[^0-9]/g, '');
            this.digits[index] = value ? value.slice(-1) : '';
            event.target.value = this.digits[index];
            this.syncModel();

            if (this.digits[index] !== '' && index < 5) {
                this.focus(index + 1);
            }
        },
        onKeydown(index, event) {
            if (event.key === 'Backspace' && ! this.digits[index] && index > 0) {
                this.focus(index - 1);
            }
        },
        onPaste(event) {
            event.preventDefault();
            const pasted = (event.clipboardData?.getData('text') ?? '').replace(/[^0-9]/g, '').slice(0, 6);

            pasted.split('').forEach((char, i) => {
                this.digits[i] = char;
            });

            for (let i = pasted.length; i < 6; i++) {
                this.digits[i] = '';
            }

            this.syncModel();
            if (pasted.length > 0) {
                this.focus(Math.min(pasted.length - 1, 5));
            }
        },
    }">
        <p class="text-sm font-medium text-[#1F2937]">{{ __('Authentication code') }}</p>
        <div class="grid grid-cols-6 gap-2 sm:gap-3" @paste="onPaste($event)">
            <template x-for="(digit, index) in digits" :key="index">
                <input
                    class="otp-input h-12 rounded-lg border-2 border-gray-300 bg-white text-center text-lg font-semibold text-[#1F2937] outline-none transition focus:border-[#2EC4B6] focus:ring-2 focus:ring-[#2EC4B6]/30 sm:h-14 sm:text-xl"
                    type="text"
                    maxlength="1"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    x-model="digits[index]"
                    x-on:input="onInput(index, $event)"
                    x-on:keydown="onKeydown(index, $event)"
                    x-on:focus="$event.target.select()"
                    required
                />
            </template>
        </div>
        <input x-ref="hiddenCode" type="hidden" wire:model="code" />
        <flux:error name="code" />

        <flux:button type="submit" variant="primary" class="w-full">
            {{ __('Complete onboarding') }}
        </flux:button>
    </form>
</div>
