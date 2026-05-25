<?php

use App\Models\BillingProfile;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public ?BillingProfile $billingProfile = null;
    public string $registeredName = '';
    public string $tin = '';
    public string $branchCode = '';
    public string $email = '';
    public string $phone = '';
    public string $addressLine = '';
    public string $city = '';
    public string $state = '';
    public string $postalCode = '';
    public string $countryCode = 'PH';
    public string $eisEnvironment = 'sandbox';
    public string $eisAccreditationId = '';
    public string $eisApplicationId = '';
    public string $eisUsername = '';
    public string $eisPassword = '';
    public string $eisCertificateId = '';
    public bool $isActive = true;

    public function mount(): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);
        abort_unless($user->organization_id !== null, 403, 'A billing profile requires an organization.');

        $this->billingProfile = BillingProfile::query()
            ->where('organization_id', $user->organization_id)
            ->latest('id')
            ->first();

        if (! $this->billingProfile instanceof BillingProfile) {
            return;
        }

        $this->registeredName = (string) $this->billingProfile->registered_name;
        $this->tin = (string) $this->billingProfile->tin;
        $this->branchCode = (string) $this->billingProfile->branch_code;
        $this->email = (string) $this->billingProfile->email;
        $this->phone = (string) $this->billingProfile->phone;
        $this->addressLine = (string) $this->billingProfile->address_line;
        $this->city = (string) $this->billingProfile->city;
        $this->state = (string) $this->billingProfile->state;
        $this->postalCode = (string) $this->billingProfile->postal_code;
        $this->countryCode = (string) ($this->billingProfile->country_code ?: 'PH');
        $this->eisEnvironment = (string) ($this->billingProfile->eis_environment ?: 'sandbox');
        $this->eisAccreditationId = (string) $this->billingProfile->eis_accreditation_id;
        $this->eisApplicationId = (string) $this->billingProfile->eis_application_id;
        $this->eisUsername = (string) $this->billingProfile->eis_username;
        $this->eisCertificateId = (string) $this->billingProfile->eis_certificate_id;
        $this->isActive = (bool) $this->billingProfile->is_active;
    }

    public function save(): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);
        abort_unless($user->organization_id !== null, 403, 'A billing profile requires an organization.');

        $validated = $this->validate([
            'registeredName' => ['required', 'string', 'max:255'],
            'tin' => ['required', 'string', 'max:64'],
            'branchCode' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'addressLine' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:128'],
            'state' => ['nullable', 'string', 'max:128'],
            'postalCode' => ['nullable', 'string', 'max:32'],
            'countryCode' => ['required', 'string', 'size:2'],
            'eisEnvironment' => ['required', 'string', 'max:32'],
            'eisAccreditationId' => ['required', 'string', 'max:255'],
            'eisApplicationId' => ['required', 'string', 'max:255'],
            'eisUsername' => ['required', 'string', 'max:255'],
            'eisPassword' => [$this->billingProfile instanceof BillingProfile ? 'nullable' : 'required', 'string', 'max:255'],
            'eisCertificateId' => ['required', 'string', 'max:255'],
            'isActive' => ['boolean'],
        ]);

        $payload = [
            'organization_id' => $user->organization_id,
            'registered_name' => trim($validated['registeredName']),
            'tin' => trim($validated['tin']),
            'branch_code' => trim($validated['branchCode']),
            'email' => trim((string) $validated['email']) !== '' ? trim((string) $validated['email']) : null,
            'phone' => trim((string) $validated['phone']) !== '' ? trim((string) $validated['phone']) : null,
            'address_line' => trim((string) $validated['addressLine']) !== '' ? trim((string) $validated['addressLine']) : null,
            'city' => trim((string) $validated['city']) !== '' ? trim((string) $validated['city']) : null,
            'state' => trim((string) $validated['state']) !== '' ? trim((string) $validated['state']) : null,
            'postal_code' => trim((string) $validated['postalCode']) !== '' ? trim((string) $validated['postalCode']) : null,
            'country_code' => strtoupper(trim($validated['countryCode'])),
            'eis_environment' => trim($validated['eisEnvironment']),
            'eis_accreditation_id' => trim($validated['eisAccreditationId']),
            'eis_application_id' => trim($validated['eisApplicationId']),
            'eis_username' => trim($validated['eisUsername']),
            'eis_certificate_id' => trim($validated['eisCertificateId']),
            'is_active' => (bool) $validated['isActive'],
        ];

        if (trim((string) $validated['eisPassword']) !== '') {
            $payload['eis_password'] = trim($validated['eisPassword']);
        }

        if ($this->billingProfile instanceof BillingProfile) {
            $this->billingProfile->fill($payload)->save();
            $this->billingProfile->refresh();
        } else {
            $this->billingProfile = BillingProfile::query()->create($payload);
        }

        $this->eisPassword = '';

        session()->flash('status', __('Billing profile saved.'));
    }

    public function with(): array
    {
        return [
            'hasProfile' => $this->billingProfile instanceof BillingProfile,
        ];
    }
}; ?>

