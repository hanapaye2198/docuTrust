<?php

use App\Enums\OnboardingStep;
use App\Services\OnboardingAuditLogger;
use App\Services\OtpService;
use App\Services\SmsService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth.register')] class extends Component {
    public string $mobile_number = '';
    public string $otp = '';
    public bool $otpSent = false;
    public int $resendAvailableIn = 0;

    public function mount(OtpService $otpService): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        $this->mobile_number = (string) ($user->mobile_number ?? '');
        $this->resendAvailableIn = $otpService->secondsUntilResendAvailable($user);
        $this->otpSent = $this->resendAvailableIn > 0 || $user->mobileOtps()->exists();
    }

    public function sendOtp(OtpService $otpService, SmsService $smsService): void
    {
        $this->validate([
            'mobile_number' => ['required', 'string', 'max:32', 'regex:/^\+?[0-9]{10,15}$/'],
        ]);

        $user = Auth::user();
        if ($user === null) {
            $this->redirect(route('login', absolute: false), navigate: true);

            return;
        }

        $secondsUntilResend = $otpService->secondsUntilResendAvailable($user);
        if ($secondsUntilResend > 0) {
            $this->addError('mobile_number', __('Please wait :seconds seconds before requesting another OTP.', [
                'seconds' => $secondsUntilResend,
            ]));
            $this->resendAvailableIn = $secondsUntilResend;

            return;
        }

        $user->forceFill([
            'mobile_number' => $this->mobile_number,
            'mobile_verified_at' => null,
        ])->save();

        try {
            $otp = $otpService->generate($user);

            $smsService->send(
                $this->mobile_number,
                __('Your DocuTrust verification code is :otp. It expires in 5 minutes.', ['otp' => $otp]),
            );
        } catch (\Throwable $throwable) {
            report($throwable);
            $this->addError('mobile_number', __('Unable to send OTP right now. Please try again.'));

            return;
        }

        $this->otpSent = true;
        $this->resendAvailableIn = 60;
        session()->flash('status', __('OTP sent successfully.'));
    }

    public function verifyOtp(OnboardingAuditLogger $auditLogger, OtpService $otpService): void
    {
        $this->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $user = Auth::user();
        if ($user === null) {
            $this->redirect(route('login', absolute: false), navigate: true);

            return;
        }

        $isValidOtp = $otpService->verify($user, $this->otp);
        if (! $isValidOtp) {
            $this->addError('otp', __('Invalid or expired OTP.'));

            return;
        }

        $user->forceFill([
            'mobile_verified_at' => now(),
            'onboarding_step' => OnboardingStep::Kyc,
        ])->save();
        $auditLogger->log($user, 'phone_verified');

        $this->redirect(route('onboarding.kyc', absolute: false), navigate: true);
    }
}; ?>

<x-auth.onboarding-wizard-shell :active-step="2">
    <h1 class="text-2xl font-semibold tracking-tight text-[#1F2937] dark:text-zinc-100 sm:text-3xl">{{ __('Mobile Verification') }}</h1>
    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400 sm:text-base">{{ __('Secure your account with a one-time SMS code. We only use your number for account protection.') }}</p>

    <x-auth-session-status class="mt-4 rounded-xl border border-[#2EC4B6]/25 bg-[#2EC4B6]/10 px-4 py-3 text-center text-sm text-[#1B5E20] dark:border-teal-500/30 dark:text-teal-300" :status="session('status')" />

    <form wire:submit="sendOtp" class="mt-6 flex flex-col gap-6">
        <div class="rounded-2xl border border-zinc-200 bg-zinc-50/60 p-4 dark:border-zinc-700 dark:bg-zinc-800/40 sm:p-5">
            <flux:input
                wire:model="mobile_number"
                type="tel"
                label="{{ __('Mobile Number') }}"
                placeholder="{{ __('+1 555 000 0000') }}"
                autocomplete="tel"
                autofocus
                class="border-gray-300 focus:border-[#2EC4B6] focus:ring-[#2EC4B6] transition dark:border-zinc-600"
            />
            <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Use international format to ensure SMS delivery.') }}</p>
        </div>

        <div x-data="{ seconds: @entangle('resendAvailableIn') }" x-init="setInterval(() => { if (seconds > 0) { seconds--; } }, 1000)">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="sendOtp" class="w-full bg-[#2EC4B6] text-white shadow-md shadow-[#2EC4B6]/25 transition hover:bg-[#1B5E20] hover:text-white" x-bind:disabled="seconds > 0">
                <span wire:loading.remove wire:target="sendOtp">
                    <span x-show="seconds === 0">{{ __('Send OTP') }}</span>
                    <span x-show="seconds > 0">{{ __('Resend OTP in ') }}<span x-text="seconds"></span>{{ __('s') }}</span>
                </span>
                <span wire:loading wire:target="sendOtp">{{ __('Sending…') }}</span>
            </flux:button>
        </div>

        @if ($otpSent)
            <div class="rounded-2xl border border-[#2EC4B6]/25 bg-[#2EC4B6]/5 p-4 dark:border-teal-500/25 dark:bg-teal-500/5 sm:p-5">
                <p class="text-sm font-medium text-[#1F2937] dark:text-zinc-200">{{ __('Enter verification code') }}</p>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Each box accepts one digit and moves to the next automatically.') }}</p>
                <x-auth.otp-inputs model="otp" :auto-submit="false" />
            </div>

            <flux:button type="button" variant="primary" wire:click="verifyOtp" wire:loading.attr="disabled" wire:target="verifyOtp" class="w-full bg-[#2EC4B6] text-white shadow-md shadow-[#2EC4B6]/25 transition hover:bg-[#1B5E20] hover:text-white">
                <span wire:loading.remove wire:target="verifyOtp">{{ __('Verify OTP') }}</span>
                <span wire:loading wire:target="verifyOtp">{{ __('Verifying…') }}</span>
            </flux:button>
        @endif

        @error('otp')
            <p class="text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
        @enderror

        @error('mobile_number')
            <p class="text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
        @enderror

        @if (session('status'))
            <p class="text-sm text-[#1B5E20] dark:text-teal-300">{{ session('status') }}</p>
        @endif

    </form>
</x-auth.onboarding-wizard-shell>
