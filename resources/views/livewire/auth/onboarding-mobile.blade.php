<?php

use App\Enums\OnboardingStep;
use App\Services\OnboardingAuditLogger;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth.register')] class extends Component {
    public string $mobile_number = '';

    public function verifyMobile(OnboardingAuditLogger $auditLogger): void
    {
        $this->validate([
            'mobile_number' => ['required', 'string', 'max:32'],
        ]);

        $user = Auth::user();
        if ($user === null) {
            $this->redirect(route('login', absolute: false), navigate: true);

            return;
        }

        $user->forceFill([
            'mobile_number' => $this->mobile_number,
            'mobile_verified_at' => now(),
            'onboarding_step' => OnboardingStep::Kyc,
        ])->save();
        $auditLogger->log($user, 'phone_verified');

        $this->redirect(route('onboarding.kyc', absolute: false), navigate: true);
    }

    public function skip(OnboardingAuditLogger $auditLogger): void
    {
        $user = Auth::user();
        if ($user === null) {
            $this->redirect(route('login', absolute: false), navigate: true);

            return;
        }

        $user->forceFill([
            'onboarding_step' => OnboardingStep::Kyc,
        ])->save();
        $auditLogger->log($user, 'phone_skipped');

        $this->redirect(route('onboarding.kyc', absolute: false), navigate: true);
    }
}; ?>

<x-auth.onboarding-wizard-shell :active-step="2">
    <h1 class="text-2xl font-semibold text-[#1F2937] dark:text-zinc-100">{{ __('Mobile Verification') }}</h1>
    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Please provide your mobile number in the field below. You can skip and add this later from your profile.') }}</p>

    <x-auth-session-status class="mt-4 rounded-lg bg-[#2EC4B6]/10 px-3 py-2 text-center text-sm text-[#1B5E20] dark:text-teal-300" :status="session('status')" />

    <form wire:submit="verifyMobile" class="mt-6 flex flex-col gap-6">
        <flux:input
            wire:model="mobile_number"
            type="tel"
            label="{{ __('Mobile Number') }}"
            placeholder="{{ __('+1 555 000 0000') }}"
            autocomplete="tel"
            autofocus
            class="border-gray-300 focus:border-[#2EC4B6] focus:ring-[#2EC4B6] transition dark:border-zinc-600"
        />

        <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="verifyMobile" class="w-full bg-[#2EC4B6] text-white transition hover:bg-[#1B5E20] hover:text-white">
            <span wire:loading.remove wire:target="verifyMobile">{{ __('Verify Mobile Number') }}</span>
            <span wire:loading wire:target="verifyMobile">{{ __('Saving…') }}</span>
        </flux:button>

        <div class="relative">
            <div class="absolute inset-0 flex items-center" aria-hidden="true">
                <div class="w-full border-t border-gray-200 dark:border-zinc-700"></div>
            </div>
            <div class="relative flex justify-center text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-500">
                <span class="bg-white px-2 dark:bg-zinc-900">{{ __('or') }}</span>
            </div>
        </div>

        <flux:button
            type="button"
            wire:click="skip"
            wire:loading.attr="disabled"
            wire:target="skip"
            variant="ghost"
            class="w-full border border-gray-200 bg-gray-50 text-[#1F2937] hover:bg-gray-100 dark:border-zinc-700 dark:bg-zinc-800/80 dark:text-zinc-200 dark:hover:bg-zinc-800"
        >
            <span wire:loading.remove wire:target="skip">{{ __('Skip for now') }}</span>
            <span wire:loading wire:target="skip">{{ __('Continuing…') }}</span>
        </flux:button>
    </form>
</x-auth.onboarding-wizard-shell>
