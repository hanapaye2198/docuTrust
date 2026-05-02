<?php

use App\Enums\EkycStatus;
use App\Enums\OnboardingStep;
use App\Services\OnboardingAuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.auth.register')] class extends Component {
    use WithFileUploads;

    public string $kyc_id_type = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $id_document = null;

    public function continue(OnboardingAuditLogger $auditLogger): void
    {
        $this->validate([
            'kyc_id_type' => ['required', 'string', Rule::in(['passport', 'drivers_license', 'national_id'])],
            'id_document' => ['required', 'image', 'max:5120'],
        ]);

        $user = Auth::user();
        if ($user === null) {
            $this->redirect(route('login', absolute: false), navigate: true);

            return;
        }

        $path = $this->id_document->store('kyc/'.$user->id, 'local');

        $user->forceFill([
            'kyc_id_type' => $this->kyc_id_type,
            'kyc_file_path' => $path,
            'kyc_verified_at' => now(),
            'ekyc_status' => EkycStatus::Verified,
            'onboarding_step' => OnboardingStep::Mfa,
        ])->save();

        $auditLogger->log($user, 'ekyc_submitted');

        $this->redirect(route('onboarding.mfa', absolute: false), navigate: true);
    }

    public function skip(OnboardingAuditLogger $auditLogger): void
    {
        $user = Auth::user();
        if ($user === null) {
            $this->redirect(route('login', absolute: false), navigate: true);

            return;
        }

        $user->forceFill([
            'onboarding_step' => OnboardingStep::Mfa,
        ])->save();
        $auditLogger->log($user, 'ekyc_skipped');

        $this->redirect(route('onboarding.mfa', absolute: false), navigate: true);
    }
}; ?>

<x-auth.onboarding-wizard-shell :active-step="3">
    <h1 class="text-2xl font-semibold text-[#1F2937] dark:text-zinc-100">{{ __('Verify your Identity') }}</h1>
    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Choose your ID type and upload a clear photo. PNG or JPG, max 5 MB.') }}</p>

    <x-auth-session-status class="mt-4 rounded-lg bg-[#2EC4B6]/10 px-3 py-2 text-center text-sm text-[#1B5E20] dark:text-teal-300" :status="session('status')" />

    <form wire:submit="continue" class="mt-6 flex flex-col gap-6">
        <flux:select wire:model="kyc_id_type" label="{{ __('ID type') }}" placeholder="{{ __('Select…') }}" class="border-gray-300 focus:border-[#2EC4B6] dark:border-zinc-600">
            <flux:select.option value="passport">{{ __('Passport') }}</flux:select.option>
            <flux:select.option value="drivers_license">{{ __('Driver\'s License') }}</flux:select.option>
            <flux:select.option value="national_id">{{ __('National ID') }}</flux:select.option>
        </flux:select>

        <div>
            <flux:label>{{ __('ID image') }}</flux:label>
            <label class="mt-2 flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 px-4 py-8 transition hover:border-[#2EC4B6] hover:bg-[#2EC4B6]/5 dark:border-zinc-600 dark:bg-zinc-800/50 dark:hover:border-teal-500/60">
                <input type="file" wire:model="id_document" accept="image/*" class="sr-only" />
                <svg class="mb-2 size-10 text-gray-400 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span class="text-sm font-medium text-[#1F2937] dark:text-zinc-200">{{ __('Click to upload or drag an image') }}</span>
                <span class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Recommended: well-lit, full document in frame') }}</span>
            </label>
            <div wire:loading wire:target="id_document" class="mt-2 text-xs text-[#1B5E20] dark:text-teal-300">{{ __('Uploading…') }}</div>
            @if ($id_document)
                <p class="mt-2 text-xs text-zinc-600 dark:text-zinc-400">{{ $id_document->getClientOriginalName() }}</p>
            @endif
            <flux:error name="id_document" />
        </div>

        <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="continue" class="w-full bg-[#2EC4B6] text-black transition hover:bg-[#1B5E20]">
            <span wire:loading.remove wire:target="continue">{{ __('Continue') }}</span>
            <span wire:loading wire:target="continue">{{ __('Uploading…') }}</span>
        </flux:button>

        <div class="relative">
            <div class="absolute inset-0 flex items-center" aria-hidden="true">
                <div class="w-full border-t border-gray-200 dark:border-zinc-700"></div>
            </div>
            <div class="relative flex justify-center text-xs uppercase tracking-wide text-zinc-500">
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
