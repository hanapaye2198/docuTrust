<?php

use App\Enums\OnboardingStep;
use App\Enums\EkycStatus;
use App\Models\EkycRecord;
use App\Services\OnboardingAuditLogger;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public bool $submitted = false;

    public function submitEkyc(OnboardingAuditLogger $auditLogger): void
    {
        $user = Auth::user();
        if ($user === null) {
            $this->redirect(route('login', absolute: false), navigate: true);

            return;
        }

        $user->forceFill([
            'ekyc_status' => EkycStatus::Pending,
            'onboarding_step' => OnboardingStep::EkycPending,
        ])->save();

        EkycRecord::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'document_type' => 'government_id',
                'document_path' => 'ekyc/pending/'.$user->id.'.pdf',
                'status' => EkycStatus::Pending->value,
                'verified_by' => null,
                'verified_at' => null,
            ],
        );

        $auditLogger->log($user, 'ekyc_submitted');
        $this->submitted = true;
    }

    public function approveEkyc(OnboardingAuditLogger $auditLogger): void
    {
        $user = Auth::user();
        if ($user === null) {
            $this->redirect(route('login', absolute: false), navigate: true);

            return;
        }

        $user->forceFill([
            'ekyc_status' => EkycStatus::Verified,
            'onboarding_step' => OnboardingStep::EkycVerified,
        ])->save();
        EkycRecord::query()->where('user_id', $user->id)->update([
            'status' => EkycStatus::Verified->value,
            'verified_by' => $user->id,
            'verified_at' => now(),
        ]);
        $auditLogger->log($user, 'ekyc_verified');

        $this->redirect(route('onboarding.mfa-setup', absolute: false), navigate: true);
    }
}; ?>

<div class="mx-auto w-full max-w-lg rounded-2xl border border-gray-200 bg-white p-6 shadow-lg sm:p-8">
    <x-auth.onboarding-progress current="ekyc" />
    <h1 class="text-2xl font-semibold text-[#1F2937]">{{ __('eKYC verification') }}</h1>
    <p class="mt-2 text-sm text-gray-600">{{ __('Submit your identity details, then continue once verification is approved.') }}</p>

    <div class="mt-6 space-y-3">
        <flux:button wire:click="submitEkyc" type="button" variant="primary" class="w-full">
            {{ __('Submit eKYC') }}
        </flux:button>

        @if ($submitted)
            <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700">
                {{ __('eKYC is marked as pending. Continue once approved.') }}
            </p>
        @endif

        <flux:button wire:click="approveEkyc" type="button" variant="ghost" class="w-full">
            {{ __('Mark eKYC as approved and continue') }}
        </flux:button>
    </div>
</div>
