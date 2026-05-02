<?php

use App\Enums\OnboardingStep;
use App\Services\OnboardingAuditLogger;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $phone = '';

    public function verifyPhone(OnboardingAuditLogger $auditLogger): void
    {
        $user = Auth::user();
        if ($user === null) {
            $this->redirect(route('login', absolute: false), navigate: true);

            return;
        }

        $user->forceFill([
            'onboarding_step' => OnboardingStep::PhoneVerified,
        ])->save();
        $auditLogger->log($user, 'phone_verified');

        $this->redirect(route('onboarding.ekyc', absolute: false), navigate: true);
    }
}; ?>

<div class="mx-auto w-full max-w-lg rounded-2xl border border-gray-200 bg-white p-6 shadow-lg sm:p-8">
    <x-auth.onboarding-progress current="phone" />
    <h1 class="text-2xl font-semibold text-[#1F2937]">{{ __('Phone verification') }}</h1>
    <p class="mt-2 text-sm text-gray-600">{{ __('Verify your mobile number to continue onboarding.') }}</p>

    <form wire:submit="verifyPhone" class="mt-6 space-y-4">
        <flux:input wire:model="phone" type="tel" label="{{ __('Mobile number') }}" placeholder="+63 9XX XXX XXXX" required />

        <flux:button type="submit" variant="primary" class="w-full">
            {{ __('Verify phone and continue') }}
        </flux:button>
    </form>
</div>
