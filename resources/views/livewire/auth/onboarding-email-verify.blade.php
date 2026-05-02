<?php

use App\Enums\OnboardingStep;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth.register')] class extends Component {
    public string $code = '';

    public function verifyCode(): void
    {
        $this->validate([
            'code' => ['required', 'string', 'size:6', 'regex:/^[0-9]+$/'],
        ]);

        $user = Auth::user();
        if ($user === null) {
            $this->redirect(route('login', absolute: false), navigate: true);

            return;
        }

        if (
            $user->email_otp !== $this->code
            || $user->email_otp_expires_at === null
            || now()->greaterThan($user->email_otp_expires_at)
        ) {
            throw ValidationException::withMessages([
                'code' => __('Invalid or expired code'),
            ]);
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'email_otp' => null,
            'email_otp_expires_at' => null,
            'onboarding_step' => OnboardingStep::MobileVerification,
        ])->save();

        $this->redirect(route('onboarding.mobile', absolute: false), navigate: true);
    }
}; ?>

<x-auth.onboarding-wizard-shell :active-step="1">
    <h1 class="text-2xl font-semibold text-[#1F2937] dark:text-zinc-100">{{ __('Create your free Signer account') }}</h1>
    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Enter the 6-digit authentication code sent to your email.') }}</p>

    <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/60">
        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Email') }}</p>
        <p class="mt-1 text-sm font-medium text-[#1F2937] dark:text-zinc-100">{{ Auth::user()?->email }}</p>
        <a
            href="{{ route('onboarding.change-email') }}"
            class="mt-2 inline-block text-sm font-medium text-[#1B5E20] underline hover:text-[#2EC4B6] dark:text-teal-300"
        >{{ __('Edit email address') }}</a>
    </div>

    <x-auth-session-status class="mt-4 rounded-lg bg-[#2EC4B6]/10 px-3 py-2 text-center text-sm text-[#1B5E20] dark:text-teal-300" :status="session('status')" />

    <form wire:submit="verifyCode" class="mt-6 flex flex-col gap-6">
        <div>
            <p class="text-sm font-medium text-[#1F2937] dark:text-zinc-200">{{ __('Authentication code') }}</p>
            <x-auth.otp-inputs model="code" :auto-submit="true" />
            <div class="mt-2">
                <flux:error name="code" />
            </div>
        </div>

        <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="verifyCode" class="w-full bg-[#2EC4B6] text-black transition hover:bg-[#1B5E20]">
            <span wire:loading.remove wire:target="verifyCode">{{ __('Verify Code') }}</span>
            <span wire:loading wire:target="verifyCode">{{ __('Verifying…') }}</span>
        </flux:button>
    </form>
</x-auth.onboarding-wizard-shell>
