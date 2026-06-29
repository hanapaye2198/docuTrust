<?php

use App\Enums\UserRole;
use App\Services\AttorneyApplicationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Livewire\Volt\Component;

new class extends Component {
    use WithFileUploads;

    public string $commissionNumber = '';

    public string $commissionJurisdiction = 'Philippines';

    public string $commissionIssuedAt = '';

    public string $commissionExpiresAt = '';

    public string $rollNumber = '';

    public string $ibpNumber = '';

    public string $ptrNumber = '';

    public string $mcleComplianceNumber = '';

    public $commissionDocument = null;

    public $ibpDocument = null;

    public $ptrDocument = null;

    public $mcleDocument = null;

    public $sealImage = null;

    public $signatureImage = null;

    public function mount(AttorneyApplicationService $applications): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        if (! in_array($user->role, [UserRole::Client, UserRole::Notary], true)) {
            abort(403);
        }

        $credential = $applications->latestCredential($user);
        if ($credential !== null) {
            $this->commissionNumber = $credential->commission_number;
            $this->commissionJurisdiction = $credential->commission_jurisdiction;
            $this->commissionIssuedAt = $credential->commission_issued_at?->format('Y-m-d') ?? '';
            $this->commissionExpiresAt = $credential->commission_expires_at?->format('Y-m-d') ?? '';
            $this->rollNumber = $credential->roll_number ?? '';
            $this->ibpNumber = $credential->ibp_number ?? '';
            $this->ptrNumber = $credential->ptr_number ?? '';
            $this->mcleComplianceNumber = $credential->mcle_compliance_number ?? '';
        }
    }

    public function with(AttorneyApplicationService $applications): array
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        $credential = $applications->latestCredential($user);

        return [
            'credential' => $credential,
            'canSubmit' => $applications->canSubmitApplication($user),
            'requiresRenewal' => $applications->requiresRenewal($user),
            'practiceEligibility' => $applications->practiceEligibility($user),
            'isRenewal' => $user->role === UserRole::Notary && $applications->requiresRenewal($user),
        ];
    }

    public function submit(AttorneyApplicationService $applications): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        $validated = $this->validate([
            'commissionNumber' => ['required', 'string', 'max:100'],
            'commissionJurisdiction' => ['required', 'string', 'max:255'],
            'commissionIssuedAt' => ['required', 'date'],
            'commissionExpiresAt' => ['required', 'date', 'after:commissionIssuedAt'],
            'rollNumber' => ['nullable', 'string', 'max:100'],
            'ibpNumber' => ['nullable', 'string', 'max:100'],
            'ptrNumber' => ['nullable', 'string', 'max:100'],
            'mcleComplianceNumber' => ['nullable', 'string', 'max:100'],
            'commissionDocument' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'ibpDocument' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'ptrDocument' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'mcleDocument' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'sealImage' => ['required', 'image', 'max:2048'],
            'signatureImage' => ['required', 'image', 'max:2048'],
        ]);

        $existing = $applications->latestCredential($user);
        $isRenewal = $user->role === UserRole::Notary && $applications->requiresRenewal($user);

        try {
            $applications->submit($user, [
                'commission_number' => trim($validated['commissionNumber']),
                'commission_jurisdiction' => trim($validated['commissionJurisdiction']),
                'commission_issued_at' => $validated['commissionIssuedAt'],
                'commission_expires_at' => $validated['commissionExpiresAt'],
                'roll_number' => trim((string) ($validated['rollNumber'] ?? '')) ?: null,
                'ibp_number' => trim((string) ($validated['ibpNumber'] ?? '')) ?: null,
                'ptr_number' => trim((string) ($validated['ptrNumber'] ?? '')) ?: null,
                'mcle_compliance_number' => trim((string) ($validated['mcleComplianceNumber'] ?? '')) ?: null,
                'commission_document_path' => $applications->storeUploadedFile($this->commissionDocument, 'notary/applications/commission'),
                'ibp_document_path' => $applications->storeUploadedFile($this->ibpDocument, 'notary/applications/ibp'),
                'ptr_document_path' => $applications->storeUploadedFile($this->ptrDocument, 'notary/applications/ptr'),
                'mcle_document_path' => $this->mcleDocument
                    ? $applications->storeUploadedFile($this->mcleDocument, 'notary/applications/mcle')
                    : ($existing?->mcle_document_path),
                'seal_image_path' => $applications->storeUploadedFile($this->sealImage, 'notary/seals'),
                'signature_image_path' => $applications->storeUploadedFile($this->signatureImage, 'notary/signatures'),
            ], $isRenewal);

            $this->reset(['commissionDocument', 'ibpDocument', 'ptrDocument', 'mcleDocument', 'sealImage', 'signatureImage']);

            session()->flash('attorney-application-status', $isRenewal
                ? __('Renewal application submitted. A Notary Admin will review it shortly.')
                : __('Attorney application submitted. A Notary Admin will review it shortly.'));
        } catch (\RuntimeException $exception) {
            $this->addError('submit', $exception->getMessage());
        }
    }
}; ?>

