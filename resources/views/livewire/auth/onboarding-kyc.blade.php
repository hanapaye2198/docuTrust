<?php

use App\Enums\EkycStatus;
use App\Enums\OnboardingStep;
use App\Exceptions\EkycOcrUnavailableException;
use App\Models\EkycRecord;
use App\Services\Ekyc\EkycNameVerificationService;
use App\Services\Ekyc\EkycProviderManager;
use App\Services\OnboardingAuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.auth.register')] class extends Component {
    use WithFileUploads;

    public string $kyc_id_type = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $id_document = null;

    public bool $useSumsub = false;

    public bool $sumsubCompleted = false;

    public function mount(): void
    {
        $this->useSumsub = config('ekyc.default_driver') === 'sumsub';
    }

    /**
     * Handle Sumsub WebSDK completion event from the frontend.
     * The actual verification result arrives via webhook, but we can advance
     * the user to the next step since Sumsub has captured their data.
     */
    public function sumsubFlowCompleted(OnboardingAuditLogger $auditLogger): void
    {
        $user = Auth::user();
        if ($user === null) {
            $this->redirect(route('login', absolute: false), navigate: true);

            return;
        }

        // The webhook will update ekyc_status to Verified/Rejected.
        // For now, mark as pending and advance onboarding so the user isn't blocked.
        if ($user->ekyc_status === EkycStatus::NotSubmitted) {
            $user->forceFill(['ekyc_status' => EkycStatus::Pending])->save();
        }

        $user->forceFill(['onboarding_step' => OnboardingStep::Mfa])->save();
        $auditLogger->log($user, 'ekyc_sumsub_submitted');

        $this->redirect(route('onboarding.mfa', absolute: false), navigate: true);
    }

    /**
     * Tesseract-based verification (synchronous, local OCR).
     */
    public function continue(
        EkycNameVerificationService $verificationService,
        OnboardingAuditLogger $auditLogger,
    ): void {
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
        $absolutePath = Storage::disk('local')->path($path);

        try {
            $result = $verificationService->verify($user, $absolutePath);
        } catch (EkycOcrUnavailableException $exception) {
            Storage::disk('local')->delete($path);

            $this->addError('id_document', $exception->getMessage());

            return;
        }

        if (! $result->matched) {
            EkycRecord::query()->create([
                'user_id' => $user->id,
                'document_type' => $this->kyc_id_type,
                'document_path' => $path,
                'provider' => 'tesseract',
                'ocr_text' => $result->ocrText,
                'status' => EkycStatus::Rejected->value,
                'rejection_reason' => $result->message,
            ]);

            $user->forceFill([
                'kyc_id_type' => $this->kyc_id_type,
                'kyc_file_path' => $path,
                'kyc_verified_at' => null,
                'ekyc_status' => EkycStatus::Rejected,
            ])->save();

            $auditLogger->log($user, 'ekyc_name_mismatch');

            $this->addError('id_document', $result->message);

            return;
        }

        EkycRecord::query()->create([
            'user_id' => $user->id,
            'document_type' => $this->kyc_id_type,
            'document_path' => $path,
            'provider' => 'tesseract',
            'ocr_text' => $result->ocrText,
            'status' => EkycStatus::Verified->value,
            'verified_at' => now(),
        ]);

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
    <h1 class="text-2xl font-semibold tracking-tight text-[#1F2937] dark:text-zinc-100 sm:text-3xl">{{ __('Verify your Identity') }}</h1>

    @if ($useSumsub)
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400 sm:text-base">{{ __('Complete identity verification by following the steps below. You will be asked to capture your ID document and take a selfie.') }}</p>
    @else
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400 sm:text-base">{{ __('Upload a clear photo of your government ID. We will check that the name on the ID matches the name on your account.') }}</p>
    @endif

    <x-auth-session-status class="mt-4 rounded-xl border border-[#2EC4B6]/25 bg-[#2EC4B6]/10 px-4 py-3 text-center text-sm text-[#1B5E20] dark:border-teal-500/30 dark:text-teal-300" :status="session('status')" />

    @if ($useSumsub)
        {{-- Sumsub WebSDK integration --}}
        <div class="mt-6 flex flex-col gap-6">
            <div id="sumsub-websdk-container" wire:ignore class="min-h-[480px] rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/40"></div>

            <div id="sumsub-error" class="hidden rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-center text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300"></div>

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
        </div>

        @script
        <script>
            (function () {
                const container = document.getElementById('sumsub-websdk-container');
                const errorEl = document.getElementById('sumsub-error');

                function showError(message) {
                    errorEl.textContent = message;
                    errorEl.classList.remove('hidden');
                }

                function loadSumsubSdk() {
                    return new Promise((resolve, reject) => {
                        if (window.snsWebSdk) {
                            resolve();
                            return;
                        }
                        const script = document.createElement('script');
                        script.src = 'https://static.sumsub.com/idensic/static/sns-websdk-builder.js';
                        script.onload = resolve;
                        script.onerror = () => reject(new Error('Failed to load Sumsub SDK'));
                        document.head.appendChild(script);
                    });
                }

                async function getAccessToken() {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                    const response = await fetch('/api/ekyc/token', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken || '',
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        credentials: 'same-origin',
                    });

                    if (!response.ok) {
                        const data = await response.json().catch(() => ({}));
                        throw new Error(data.message || 'Failed to get verification token');
                    }

                    const data = await response.json();
                    return data.access_token;
                }

                async function initWidget() {
                    try {
                        await loadSumsubSdk();
                        const accessToken = await getAccessToken();

                        const snsWebSdkInstance = snsWebSdk
                            .init(accessToken, () => getAccessToken())
                            .withConf({
                                lang: document.documentElement.lang || 'en',
                                theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
                            })
                            .withOptions({ addViewportTag: false })
                            .on('idCheck.onError', (error) => {
                                console.error('Sumsub error:', error);
                                showError(error?.message || 'An error occurred during verification.');
                            })
                            .on('idCheck.applicantStatus', (payload) => {
                                if (payload.reviewStatus === 'completed' || payload.reviewStatus === 'pending') {
                                    $wire.call('sumsubFlowCompleted');
                                }
                            })
                            .build();

                        snsWebSdkInstance.launch('#sumsub-websdk-container');
                    } catch (error) {
                        console.error('Sumsub init error:', error);
                        showError(error.message || 'Could not start identity verification. Please try again.');
                    }
                }

                initWidget();
            })();
        </script>
        @endscript
    @else
        {{-- Tesseract-based upload form (existing flow) --}}
        <form wire:submit="continue" class="mt-6 flex flex-col gap-6">
            <div class="rounded-2xl border border-zinc-200 bg-zinc-50/60 p-4 dark:border-zinc-700 dark:bg-zinc-800/40 sm:p-5">
                <flux:select wire:model="kyc_id_type" label="{{ __('ID type') }}" placeholder="{{ __('Select…') }}" class="border-gray-300 focus:border-[#2EC4B6] dark:border-zinc-600">
                    <flux:select.option value="passport">{{ __('Passport') }}</flux:select.option>
                    <flux:select.option value="drivers_license">{{ __('Driver\'s License') }}</flux:select.option>
                    <flux:select.option value="national_id">{{ __('National ID') }}</flux:select.option>
                </flux:select>
                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Use the same name as when you registered: :name', ['name' => auth()->user()?->name ?? '—']) }}</p>
            </div>

            <div>
                <flux:label>{{ __('ID image') }}</flux:label>
                <label class="mt-2 flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-gray-300 bg-gray-50 px-4 py-8 transition duration-300 hover:border-[#2EC4B6] hover:bg-[#2EC4B6]/5 dark:border-zinc-600 dark:bg-zinc-800/50 dark:hover:border-teal-500/60 motion-safe:hover:scale-[1.01]">
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

            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="continue,id_document" class="w-full bg-[#2EC4B6] text-white shadow-md shadow-[#2EC4B6]/25 transition hover:bg-[#1B5E20] hover:text-white">
                <span wire:loading.remove wire:target="continue,id_document">{{ __('Continue') }}</span>
                <span wire:loading wire:target="continue,id_document">{{ __('Verifying name on ID…') }}</span>
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
    @endif
</x-auth.onboarding-wizard-shell>
