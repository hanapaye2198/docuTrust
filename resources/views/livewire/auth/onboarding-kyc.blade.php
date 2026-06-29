<?php

use App\Enums\EkycStatus;
use App\Enums\OnboardingStep;
use App\Exceptions\EkycOcrUnavailableException;
use App\Models\EkycRecord;
use App\Models\User;
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
            'id_document' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:10240'],
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
            $this->markKycPendingReview(
                user: $user,
                path: $path,
                ocrText: null,
                reason: $exception->getMessage(),
                auditLogger: $auditLogger,
            );

            return;
        }

        if (! $result->matched) {
            $this->markKycPendingReview(
                user: $user,
                path: $path,
                ocrText: $result->ocrText,
                reason: $result->message,
                auditLogger: $auditLogger,
            );

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

    protected function markKycPendingReview(
        User $user,
        string $path,
        ?string $ocrText,
        string $reason,
        OnboardingAuditLogger $auditLogger,
    ): void {
        EkycRecord::query()->create([
            'user_id' => $user->id,
            'document_type' => $this->kyc_id_type,
            'document_path' => $path,
            'provider' => 'tesseract',
            'ocr_text' => $ocrText,
            'status' => EkycStatus::Pending->value,
            'rejection_reason' => $reason,
        ]);

        $user->forceFill([
            'kyc_id_type' => $this->kyc_id_type,
            'kyc_file_path' => $path,
            'kyc_verified_at' => null,
            'ekyc_status' => EkycStatus::Pending,
            'onboarding_step' => OnboardingStep::Mfa,
        ])->save();

        $auditLogger->log($user, 'ekyc_pending_review');

        session()->flash('status', __('We received your ID. It needs manual review, so you can continue onboarding while we check it.'));

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
                <div
                    class="mt-2 rounded-2xl border-2 border-dashed border-gray-300 bg-gray-50 p-4 transition duration-300 hover:border-[#2EC4B6] hover:bg-[#2EC4B6]/5 dark:border-zinc-600 dark:bg-zinc-800/50 dark:hover:border-teal-500/60 sm:p-5"
                    x-data="{ dragging: false, progress: 0 }"
                    x-bind:class="dragging ? 'border-[#2EC4B6] bg-[#2EC4B6]/10 dark:border-teal-500/80' : ''"
                    x-on:livewire-upload-start="progress = 1"
                    x-on:livewire-upload-finish="progress = 0"
                    x-on:livewire-upload-error="progress = 0"
                    x-on:livewire-upload-progress="progress = $event.detail.progress"
                    x-on:dragover.prevent="dragging = true"
                    x-on:dragleave.prevent="dragging = false"
                    x-on:drop.prevent="
                        dragging = false;
                        if ($event.dataTransfer.files.length) {
                            $refs.idDocument.files = $event.dataTransfer.files;
                            $refs.idDocument.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    "
                >
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[#2EC4B6]/10 text-[#1B5E20] dark:bg-teal-500/10 dark:text-teal-300">
                                <flux:icon.identification class="size-5" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-[#1F2937] dark:text-zinc-100">{{ __('Upload your government ID') }}</p>
                                <p class="mt-1 text-xs leading-5 text-zinc-500 dark:text-zinc-400">{{ __('JPG, JPEG, or PNG up to 10 MB. Use a clear, well-lit photo with the full ID inside the frame.') }}</p>
                                @if ($id_document)
                                    <p class="mt-2 truncate text-xs font-medium text-[#1B5E20] dark:text-teal-300">{{ $id_document->getClientOriginalName() }}</p>
                                @endif
                            </div>
                        </div>

                        <flux:button type="button" variant="outline" class="w-full sm:w-auto" x-on:click="$refs.idDocument.click()">
                            {{ __('Browse ID') }}
                        </flux:button>
                    </div>

                    <input x-ref="idDocument" type="file" wire:model="id_document" accept=".jpg,.jpeg,.png,image/jpeg,image/png" class="sr-only" />

                    <div wire:loading wire:target="id_document" class="mt-4">
                        <div class="flex items-center justify-between gap-3 text-xs text-zinc-500 dark:text-zinc-400">
                            <span>{{ __('Uploading ID') }}</span>
                            <span class="font-semibold tabular-nums text-[#1B5E20] dark:text-teal-300" x-text="(progress > 0 ? progress : 0) + '%'"></span>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-[#2EC4B6]/15 dark:bg-teal-950/50">
                            <div class="h-full rounded-full bg-[#2EC4B6] transition-all duration-300" x-bind:style="'width: ' + Math.max(progress, 8) + '%'"></div>
                        </div>
                    </div>
                </div>
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