<div class="mx-auto flex w-full max-w-5xl min-w-0 flex-col gap-6 px-2 py-4 sm:px-4 lg:px-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ __('Billing Profile') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Configure the seller profile and EIS credentials used for e-invoice submission for your organization.') }}</p>
        </div>
        <div class="text-sm font-medium text-zinc-400 dark:text-zinc-500">
            {{ $hasProfile ? __('Profile on file') : __('Profile not configured') }}
        </div>
    </div>

    <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
        <div class="grid gap-6 lg:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('Registered seller name') }}</flux:label>
                <flux:input wire:model="registeredName" type="text" />
                <flux:error name="registeredName" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Seller TIN') }}</flux:label>
                <flux:input wire:model="tin" type="text" />
                <flux:error name="tin" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Branch code') }}</flux:label>
                <flux:input wire:model="branchCode" type="text" />
                <flux:error name="branchCode" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Billing email') }}</flux:label>
                <flux:input wire:model="email" type="email" />
                <flux:error name="email" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Phone') }}</flux:label>
                <flux:input wire:model="phone" type="text" />
                <flux:error name="phone" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Address line') }}</flux:label>
                <flux:input wire:model="addressLine" type="text" />
                <flux:error name="addressLine" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('City') }}</flux:label>
                <flux:input wire:model="city" type="text" />
                <flux:error name="city" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('State / Province') }}</flux:label>
                <flux:input wire:model="state" type="text" />
                <flux:error name="state" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Postal code') }}</flux:label>
                <flux:input wire:model="postalCode" type="text" />
                <flux:error name="postalCode" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Country code') }}</flux:label>
                <flux:input wire:model="countryCode" type="text" maxlength="2" />
                <flux:error name="countryCode" />
            </flux:field>
        </div>

        <div class="mt-8 border-t border-zinc-200 pt-6 dark:border-zinc-800">
            <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ __('EIS Credentials') }}</h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('These values are used when DocuTrust authenticates and submits invoices to BIR EIS.') }}</p>

            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Environment') }}</flux:label>
                    <select wire:model="eisEnvironment" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                        <option value="sandbox">{{ __('Sandbox') }}</option>
                        <option value="production">{{ __('Production') }}</option>
                    </select>
                    <flux:error name="eisEnvironment" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('EIS accreditation ID') }}</flux:label>
                    <flux:input wire:model="eisAccreditationId" type="text" />
                    <flux:error name="eisAccreditationId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('EIS application ID') }}</flux:label>
                    <flux:input wire:model="eisApplicationId" type="text" />
                    <flux:error name="eisApplicationId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('EIS username') }}</flux:label>
                    <flux:input wire:model="eisUsername" type="text" />
                    <flux:error name="eisUsername" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ $hasProfile ? __('EIS password (leave blank to keep current)') : __('EIS password') }}</flux:label>
                    <flux:input wire:model="eisPassword" type="password" />
                    <flux:error name="eisPassword" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('EIS certificate ID') }}</flux:label>
                    <flux:input wire:model="eisCertificateId" type="text" />
                    <flux:error name="eisCertificateId" />
                </flux:field>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <input wire:model="isActive" id="billing-profile-active" type="checkbox" class="h-4 w-4 rounded border-zinc-300 text-teal-600 focus:ring-teal-500">
                <label for="billing-profile-active" class="text-sm text-zinc-700 dark:text-zinc-300">{{ __('Use this profile for automatic EIS queueing and submission') }}</label>
            </div>
        </div>

        <div class="mt-8 flex items-center justify-between border-t border-zinc-200 pt-6 dark:border-zinc-800">
            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Saving this profile will let paid invoices move past "needs_correction" once you retry queueing or the next invoice is created.') }}
            </div>
            <flux:button variant="primary" type="button" wire:click="save">{{ __('Save billing profile') }}</flux:button>
        </div>
    </div>
</div>
