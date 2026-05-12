<?php

use App\Enums\UserRole;
use App\Http\Requests\StoreNotaryClientCaseRequest;
use App\Models\NotarySigner;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.app')] class extends Component {
    use WithFileUploads;

    public string $title = '';

    public string $requestType = 'acknowledgment';

    public string $notaryUserId = '';

    public string $remarks = '';

    public $caseDocument = null;

    /**
     * @var list<array{full_name: string, email: string, phone: string, address: string, role: string}>
     */
    public array $signers = [];

    public function mount(): void
    {
        $this->signers = [
            [
                'full_name' => '',
                'email' => '',
                'phone' => '',
                'address' => '',
                'role' => 'signer',
            ],
        ];
    }

    public function addSignerRow(): void
    {
        $this->signers[] = [
            'full_name' => '',
            'email' => '',
            'phone' => '',
            'address' => '',
            'role' => 'signer',
        ];
    }

    public function removeSignerRow(int $index): void
    {
        if (count($this->signers) <= 1) {
            return;
        }

        unset($this->signers[$index]);
        $this->signers = array_values($this->signers);
    }

    public function with(): array
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        return [
            'isNotaryView' => $user->role === UserRole::Notary,
            'availableNotaries' => User::query()
                ->where('organization_id', $user->organization_id)
                ->where('role', UserRole::Notary)
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }

    public function save(): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        $rules = StoreNotaryClientCaseRequest::rules($user);
        $notaryUserId = null;

        if (trim((string) $this->notaryUserId) === '') {
            unset($rules['notaryUserId']);
            $validated = $this->validate($rules);
        } else {
            $validated = $this->validate($rules);
            $notaryUserId = (int) $validated['notaryUserId'];
        }

        $documentPath = null;
        if ($this->caseDocument !== null) {
            $documentPath = $this->caseDocument->store('notary-requests', (string) config('filesystems.docutrust_disk', 'local'));
        }

        $request = $user->notaryRequests()->create([
            'title' => trim($validated['title']),
            'request_type' => trim($validated['requestType']),
            'notary_user_id' => $notaryUserId,
            'document_path' => $documentPath,
            'remarks' => trim((string) ($validated['remarks'] ?? '')) !== '' ? trim((string) $validated['remarks']) : null,
            'metadata' => [
                'created_from' => 'enotary_wizard',
            ],
        ]);

        foreach ($validated['signers'] as $signerRow) {
            NotarySigner::query()->create([
                'notary_request_id' => $request->id,
                'full_name' => trim((string) $signerRow['full_name']),
                'email' => strtolower(trim((string) $signerRow['email'])),
                'phone' => trim((string) $signerRow['phone']) !== '' ? trim((string) $signerRow['phone']) : null,
                'address' => trim((string) $signerRow['address']) !== '' ? trim((string) $signerRow['address']) : null,
                'role' => trim((string) $signerRow['role']) !== '' ? trim((string) $signerRow['role']) : 'signer',
            ]);
        }

        session()->flash('status', __('eNOTARY case created. Upload identity documents on the request page, then submit for verification.'));

        $this->redirect(route($user->role === UserRole::Notary ? 'notary.requests.show' : 'notary-requests.show', $request, absolute: false), navigate: true);
    }
}; ?>

<div class="flex w-full min-w-0 flex-col gap-6 p-1">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50 sm:text-3xl">{{ __('Create eNOTARY request') }}</h1>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Upload the instrument, choose the notarial act, add every signer, and open a trackable case.') }}</p>
    </div>

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div class="p-6 sm:p-8">
            <form wire:submit="save" class="space-y-8">
                <flux:field>
                    <flux:label>{{ __('Case title') }}</flux:label>
                    <flux:input wire:model="title" type="text" required placeholder="{{ __('Matter name and document type') }}" />
                    <flux:error name="title" />
                </flux:field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Notarial act') }}</flux:label>
                        <select wire:model="requestType" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                            @foreach (config('docutrust.notary.notarial_act_types', []) as $act)
                                <option value="{{ $act }}">{{ __(ucfirst(str_replace('_', ' ', $act))) }}</option>
                            @endforeach
                        </select>
                        <flux:error name="requestType" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Assign notary') }}</flux:label>
                        <select wire:model="notaryUserId" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                            <option value="">{{ __('Select later') }}</option>
                            @foreach ($availableNotaries as $notary)
                                <option value="{{ $notary->id }}">{{ $notary->name }}</option>
                            @endforeach
                        </select>
                        <flux:error name="notaryUserId" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>{{ __('Primary document (PDF)') }}</flux:label>
                    <input
                        type="file"
                        wire:model="caseDocument"
                        accept="application/pdf,.pdf"
                        class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                    />
                    <div wire:loading wire:target="caseDocument" class="mt-2 text-xs text-teal-600 dark:text-teal-400">{{ __('Reading file…') }}</div>
                    <flux:error name="caseDocument" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Remarks') }}</flux:label>
                    <flux:textarea wire:model="remarks" rows="3" placeholder="{{ __('Optional context for reviewers.') }}" />
                    <flux:error name="remarks" />
                </flux:field>

                <div class="space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Signers') }}</h2>
                        <flux:button type="button" variant="outline" size="sm" wire:click="addSignerRow">{{ __('Add signer') }}</flux:button>
                    </div>
                    @foreach ($signers as $index => $signer)
                        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" wire:key="signer-{{ $index }}">
                            <div class="mb-3 flex items-center justify-between">
                                <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Signer') }} {{ $index + 1 }}</span>
                                @if (count($signers) > 1)
                                    <flux:button type="button" variant="ghost" size="sm" wire:click="removeSignerRow({{ $index }})">{{ __('Remove') }}</flux:button>
                                @endif
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <flux:field class="sm:col-span-2">
                                    <flux:label>{{ __('Full name') }}</flux:label>
                                    <flux:input type="text" wire:model="signers.{{ $index }}.full_name" required />
                                    <flux:error name="signers.{{ $index }}.full_name" />
                                </flux:field>
                                <flux:field>
                                    <flux:label>{{ __('Email') }}</flux:label>
                                    <flux:input type="email" wire:model="signers.{{ $index }}.email" required />
                                    <flux:error name="signers.{{ $index }}.email" />
                                </flux:field>
                                <flux:field>
                                    <flux:label>{{ __('Phone') }}</flux:label>
                                    <flux:input type="text" wire:model="signers.{{ $index }}.phone" />
                                    <flux:error name="signers.{{ $index }}.phone" />
                                </flux:field>
                                <flux:field class="sm:col-span-2">
                                    <flux:label>{{ __('Address') }}</flux:label>
                                    <flux:input type="text" wire:model="signers.{{ $index }}.address" />
                                    <flux:error name="signers.{{ $index }}.address" />
                                </flux:field>
                                <flux:field>
                                    <flux:label>{{ __('Role') }}</flux:label>
                                    <flux:input type="text" wire:model="signers.{{ $index }}.role" />
                                    <flux:error name="signers.{{ $index }}.role" />
                                </flux:field>
                            </div>
                        </div>
                    @endforeach
                    <flux:error name="signers" />
                </div>

                <div class="flex items-center gap-3">
                    <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="save,caseDocument">
                        <span wire:loading.remove wire:target="save">{{ __('Create request') }}</span>
                        <span wire:loading wire:target="save">{{ __('Creating…') }}</span>
                    </flux:button>
                    <flux:button variant="ghost" :href="$isNotaryView ? route('notary.requests.index') : route('notary-requests.index')" wire:navigate type="button">{{ __('Cancel') }}</flux:button>
                </div>
            </form>
        </div>
    </div>
</div>