<section class="w-full">
    <x-settings.trust-layout
        :heading="__('Attorney application')"
        :subheading="__('Apply to practice as an attorney / notary public on the e-Notary platform.')"
    >
        @if (session('attorney-application-status'))
            <div class="mb-6 rounded-xl border border-emerald-200/90 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-100">
                {{ session('attorney-application-status') }}
            </div>
        @endif

        @if ($credential?->isPending())
            <div class="mb-6 rounded-xl border border-amber-200/90 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100">
                {{ __('Your application is pending review by a Notary Admin.') }}
                @if ($credential->submitted_at)
                    <span class="block mt-1 text-xs opacity-80">{{ __('Submitted :time', ['time' => $credential->submitted_at->diffForHumans()]) }}</span>
                @endif
            </div>
        @endif

        @if ($credential?->status === 'rejected')
            <div class="mb-6 rounded-xl border border-red-200/90 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-100">
                <p class="font-semibold">{{ __('Application not approved') }}</p>
                <p class="mt-1">{{ $credential->rejection_reason }}</p>
            </div>
        @endif

        @if ($practiceEligibility['allowed'])
            <div class="mb-6 rounded-xl border border-emerald-200/90 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-100">
                {{ __('You are approved to practice as an attorney. Manage cases from the e-Notary dashboard.') }}
                <flux:button variant="primary" size="sm" class="mt-3" :href="route('notary.dashboard')" wire:navigate>{{ __('e-Notary dashboard') }}</flux:button>
            </div>
        @endif

        @if ($requiresRenewal && ! $credential?->isPending())
            <div class="mb-6 rounded-xl border border-amber-200/90 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100">
                {{ __('Your commission is expiring soon or has expired. Submit a renewal application to continue new e-Notary cases.') }}
            </div>
        @endif

        @if (! $canSubmit)
            <div class="rounded-2xl border border-zinc-200/90 bg-white p-6 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-400">
                @if (! auth()->user()?->hasCompletedOnboarding())
                    {{ __('Complete onboarding (email, mobile, eKYC, and MFA) before applying.') }}
                    <flux:button variant="outline" size="sm" class="mt-3" :href="route('onboarding.email.verify')" wire:navigate>{{ __('Continue onboarding') }}</flux:button>
                @elseif ($credential?->isPending())
                    {{ __('You already have a pending application.') }}
                @else
                    {{ __('You cannot submit an application at this time.') }}
                @endif
            </div>
        @else
            <div class="rounded-2xl border border-zinc-200/90 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900/40">
                <p class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
                    @if ($isRenewal)
                        {{ __('Submit updated commission documents for renewal approval.') }}
                    @else
                        {{ __('Upload proof of commission and attorney identifiers. A Notary Admin will verify before granting attorney access.') }}
                    @endif
                </p>

                <form wire:submit="submit" class="space-y-6">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>{{ __('Commission number') }}</flux:label>
                            <flux:input wire:model="commissionNumber" type="text" required />
                            <flux:error name="commissionNumber" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('Jurisdiction') }}</flux:label>
                            <flux:input wire:model="commissionJurisdiction" type="text" required />
                            <flux:error name="commissionJurisdiction" />
                        </flux:field>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>{{ __('Commission issued') }}</flux:label>
                            <flux:input wire:model="commissionIssuedAt" type="date" required />
                            <flux:error name="commissionIssuedAt" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('Commission expires') }}</flux:label>
                            <flux:input wire:model="commissionExpiresAt" type="date" required />
                            <flux:error name="commissionExpiresAt" />
                        </flux:field>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>{{ __('Roll number') }}</flux:label>
                            <flux:input wire:model="rollNumber" type="text" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('IBP number') }}</flux:label>
                            <flux:input wire:model="ibpNumber" type="text" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('PTR number') }}</flux:label>
                            <flux:input wire:model="ptrNumber" type="text" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('MCLE compliance number') }}</flux:label>
                            <flux:input wire:model="mcleComplianceNumber" type="text" />
                        </flux:field>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>{{ __('Commission document') }}</flux:label>
                            <input type="file" wire:model="commissionDocument" accept=".pdf,image/*" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                            <flux:error name="commissionDocument" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('IBP ID document') }}</flux:label>
                            <input type="file" wire:model="ibpDocument" accept=".pdf,image/*" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                            <flux:error name="ibpDocument" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('PTR document') }}</flux:label>
                            <input type="file" wire:model="ptrDocument" accept=".pdf,image/*" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                            <flux:error name="ptrDocument" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('MCLE document (optional)') }}</flux:label>
                            <input type="file" wire:model="mcleDocument" accept=".pdf,image/*" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                            <flux:error name="mcleDocument" />
                        </flux:field>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.25fr)_minmax(18rem,0.75fr)]">
                        <div class="rounded-2xl border border-dashed border-zinc-300 bg-zinc-50/70 p-5 transition dark:border-zinc-700 dark:bg-zinc-950/30">
                            <div class="flex items-start gap-3">
                                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-teal-100 text-teal-700 dark:bg-teal-500/15 dark:text-teal-300">
                                    <flux:icon name="cloud-arrow-up" class="size-5" />
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Notary seal image') }}</p>
                                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                                        {{ __('Upload a clear image of your official seal for review with your attorney application.') }}
                                    </p>
                                </div>
                            </div>

                            <label class="mt-5 flex cursor-pointer flex-col items-center justify-center rounded-2xl border border-zinc-200 bg-white px-4 py-6 text-center shadow-sm transition hover:border-teal-300 hover:bg-teal-50/50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-teal-500/50 dark:hover:bg-teal-500/10">
                                <input type="file" wire:model="sealImage" accept="image/*" class="sr-only" />
                                <span class="text-sm font-semibold text-teal-700 dark:text-teal-300">{{ __('Choose seal image') }}</span>
                                <span class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('PNG or JPG, max 2MB') }}</span>
                            </label>

                            <flux:error name="sealImage" />
                            <div wire:loading wire:target="sealImage" class="mt-3 text-sm text-teal-600 dark:text-teal-400">
                                {{ __('Preparing seal preview...') }}
                            </div>

                            @if ($sealImage)
                                <p class="mt-3 truncate text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ __('Selected: :file', ['file' => $sealImage->getClientOriginalName()]) }}
                                </p>
                            @endif
                        </div>

                        <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-950/40">
                            @if ($sealImage)
                                <div class="mb-4 flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-teal-950 dark:text-teal-100">{{ __('Seal selected') }}</p>
                                        <p class="mt-1 text-xs text-teal-800 dark:text-teal-200">{{ __('Submit the application to send this seal for review.') }}</p>
                                    </div>
                                    <flux:badge color="emerald" size="sm">{{ __('Ready') }}</flux:badge>
                                </div>
                                <div class="flex min-h-44 items-center justify-center rounded-2xl border border-teal-200 bg-teal-50/70 p-4 dark:border-teal-500/30 dark:bg-teal-500/10">
                                    <div class="text-center text-teal-900 dark:text-teal-100">
                                        <flux:icon name="check-circle" class="mx-auto size-8" />
                                        <p class="mt-3 text-sm font-semibold">{{ __('Image uploaded successfully') }}</p>
                                        <p class="mt-1 text-xs">{{ __('Continue once the selected file is correct.') }}</p>
                                    </div>
                                </div>
                            @else
                                <div class="flex min-h-44 flex-col items-center justify-center rounded-2xl border border-zinc-200 bg-zinc-50 p-5 text-center text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/60 dark:text-zinc-300">
                                    <flux:icon name="shield-check" class="size-8" />
                                    <p class="mt-3 text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Seal preview will appear here') }}</p>
                                    <p class="mt-1 text-xs">{{ __('Select an image to confirm it is readable before you submit.') }}</p>
                                </div>
                            @endif
                        </div>

                        <flux:field>
                            <flux:label>{{ __('Registered signature') }}</flux:label>
                            <input type="file" wire:model="signatureImage" accept="image/*" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                            <flux:error name="signatureImage" />
                        </flux:field>
                    </div>

                    <flux:error name="submit" />

                    <flux:button variant="primary" type="submit" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="submit">{{ $isRenewal ? __('Submit renewal') : __('Submit application') }}</span>
                        <span wire:loading wire:target="submit">{{ __('Submitting…') }}</span>
                    </flux:button>
                </form>
            </div>
        @endif

        <div class="mt-4">
            <flux:button variant="ghost" :href="route('settings.trust-profile', [], false)">{{ __('Back to trust profile') }}</flux:button>
        </div>
    </x-settings.trust-layout>
</section>
