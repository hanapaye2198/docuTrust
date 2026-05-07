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
    <h1 class="text-2xl font-semibold tracking-tight text-[#1F2937] dark:text-zinc-100 sm:text-3xl">{{ __('Create your free Signer account') }}</h1>
    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400 sm:text-base">{{ __('We sent a secure 6-digit code to your inbox. Enter it below to continue onboarding.') }}</p>

    <div class="mt-4 rounded-2xl border border-zinc-200 bg-zinc-50/80 px-4 py-4 dark:border-zinc-700 dark:bg-zinc-800/60 sm:px-5">
        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Email') }}</p>
        <p class="mt-1 text-sm font-semibold text-[#1F2937] dark:text-zinc-100">{{ Auth::user()?->email }}</p>
        <a
            href="{{ route('onboarding.change-email') }}"
            class="mt-2 inline-block text-sm font-medium text-[#1B5E20] underline underline-offset-4 transition hover:text-[#2EC4B6] dark:text-teal-300"
        >{{ __('Edit email address') }}</a>
    </div>

    <x-auth-session-status class="mt-4 rounded-xl border border-[#2EC4B6]/25 bg-[#2EC4B6]/10 px-4 py-3 text-center text-sm text-[#1B5E20] dark:border-teal-500/30 dark:text-teal-300" :status="session('status')" />

    <form wire:submit="verifyCode" class="mt-6 flex flex-col gap-6">
        <div class="rounded-2xl border border-[#2EC4B6]/20 bg-[#2EC4B6]/5 p-4 dark:border-teal-500/25 dark:bg-teal-500/5 sm:p-5">
            <p class="text-sm font-medium text-[#1F2937] dark:text-zinc-200">{{ __('Authentication code') }}</p>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('One digit per box. Typing automatically moves to the next input.') }}</p>
            <x-auth.otp-inputs model="code" :auto-submit="true" />
            <div class="mt-2">
                <flux:error name="code" />
            </div>
        </div>

        <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="verifyCode" class="w-full bg-[#2EC4B6] text-white shadow-md shadow-[#2EC4B6]/25 transition hover:bg-[#1B5E20] hover:text-white">
            <span wire:loading.remove wire:target="verifyCode">{{ __('Verify Code') }}</span>
            <span wire:loading wire:target="verifyCode">{{ __('Verifying…') }}</span>
        </flux:button>
    </form>
</x-auth.onboarding-wizard-shell>
