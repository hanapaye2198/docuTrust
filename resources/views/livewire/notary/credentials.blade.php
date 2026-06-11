<?php

use App\Enums\UserRole;
use App\Models\NotaryCredential;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.app')] class extends Component {
    use WithFileUploads;

    public string $commissionNumber = '';
    public string $commissionJurisdiction = 'Philippines';
    public string $commissionIssuedAt = '';
    public string $commissionExpiresAt = '';
    public string $rollNumber = '';
    public string $ibpNumber = '';
    public string $ptrNumber = '';
    public string $mcleComplianceNumber = '';
    public $signatureImage = null;

    public function save(): void
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->role === UserRole::Notary, 403);

        $validated = $this->validate([
            'commissionNumber' => ['required', 'string', 'max:100'],
            'commissionJurisdiction' => ['required', 'string', 'max:255'],
            'commissionIssuedAt' => ['required', 'date'],
            'commissionExpiresAt' => ['required', 'date', 'after:commissionIssuedAt'],
            'rollNumber' => ['nullable', 'string', 'max:100'],
            'ibpNumber' => ['nullable', 'string', 'max:100'],
            'ptrNumber' => ['nullable', 'string', 'max:100'],
            'mcleComplianceNumber' => ['nullable', 'string', 'max:100'],
            'signatureImage' => ['nullable', 'image', 'max:2048'],
        ]);

        $signaturePath = null;
        if ($this->signatureImage) {
            $signaturePath = $this->signatureImage->store('notary/signatures', (string) config('filesystems.docutrust_disk', 'local'));
        }

        $existing = NotaryCredential::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        abort_unless($existing !== null && $existing->isActive(), 403, __('Only approved attorneys can update credentials here. Submit changes via renewal if your commission changed.'));

        $credential = NotaryCredential::query()->updateOrCreate(
            ['user_id' => $user->id, 'commission_number' => trim($validated['commissionNumber'])],
            [
                'commission_jurisdiction' => trim($validated['commissionJurisdiction']),
                'commission_issued_at' => $validated['commissionIssuedAt'],
                'commission_expires_at' => $validated['commissionExpiresAt'],
                'roll_number' => trim((string) $validated['rollNumber']) !== '' ? trim($validated['rollNumber']) : null,
                'ibp_number' => trim((string) $validated['ibpNumber']) !== '' ? trim($validated['ibpNumber']) : null,
                'ptr_number' => trim((string) $validated['ptrNumber']) !== '' ? trim($validated['ptrNumber']) : null,
                'mcle_compliance_number' => trim((string) $validated['mcleComplianceNumber']) !== '' ? trim($validated['mcleComplianceNumber']) : null,
                'seal_image_path' => $existing->seal_image_path ?? null,
                'signature_image_path' => $signaturePath ?? $existing->signature_image_path ?? null,
                'status' => \App\Enums\NotaryCredentialStatus::Active->value,
            ]
        );

        session()->flash('status', __('Notary credentials saved.'));
        $this->reset(['signatureImage']);
    }

    public function mount(): void
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->role === UserRole::Notary, 403);

        $credential = NotaryCredential::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        if ($credential === null || ! $credential->isActive()) {
            $this->redirect(route('settings.attorney-application'), navigate: true);
        }
    }

    public function with(): array
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->role === UserRole::Notary, 403);

        $credential = NotaryCredential::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        if ($credential !== null) {
            $this->commissionNumber = $this->commissionNumber ?: $credential->commission_number;
            $this->commissionJurisdiction = $this->commissionJurisdiction ?: $credential->commission_jurisdiction;
            $this->commissionIssuedAt = $this->commissionIssuedAt ?: $credential->commission_issued_at?->format('Y-m-d');
            $this->commissionExpiresAt = $this->commissionExpiresAt ?: $credential->commission_expires_at?->format('Y-m-d');
            $this->rollNumber = $this->rollNumber ?: ($credential->roll_number ?? '');
            $this->ibpNumber = $this->ibpNumber ?: ($credential->ibp_number ?? '');
            $this->ptrNumber = $this->ptrNumber ?: ($credential->ptr_number ?? '');
            $this->mcleComplianceNumber = $this->mcleComplianceNumber ?: ($credential->mcle_compliance_number ?? '');
        }

        return [
            'credential' => $credential,
        ];
    }
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <header>
        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50 sm:text-3xl">{{ __('Notary credentials') }}</h1>
        <p class="mt-2 text-zinc-600 dark:text-zinc-400">
            {{ __('Manage your notary commission details and registered signature for digital notarization.') }}
        </p>
    </header>

    @if (session('status'))
        <div class="rounded-2xl border border-emerald-200/90 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-100">
            {{ session('status') }}
        </div>
    @endif

    @if ($credential && $credential->isExpired())
        <div class="rounded-2xl border border-red-200/90 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-100">
            {{ __('Your notary commission has expired. Please update your credentials.') }}
        </div>
    @endif

    <div class="ui-panel p-6 sm:p-8">
        <form wire:submit="save" class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Commission number') }}</flux:label>
                    <flux:input wire:model="commissionNumber" type="text" required placeholder="CN-2024-0001" />
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
                    <flux:error name="rollNumber" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('IBP number') }}</flux:label>
                    <flux:input wire:model="ibpNumber" type="text" />
                    <flux:error name="ibpNumber" />
                </flux:field>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('PTR number') }}</flux:label>
                    <flux:input wire:model="ptrNumber" type="text" />
                    <flux:error name="ptrNumber" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('MCLE compliance number') }}</flux:label>
                    <flux:input wire:model="mcleComplianceNumber" type="text" />
                    <flux:error name="mcleComplianceNumber" />
                </flux:field>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300">
                <p class="font-medium text-zinc-800 dark:text-zinc-200">{{ __('Notary personal seal') }}</p>
                <p class="mt-1">
                    @if ($credential?->seal_image_path)
                        {{ __('Your seal is on file and managed in trust profile.') }}
                    @else
                        {{ __('Upload your personal seal in trust profile. It is reused on every case.') }}
                    @endif
                </p>
                <flux:button variant="ghost" size="sm" class="mt-3" :href="route('settings.trust-profile').'#notary-seal'" wire:navigate>
                    {{ __('Open trust profile') }}
                </flux:button>
            </div>

            <flux:field>
                <flux:label>{{ __('Registered signature') }}</flux:label>
                <input type="file" wire:model="signatureImage" accept="image/*" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100" />
                <p class="mt-1 text-xs text-zinc-500">{{ __('Upload your registered signature (PNG or JPG, max 2MB)') }}</p>
                @if ($credential?->signature_image_path)
                    <p class="mt-1 text-xs text-emerald-600 dark:text-emerald-400">{{ __('Signature image on file ✓') }}</p>
                @endif
                <flux:error name="signatureImage" />
            </flux:field>

            <div class="flex items-center gap-3">
                <flux:button variant="primary" type="submit">{{ __('Save credentials') }}</flux:button>
            </div>
        </form>
    </div>
</div>
