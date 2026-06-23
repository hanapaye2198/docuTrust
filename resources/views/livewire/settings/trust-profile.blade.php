<?php

use App\Enums\UserRole;
use App\Services\Notary\NotarySealProfileService;
use App\Services\OnboardingAuditLogger;
use App\Services\TrustProfile\TrustProfileService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Livewire\Volt\Component;

new class extends Component {
    use WithFileUploads;

    public string $address = '';

    public string $nationality = '';

    public ?string $date_of_birth = null;

    public string $government_id_type = '';

    public string $government_id_number = '';

    public string $signature_initials = '';

    public string $drawnSignature = '';

    public $profilePhoto = null;

    public $signatureUpload = null;

    public $notarySealUpload = null;

    public function mount(): void
    {
        $user = Auth::user();

        $this->address = (string) ($user->address ?? '');
        $this->nationality = (string) ($user->nationality ?? '');
        $this->date_of_birth = $user->date_of_birth?->format('Y-m-d');
        $this->government_id_type = (string) ($user->government_id_type ?? $user->kyc_id_type ?? '');
        $this->government_id_number = (string) ($user->government_id_number ?? '');
        $this->signature_initials = (string) ($user->signature_initials ?? '');
    }

    public function with(TrustProfileService $trustProfile, NotarySealProfileService $sealProfile): array
    {
        $user = Auth::user()->load('ekycRecord');
        $summary = $trustProfile->summary($user);
        $notaryCredential = $sealProfile->activeCredential($user);

        return [
            'user' => $user,
            'summary' => $summary,
            'verifications' => $trustProfile->verificationItems($user),
            'enotaryChecks' => $trustProfile->enotaryReadinessChecks($user),
            'activity' => $trustProfile->activityTimeline($user, 8),
            'sessions' => $trustProfile->activeSessions($user)->take(5),
            'completionFields' => $trustProfile->completionFields($user),
            'notaryCredential' => $notaryCredential,
            'hasNotarySeal' => $sealProfile->hasSealOnFile($user),
        ];
    }

    public function updateLegalIdentity(): void
    {
        $validated = $this->validate([
            'address' => ['required', 'string', 'max:1000'],
            'nationality' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'government_id_type' => ['required', 'string', 'max:50'],
            'government_id_number' => ['required', 'string', 'max:100'],
        ]);

        Auth::user()->update($validated);

        app(OnboardingAuditLogger::class)->log(Auth::user(), 'trust_profile.legal_identity_updated');

        Session::flash('trust-status', __('Legal identity saved.'));
    }

    public function uploadProfilePhoto(): void
    {
        $this->validate([
            'profilePhoto' => ['required', 'image', 'max:2048'],
        ]);

        $user = Auth::user();
        $disk = (string) config('filesystems.docutrust_disk', 'local');
        $path = $this->profilePhoto->store('trust-profile/'.$user->id.'/photos', $disk);

        if (filled($user->profile_photo_path) && Storage::disk($disk)->exists($user->profile_photo_path)) {
            Storage::disk($disk)->delete($user->profile_photo_path);
        }

        $user->update(['profile_photo_path' => $path]);
        $this->profilePhoto = null;

        Session::flash('trust-status', __('Profile photo updated.'));
    }

    public function saveDrawnSignature(): void
    {
        $this->validate([
            'drawnSignature' => ['required', 'string', 'starts_with:data:image/png;base64,'],
        ]);

        $this->persistSignature($this->drawnSignature, 'drawn');
        $this->drawnSignature = '';

        Session::flash('trust-status', __('Signature saved.'));
    }

    public function uploadNotarySeal(NotarySealProfileService $sealProfile): void
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->role === UserRole::Notary, 403);

        $this->validate([
            'notarySealUpload' => ['required', 'image', 'max:2048'],
        ]);

        $sealProfile->storeSeal($user, $this->notarySealUpload);
        $this->notarySealUpload = null;

        app(OnboardingAuditLogger::class)->log($user, 'trust_profile.notary_seal_updated');

        Session::flash('trust-status', __('Notary seal saved. It will be used on all cases.'));
    }

    public function saveUploadedSignature(): void
    {
        $this->validate([
            'signatureUpload' => ['required', 'image', 'max:2048'],
        ]);

        $user = Auth::user();
        $disk = (string) config('filesystems.docutrust_disk', 'local');
        $path = $this->signatureUpload->store('trust-profile/'.$user->id.'/signatures', $disk);

        if (filled($user->signature_image_path) && Storage::disk($disk)->exists($user->signature_image_path)) {
            Storage::disk($disk)->delete($user->signature_image_path);
        }

        $user->update([
            'signature_image_path' => $path,
            'signature_type' => 'uploaded',
        ]);

        $this->signatureUpload = null;

        Session::flash('trust-status', __('Signature uploaded.'));
    }

    public function saveInitials(): void
    {
        $validated = $this->validate([
            'signature_initials' => ['required', 'string', 'min:1', 'max:10'],
        ]);

        Auth::user()->update($validated);

        Session::flash('trust-status', __('Initials saved.'));
    }

    public function grantGpsPermission(): void
    {
        Auth::user()->update(['gps_permission_granted_at' => now()]);

        app(OnboardingAuditLogger::class)->log(Auth::user(), 'trust_profile.gps_granted');

        Session::flash('trust-status', __('Location permission saved for eNOTARY.'));
    }

    public function resendEmailVerification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('trust-status', __('Verification email sent.'));
    }

    private function persistSignature(string $dataUrl, string $type): void
    {
        $user = Auth::user();
        $disk = (string) config('filesystems.docutrust_disk', 'local');
        $binary = base64_decode((string) preg_replace('#^data:image/\w+;base64,#i', '', $dataUrl));

        if ($binary === false) {
            return;
        }

        $path = 'trust-profile/'.$user->id.'/signatures/'.uniqid('sig_', true).'.png';

        Storage::disk($disk)->put($path, $binary);

        if (filled($user->signature_image_path) && Storage::disk($disk)->exists($user->signature_image_path)) {
            Storage::disk($disk)->delete($user->signature_image_path);
        }

        $user->update([
            'signature_image_path' => $path,
            'signature_type' => $type,
        ]);
    }
}; ?>

