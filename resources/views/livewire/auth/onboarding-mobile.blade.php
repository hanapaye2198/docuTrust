<?php

use App\Enums\OnboardingStep;
use App\Rules\PhilippineMobileNumber;
use App\Services\OnboardingAuditLogger;
use App\Services\OtpAuditLogger;
use App\Services\OtpService;
use App\Services\SmsService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth.register')] class extends Component {
    public string $mobile_number = '';
    public string $otp = '';
    public bool $otpSent = false;
    public bool $verificationSuccess = false;
    public int $resendAvailableIn = 0;

    public function mount(OtpService $otpService): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        $this->mobile_number = (string) ($user->mobile_number ?? '');
        $this->resendAvailableIn = $otpService->secondsUntilResendAvailable($user);
        $this->otpSent = $this->resendAvailableIn > 0;
        $this->verificationSuccess = $user->mobile_verified_at !== null;
    }

    public function sendOtp(OtpService $otpService, SmsService $smsService, OtpAuditLogger $otpAuditLogger): void
    {
        $this->validate([
            'mobile_number' => ['required', 'string', 'max:32', new PhilippineMobileNumber],
        ]);

        $user = Auth::user();
        if ($user === null) {
            $this->redirect(route('login', absolute: false), navigate: true);

            return;
        }

        $secondsUntilResend = $otpService->secondsUntilResendAvailable($user);
        if ($secondsUntilResend > 0) {
            $this->addError('mobile_number', __('Please wait :seconds seconds before requesting another code.', [
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
            $result = $otpService->generateOtp(
                user: $user,
                email: null,
                mobileNumber: $this->mobile_number,
                purpose: 'mobile_verification',
                channel: 'sms',
                request: request(),
            );

            if (! $result['success']) {
                $message = match ($result['code']) {
                    'rate_limited' => __('Too many requests. Please wait a minute and try again.'),
                    'cooldown_active' => __('Please wait before requesting another code.'),
                    default => __('Unable to send verification code. Please try again.'),
                };
                $this->addError('mobile_number', $message);
                if ($result['code'] === 'cooldown_active') {
                    $this->resendAvailableIn = (int) ($result['data']['retry_after_seconds'] ?? 60);
                }

                return;
            }

            $plainOtp = (string) ($result['data']['otp'] ?? '');
            $smsService->send(
                $this->mobile_number,
                $smsService->formatOtpMessage(),
                $plainOtp,
            );
        } catch (\Throwable $throwable) {
            report($throwable);
            $otpAuditLogger->log($user, 'phone_otp_send_failed', $this->mobile_number, request());
            $this->addError('mobile_number', __('Unable to send verification code right now. Please try again.'));

            return;
        }

        $this->otpSent = true;
        $this->otp = '';
        $this->resendAvailableIn = (int) ($result['data']['retry_after_seconds'] ?? config('otp.resend_cooldown_seconds', 60));
        session()->flash('status', __('Verification code sent to your mobile number.'));
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

        $result = $otpService->verifyOtp(
            inputOtp: $this->otp,
            user: $user,
            email: null,
            mobileNumber: (string) $user->mobile_number,
            request: request(),
        );

        if (! $result['success']) {
            $this->addError('otp', match ($result['code']) {
                'otp_expired' => __('This code has expired. Request a new one.'),
                'max_attempts_reached' => __('Too many attempts. Request a new code.'),
                'otp_not_found' => __('No active code found. Request a new one.'),
                default => __('Invalid verification code.'),
            });

            return;
        }

        $user->forceFill([
            'mobile_verified_at' => now(),
            'onboarding_step' => OnboardingStep::Kyc,
        ])->save();
        $auditLogger->log($user, 'phone_verified');

        $this->verificationSuccess = true;
        $this->redirect(route('onboarding.kyc', absolute: false), navigate: true);
    }

    public function skipForNow(OnboardingAuditLogger $auditLogger): void
    {
        $user = Auth::user();
        if ($user === null) {
            $this->redirect(route('login', absolute: false), navigate: true);

            return;
        }

        $auditLogger->log($user, 'phone_verification_skipped');
        $user->forceFill([
            'onboarding_step' => OnboardingStep::Kyc,
        ])->save();

        $this->redirect(route('onboarding.kyc', absolute: false), navigate: true);
    }
}; ?>

<x-auth.onboarding-wizard-shell :active-step="2">
    <h1 class="text-2xl font-semibold tracking-tight text-[#1F2937] dark:text-zinc-100 sm:text-3xl">{{ __('Mobile Verification') }}</h1>
    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400 sm:text-base">
        {{ __('Confirm your Philippine mobile number with a one-time SMS code. You can complete this later, but verification strengthens account security.') }}
    </p>

    @if (auth()->user()?->mobile_verified_at)
        <div class="mt-4 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
            <flux:icon.check-badge variant="mini" class="size-5" />
            <span>{{ __('Your mobile number is verified.') }}</span>
        </div>
    @elseif (auth()->user()?->mobile_number && ! auth()->user()?->mobile_verified_at)
        <div class="mt-4 flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
            <flux:icon.exclamation-triangle variant="mini" class="size-5" />
            <span>{{ __('Mobile number on file is not yet verified.') }}</span>
        </div>
    @endif

    <x-auth-session-status class="mt-4 rounded-xl border border-[#2EC4B6]/25 bg-[#2EC4B6]/10 px-4 py-3 text-center text-sm text-[#1B5E20] dark:border-teal-500/30 dark:text-teal-300" :status="session('status')" />

    @if ($verificationSuccess)
        <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-center dark:border-emerald-500/30 dark:bg-emerald-500/10">
            <flux:icon.check-circle class="mx-auto size-10 text-emerald-600 dark:text-emerald-400" />
            <p class="mt-3 text-sm font-medium text-emerald-800 dark:text-emerald-200">{{ __('Mobile verified successfully.') }}</p>
        </div>
    @else
        <form wire:submit="sendOtp" class="mt-6 flex flex-col gap-6">
            <div class="rounded-2xl border border-zinc-200 bg-zinc-50/60 p-4 dark:border-zinc-700 dark:bg-zinc-800/40 sm:p-5">
                <flux:input
                    wire:model="mobile_number"
                    type="tel"
                    inputmode="tel"
                    label="{{ __('Philippine Mobile Number') }}"
                    placeholder="09171234567"
                    autocomplete="tel"
                    autofocus
                    class="border-gray-300 focus:border-[#2EC4B6] focus:ring-[#2EC4B6] transition dark:border-zinc-600"
                />
                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Use 09XXXXXXXXX or +639XXXXXXXXX format.') }}</p>
            </div>

            <div x-data="{ seconds: @entangle('resendAvailableIn') }" x-init="const timer = setInterval(() => { if (seconds > 0) seconds--; }, 1000)">
                <flux:button
                    type="submit"
                    variant="primary"
                    wire:loading.attr="disabled"
                    wire:target="sendOtp"
                    class="w-full bg-[#2EC4B6] text-white shadow-md shadow-[#2EC4B6]/25 transition hover:bg-[#1B5E20] hover:text-white"
                    x-bind:disabled="seconds > 0"
                >
                    <span wire:loading.remove wire:target="sendOtp">
                        <span x-show="seconds === 0">{{ $otpSent ? __('Resend code') : __('Send verification code') }}</span>
                        <span x-show="seconds > 0" x-cloak>{{ __('Resend in ') }}<span x-text="seconds"></span>{{ __('s') }}</span>
                    </span>
                    <span wire:loading wire:target="sendOtp">{{ __('Sending…') }}</span>
                </flux:button>
            </div>

            @if ($otpSent)
                <div class="rounded-2xl border border-[#2EC4B6]/25 bg-[#2EC4B6]/5 p-4 dark:border-teal-500/25 dark:bg-teal-500/5 sm:p-5">
                    <p class="text-sm font-medium text-[#1F2937] dark:text-zinc-200">{{ __('Enter your 6-digit code') }}</p>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('Code expires in :minutes minutes.', ['minutes' => config('otp.expires_in_minutes', 5)]) }}
                    </p>
                    <x-auth.otp-inputs model="otp" :auto-submit="false" class="mt-4" />
                </div>

                <flux:button
                    type="button"
                    variant="primary"
                    wire:click="verifyOtp"
                    wire:loading.attr="disabled"
                    wire:target="verifyOtp"
                    class="w-full bg-[#2EC4B6] text-white shadow-md shadow-[#2EC4B6]/25 transition hover:bg-[#1B5E20] hover:text-white"
                >
                    <span wire:loading.remove wire:target="verifyOtp">{{ __('Verify code') }}</span>
                    <span wire:loading wire:target="verifyOtp">{{ __('Verifying…') }}</span>
                </flux:button>
            @endif

            @error('otp')
                <p class="text-sm text-rose-600 dark:text-rose-400" role="alert">{{ $message }}</p>
            @enderror

            @error('mobile_number')
                <p class="text-sm text-rose-600 dark:text-rose-400" role="alert">{{ $message }}</p>
            @enderror
        </form>

        <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
            <flux:button type="button" variant="ghost" wire:click="skipForNow" class="w-full text-zinc-600 dark:text-zinc-400">
                {{ __('Skip for now — verify later from your dashboard') }}
            </flux:button>
        </div>
    @endif
</x-auth.onboarding-wizard-shell>