@php
    $tierRing = match ($summary['trust_tier']) {
        'platinum' => 'ring-violet-500/40',
        'gold' => 'ring-amber-500/40',
        'silver' => 'ring-zinc-400/40',
        default => 'ring-teal-500/40',
    };
@endphp

<section class="w-full">
    <x-settings.trust-layout
        :heading="__('Trust & verification')"
        :subheading="__('Identity, security, and eNOTARY readiness for your account.')"
    >
        @if (session('trust-status'))
            <div class="mb-6 rounded-xl border border-emerald-200/90 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-100">
                {{ session('trust-status') }}
            </div>
        @endif

        <div class="space-y-8">
            {{-- Hero --}}
            <div class="rounded-2xl border border-zinc-200/90 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/40">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-center">
                    <div class="relative shrink-0">
                        @if ($user->profile_photo_path)
                            <img
                                src="{{ route('settings.trust-profile.photo') }}"
                                alt=""
                                class="size-20 rounded-2xl object-cover ring-2 {{ $tierRing }}"
                            />
                        @else
                            <flux:avatar size="lg" :name="$user->name" class="!size-20 !rounded-2xl ring-2 {{ $tierRing }}" />
                        @endif
                        <label class="absolute -bottom-1 -right-1 cursor-pointer rounded-full bg-teal-600 p-1.5 text-white shadow-md hover:bg-teal-500">
                            <flux:icon name="camera" class="size-3.5" />
                            <input type="file" class="sr-only" wire:model="profilePhoto" accept="image/*" />
                        </label>
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:heading size="lg" class="!mb-0">{{ $user->buildFullName() ?: $user->name }}</flux:heading>
                            <flux:badge variant="outline" size="sm">{{ $summary['badge_label'] }}</flux:badge>
                        </div>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $summary['role_label'] }}
                            · {{ __('Member since :date', ['date' => $user->created_at?->format('M Y') ?? '—']) }}
                        </p>

                        @if ($user->mobile_verified_at)
                            <div class="mt-3 inline-flex max-w-full flex-wrap items-center gap-2 rounded-xl border border-emerald-200/80 bg-emerald-50/80 px-3 py-2 text-xs font-medium text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
                                <flux:icon.check-badge variant="mini" class="size-4 shrink-0" />
                                <span>{{ __('Mobile verified') }}</span>
                                @if ($user->mobile_number)
                                    <span class="font-normal text-emerald-700/90 dark:text-emerald-200/80">· {{ $user->mobile_number }}</span>
                                @endif
                                <span class="font-normal text-emerald-700/80 dark:text-emerald-200/70">
                                    · {{ $user->mobile_verified_at->timezone('Asia/Manila')->format('M j, Y') }}
                                </span>
                            </div>
                        @elseif ($user->mobile_number)
                            <div class="mt-3 inline-flex max-w-full flex-wrap items-center gap-2 rounded-xl border border-amber-200/80 bg-amber-50/80 px-3 py-2 text-xs font-medium text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                                <flux:icon.exclamation-triangle variant="mini" class="size-4 shrink-0" />
                                <span>{{ __('Mobile not verified') }}</span>
                                <flux:button
                                    :href="route('onboarding.mobile')"
                                    variant="ghost"
                                    size="sm"
                                    class="!h-7 !px-2 !text-xs"
                                    wire:navigate
                                >
                                    {{ __('Verify now') }}
                                </flux:button>
                            </div>
                        @endif
                        @if ($profilePhoto)
                            <div class="mt-3">
                                <flux:button variant="primary" size="sm" type="button" wire:click="uploadProfilePhoto">{{ __('Save photo') }}</flux:button>
                                <flux:error name="profilePhoto" class="mt-1" />
                            </div>
                        @endif
                    </div>

                    <div class="grid shrink-0 grid-cols-3 gap-3 text-center sm:gap-4">
                        <div class="rounded-xl border border-zinc-100 bg-zinc-50 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-950/50">
                            <p class="text-2xl font-bold tabular-nums text-zinc-900 dark:text-zinc-50">{{ $summary['trust_score'] }}</p>
                            <p class="text-[11px] font-medium uppercase tracking-wide text-zinc-500">{{ __('Trust score') }}</p>
                        </div>
                        <div class="rounded-xl border border-zinc-100 bg-zinc-50 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-950/50">
                            <p class="text-2xl font-bold tabular-nums text-teal-700 dark:text-teal-400">{{ $summary['completion_percent'] }}%</p>
                            <p class="text-[11px] font-medium uppercase tracking-wide text-zinc-500">{{ __('Complete') }}</p>
                        </div>
                        <div class="rounded-xl border border-zinc-100 bg-zinc-50 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-950/50">
                            <p class="text-2xl font-bold tabular-nums text-zinc-900 dark:text-zinc-50">
                                {{ $summary['enotary_ready_count'] }}/{{ $summary['enotary_total'] }}
                            </p>
                            <p class="text-[11px] font-medium uppercase tracking-wide text-zinc-500">{{ __('eNOTARY') }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-6">
                    <div class="mb-2 flex items-center justify-between text-xs font-medium text-zinc-500">
                        <span>{{ __('Profile strength') }}</span>
                        <span>{{ $summary['completion_percent'] }}%</span>
                    </div>
                    <div class="h-1.5 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
                        <div class="h-full rounded-full bg-teal-500 transition-all" style="width: {{ $summary['completion_percent'] }}%"></div>
                    </div>
                </div>
            </div>

            {{-- Verifications --}}
            <div>
                <flux:heading size="lg" class="mb-4">{{ __('Verification') }}</flux:heading>
                <div class="grid items-stretch gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($verifications as $item)
                        <x-trust-profile.verification-card
                            :title="$item['title']"
                            :description="$item['description']"
                            :status="$item['status']"
                            :status-label="$item['status_label']"
                            :icon="$item['icon']"
                            :action-route="$item['action_route']"
                            :action-route-parameters="$item['action_route_parameters'] ?? []"
                            :action-label="$item['action_label']"
                            :wire-action="$item['wire_action'] ?? null"
                        />
                    @endforeach
                </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-5">
                {{-- Legal + eNOTARY --}}
                <div class="space-y-6 xl:col-span-3">
                    <div class="rounded-2xl border border-zinc-200/90 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900/40">
                        <flux:heading size="lg">{{ __('Legal identity') }}</flux:heading>
                        <flux:subheading class="mt-1 mb-4">{{ __('Used for compliance and notarized sessions') }}</flux:subheading>

                        <form wire:submit="updateLegalIdentity" class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <flux:textarea wire:model="address" label="{{ __('Address') }}" rows="2" required />
                                <flux:error name="address" />
                            </div>
                            <flux:input wire:model="nationality" label="{{ __('Nationality') }}" required />
                            <flux:input type="date" wire:model="date_of_birth" label="{{ __('Date of birth') }}" required />
                            <flux:select wire:model="government_id_type" label="{{ __('ID type') }}" placeholder="{{ __('Select…') }}" required>
                                <flux:select.option value="passport">{{ __('Passport') }}</flux:select.option>
                                <flux:select.option value="drivers_license">{{ __("Driver's license") }}</flux:select.option>
                                <flux:select.option value="national_id">{{ __('National ID') }}</flux:select.option>
                                <flux:select.option value="umid">{{ __('UMID') }}</flux:select.option>
                            </flux:select>
                            <flux:input wire:model="government_id_number" label="{{ __('ID number') }}" required />
                            <div class="sm:col-span-2">
                                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                            </div>
                        </form>
                    </div>

                    <div class="rounded-2xl border border-zinc-200/90 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900/40">
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <flux:heading size="lg">{{ __('eNOTARY readiness') }}</flux:heading>
                            <flux:badge :variant="$summary['enotary_ready'] ? 'success' : 'warning'" size="sm">
                                {{ $summary['enotary_ready'] ? __('Ready') : __(':done of :total', ['done' => $summary['enotary_ready_count'], 'total' => $summary['enotary_total']]) }}
                            </flux:badge>
                        </div>
                        <ul class="space-y-2">
                            @foreach ($enotaryChecks as $check)
                                <li class="flex items-center gap-2.5 text-sm">
                                    <flux:icon
                                        :name="$check['met'] ? 'check-circle' : 'x-circle'"
                                        class="size-4 shrink-0 {{ $check['met'] ? 'text-emerald-600' : 'text-zinc-300 dark:text-zinc-600' }}"
                                    />
                                    <span class="{{ $check['met'] ? 'text-zinc-800 dark:text-zinc-200' : 'text-zinc-500' }}">{{ $check['label'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                        @if (! $user->gps_permission_granted_at)
                            <flux:button variant="ghost" size="sm" class="mt-4" type="button" wire:click="grantGpsPermission">
                                {{ __('Allow location for remote notarization') }}
                            </flux:button>
                        @endif
                    </div>

                    @if (in_array($user->role, [\App\Enums\UserRole::Client, \App\Enums\UserRole::Notary], true))
                        <div class="rounded-2xl border border-teal-200/80 bg-teal-50/50 p-5 dark:border-teal-900/40 dark:bg-teal-950/20">
                            <flux:heading size="lg">{{ __('Attorney / Notary Public') }}</flux:heading>
                            <flux:subheading class="mt-1 mb-3">{{ __('Apply for e-Notary attorney access. A Notary Admin will review your commission and documents.') }}</flux:subheading>
                            <flux:button variant="primary" size="sm" :href="route('settings.attorney-application')" wire:navigate>
                                {{ __('Apply to practice as Attorney') }}
                            </flux:button>
                        </div>
                    @endif
                </div>

                {{-- Activity + sessions --}}
                <div class="space-y-6 xl:col-span-2">
                    <div class="rounded-2xl border border-zinc-200/90 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900/40">
                        <flux:heading size="lg">{{ __('Recent activity') }}</flux:heading>
                        <ol class="mt-4 space-y-3">
                            @forelse ($activity as $event)
                                <li class="border-l-2 border-teal-500/50 pl-3">
                                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $event['title'] }}</p>
                                    <p class="text-xs text-zinc-500">
                                        {{ \Illuminate\Support\Carbon::parse($event['occurred_at'])->diffForHumans() }}
                                    </p>
                                </li>
                            @empty
                                <li class="text-sm text-zinc-500">{{ __('No recent events') }}</li>
                            @endforelse
                        </ol>
                    </div>

                    <div class="rounded-2xl border border-zinc-200/90 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900/40">
                        <div class="mb-3 flex items-center justify-between">
                            <flux:heading size="lg">{{ __('Sessions') }}</flux:heading>
                            <flux:button variant="ghost" size="sm" :href="route('settings.profile', ['tab' => 'security'])" wire:navigate>{{ __('Security') }}</flux:button>
                        </div>
                        <div class="space-y-2">
                            @forelse ($sessions as $session)
                                <div class="rounded-lg border border-zinc-100 px-3 py-2 text-xs dark:border-zinc-800">
                                    <div class="flex justify-between gap-2 font-medium text-zinc-800 dark:text-zinc-200">
                                        <span>{{ $session['is_current'] ? __('This device') : __('Other') }}</span>
                                        <span class="text-zinc-400">{{ $session['last_activity'] }}</span>
                                    </div>
                                    <p class="mt-0.5 text-zinc-500">{{ $session['ip_address'] ?? '—' }}</p>
                                </div>
                            @empty
                                <p class="text-sm text-zinc-500">{{ __('No sessions') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            @if ($user->role === UserRole::Notary)
                <div id="notary-seal" class="scroll-mt-24 rounded-2xl border border-zinc-200/90 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900/40">
                    <flux:heading size="lg">{{ __('Notary personal seal') }}</flux:heading>
                    <flux:subheading class="mt-1 mb-4">
                        {{ __('Upload once here. The same seal is applied automatically on every case you notarize.') }}
                    </flux:subheading>

                    @if ($notaryCredential)
                        <form
                            method="POST"
                            action="{{ route('settings.trust-profile.seal.store') }}"
                            enctype="multipart/form-data"
                            data-seal-upload
                            class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,24rem)]"
                        >
                            @csrf
                            <div class="rounded-2xl border border-dashed border-zinc-300 bg-zinc-50/70 p-5 transition dark:border-zinc-700 dark:bg-zinc-950/30">
                                <div class="flex items-start gap-3">
                                    <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-teal-100 text-teal-700 dark:bg-teal-500/15 dark:text-teal-300">
                                        <flux:icon name="cloud-arrow-up" class="size-5" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Upload your official notary seal') }}</p>
                                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                                            {{ __('Choose a clear PNG or JPG. This seal will be applied automatically on every case you notarize.') }}
                                        </p>
                                    </div>
                                </div>

                                <label class="mt-5 flex cursor-pointer flex-col items-center justify-center rounded-2xl border border-zinc-200 bg-white px-4 py-6 text-center shadow-sm transition hover:border-teal-300 hover:bg-teal-50/50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-teal-500/50 dark:hover:bg-teal-500/10">
                                    <input
                                        type="file"
                                        name="notary_seal_upload"
                                        accept="image/png,image/jpeg"
                                        class="sr-only"
                                        onchange="(function(input){const root=input.closest('[data-seal-upload]');const file=input.files&&input.files[0];const selected=root&&root.querySelector('[data-seal-selected]');const name=root&&root.querySelector('[data-seal-file-name]');const preview=root&&root.querySelector('[data-seal-preview]');const empty=root&&root.querySelector('[data-seal-empty]');const image=root&&root.querySelector('[data-seal-preview-image]');if(name){name.textContent=file?file.name:'';}if(selected){selected.classList.toggle('hidden',!file);}if(!file||!preview||!empty||!image){return;}const reader=new FileReader();reader.onload=function(event){image.src=event.target.result;preview.classList.remove('hidden');empty.classList.add('hidden');};reader.readAsDataURL(file);})(this)"
                                    />
                                    <span class="text-sm font-semibold text-teal-700 dark:text-teal-300">{{ $hasNotarySeal ? __('Choose replacement seal') : __('Choose seal image') }}</span>
                                    <span class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('PNG or JPG, max 2MB') }}</span>
                                </label>

                                @error('notary_seal_upload')
                                    <p class="mt-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror

                                <p data-seal-selected class="mt-3 hidden truncate text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ __('Selected:') }} <span data-seal-file-name></span>
                                </p>

                                <flux:button variant="primary" size="sm" type="submit" class="mt-4 w-full sm:w-auto">
                                    {{ $hasNotarySeal ? __('Replace seal') : __('Save seal') }}
                                </flux:button>
                            </div>

                            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-950/40">
                                <div data-seal-preview class="hidden">
                                    <div class="mb-4 flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-teal-950 dark:text-teal-100">{{ __('Preview selected seal') }}</p>
                                            <p class="mt-1 text-xs text-teal-800 dark:text-teal-200">{{ __('Review before saving.') }}</p>
                                        </div>
                                        <flux:badge color="emerald" size="sm">{{ __('Ready') }}</flux:badge>
                                    </div>
                                    <div class="flex min-h-44 items-center justify-center rounded-2xl border border-teal-200 bg-teal-50/70 p-4 dark:border-teal-500/30 dark:bg-teal-500/10">
                                        <img
                                            data-seal-preview-image
                                            src=""
                                            alt="{{ __('Selected notary seal preview') }}"
                                            class="max-h-40 max-w-full rounded-xl border border-teal-200 bg-white p-3 shadow-sm dark:border-teal-500/30 dark:bg-zinc-950"
                                        />
                                    </div>
                                </div>

                                <div data-seal-empty>
                                    @if ($hasNotarySeal)
                                        <div class="mb-4 flex items-center justify-between gap-3">
                                            <div>
                                                <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Current seal') }}</p>
                                                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('This seal is already on file.') }}</p>
                                            </div>
                                            <flux:badge color="emerald" size="sm">{{ __('Seal on file') }}</flux:badge>
                                        </div>
                                        <div class="flex min-h-44 items-center justify-center rounded-2xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900/60">
                                            <img
                                                src="{{ route('settings.trust-profile.seal') }}"
                                                alt=""
                                                class="max-h-40 max-w-full rounded-xl border border-zinc-200 bg-white p-3 shadow-sm dark:border-zinc-700 dark:bg-zinc-950"
                                            />
                                        </div>
                                    @else
                                        <div class="flex min-h-44 flex-col items-center justify-center rounded-2xl border border-amber-200/80 bg-amber-50/80 p-5 text-center text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                                            <flux:icon name="shield-check" class="size-8" />
                                            <p class="mt-3 text-sm font-semibold">{{ __('No seal uploaded yet') }}</p>
                                            <p class="mt-1 text-xs">{{ __('Add your official notary seal before creating register entries or digitalizing documents.') }}</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </form>
                    @else
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300">
                            {{ __('Complete attorney application and receive approval before uploading your seal.') }}
                        </div>
                        <flux:button variant="primary" size="sm" class="mt-4" :href="route('settings.attorney-application')" wire:navigate>
                            {{ __('Apply to practice as Attorney') }}
                        </flux:button>
                    @endif
                </div>
            @endif

            {{-- Signatures --}}
            <div class="rounded-2xl border border-zinc-200/90 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900/40">
                <flux:heading size="lg">{{ __('Signature & initials') }}</flux:heading>
                <div class="mt-4 grid gap-6 lg:grid-cols-2">
                    <div
                        x-data="{
                            canvas: null,
                            ctx: null,
                            drawing: false,
                            lastPoint: null,
                            init() {
                                this.canvas = this.$refs.pad;
                                this.ctx = this.canvas.getContext('2d');
                                this.resizeCanvas();
                                window.addEventListener('resize', () => this.resizeCanvas());
                            },
                            resizeCanvas() {
                                const rect = this.canvas.getBoundingClientRect();
                                const ratio = window.devicePixelRatio || 1;
                                this.canvas.width = Math.max(1, Math.round(rect.width * ratio));
                                this.canvas.height = Math.max(1, Math.round(rect.height * ratio));
                                this.ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
                                this.configurePen();
                            },
                            configurePen() {
                                this.ctx.strokeStyle = '#0f172a';
                                this.ctx.lineWidth = 1.6;
                                this.ctx.lineCap = 'round';
                                this.ctx.lineJoin = 'round';
                            },
                            point(e) {
                                const rect = this.canvas.getBoundingClientRect();

                                return {
                                    x: e.clientX - rect.left,
                                    y: e.clientY - rect.top,
                                };
                            },
                            start(e) {
                                e.preventDefault();
                                this.drawing = true;
                                this.canvas.setPointerCapture?.(e.pointerId);
                                const point = this.point(e);
                                this.lastPoint = point;
                                this.ctx.beginPath();
                                this.ctx.moveTo(point.x, point.y);
                            },
                            draw(e) {
                                if (!this.drawing) return;
                                e.preventDefault();
                                const point = this.point(e);
                                const midPoint = {
                                    x: (this.lastPoint.x + point.x) / 2,
                                    y: (this.lastPoint.y + point.y) / 2,
                                };
                                this.ctx.quadraticCurveTo(this.lastPoint.x, this.lastPoint.y, midPoint.x, midPoint.y);
                                this.ctx.stroke();
                                this.lastPoint = point;
                            },
                            end(e) {
                                if (!this.drawing) return;
                                this.drawing = false;
                                this.lastPoint = null;
                                this.canvas.releasePointerCapture?.(e.pointerId);
                            },
                            clear() {
                                this.ctx.save();
                                this.ctx.setTransform(1, 0, 0, 1, 0, 0);
                                this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                                this.ctx.restore();
                                this.configurePen();
                            },
                            save() {
                                $wire.set('drawnSignature', this.canvas.toDataURL('image/png'));
                                $wire.call('saveDrawnSignature');
                            }
                        }"
                        class="rounded-xl border border-dashed border-zinc-200 p-4 dark:border-zinc-700"
                    >
                        <p class="mb-2 text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Draw') }}</p>
                        <canvas
                            x-ref="pad"
                            class="h-44 w-full touch-none rounded-lg bg-white dark:bg-zinc-950"
                            @pointerdown="start($event)"
                            @pointermove="draw($event)"
                            @pointerup="end($event)"
                            @pointercancel="end($event)"
                            @pointerleave="end($event)"
                        ></canvas>
                        <div class="mt-2 flex gap-2">
                            <flux:button variant="ghost" size="sm" type="button" @click="clear()">{{ __('Clear') }}</flux:button>
                            <flux:button variant="primary" size="sm" type="button" @click="save()">{{ __('Save') }}</flux:button>
                        </div>
                    </div>

                    <div class="space-y-4">
                        @if ($user->signature_image_path)
                            <img src="{{ route('settings.trust-profile.signature') }}" alt="" class="max-h-20 dark:invert" />
                        @endif
                        <input type="file" wire:model="signatureUpload" accept="image/*" class="w-full text-sm" />
                        <flux:error name="signatureUpload" />
                        @if ($signatureUpload)
                            <flux:button variant="primary" size="sm" type="button" wire:click="saveUploadedSignature">{{ __('Upload') }}</flux:button>
                        @endif
                        <form wire:submit="saveInitials" class="flex gap-2">
                            <flux:input wire:model="signature_initials" label="{{ __('Initials') }}" maxlength="10" class="flex-1" />
                            <flux:button variant="ghost" type="submit" class="self-end">{{ __('Save') }}</flux:button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </x-settings.trust-layout>
</section>
