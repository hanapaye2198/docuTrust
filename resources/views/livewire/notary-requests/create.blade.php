<?php

use App\Models\NotaryRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public string $title = '';
    public string $requestType = 'acknowledgment';
    public string $notaryUserId = '';
    public string $notes = '';

    public function with(): array
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        return [
            'isNotaryView' => $user->role->value === 'notary',
            'availableNotaries' => User::query()
                ->where('organization_id', $user->organization_id)
                ->where('role', 'notary')
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }

    public function save(): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'requestType' => ['required', 'string', 'max:64'],
            'notaryUserId' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $request = $user->notaryRequests()->create([
            'title' => trim($validated['title']),
            'request_type' => trim($validated['requestType']),
            'notary_user_id' => $validated['notaryUserId'] !== '' ? (int) $validated['notaryUserId'] : null,
            'metadata' => [
                'notes' => trim((string) $validated['notes']),
                'created_from' => 'manual_form',
            ],
        ]);

        session()->flash('status', __('Request created. Next: attach a document, assign workflow details, or submit it for review.'));
        $this->redirect(route($user->role->value === 'notary' ? 'notary.requests.show' : 'notary-requests.show', $request, absolute: false), navigate: true);
    }
}; ?>

<div class="flex w-full min-w-0 flex-col gap-6 p-1">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50 sm:text-3xl">{{ __('Create notary request') }}</h1>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Open a remote notarization case before assigning documents, sessions, and final attorney review.') }}</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="text-xs font-semibold uppercase tracking-widest text-zinc-400 dark:text-zinc-500">{{ __('1. Case shell') }}</div>
            <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">{{ __('Start with the matter name and notarization type. You are creating the case record, not the full document package yet.') }}</p>
        </div>
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="text-xs font-semibold uppercase tracking-widest text-zinc-400 dark:text-zinc-500">{{ __('2. Assignment') }}</div>
            <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">{{ __('Assign a notary now if ownership is clear. Otherwise leave it open and route it later from the request queue.') }}</p>
        </div>
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="text-xs font-semibold uppercase tracking-widest text-zinc-400 dark:text-zinc-500">{{ __('3. Next step') }}</div>
            <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">{{ __('After creation, attach documents, prepare signer workflow, and move the case into scheduling and attorney review.') }}</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div class="p-6 sm:p-8">
            <form wire:submit="save" class="space-y-6">
                <flux:field>
                    <flux:label>{{ __('Request title') }}</flux:label>
                    <flux:input wire:model="title" type="text" required placeholder="{{ __('e.g. SPA acknowledgment for client signing') }}" />
                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Use the client or matter name plus the act being notarized so the queue remains easy to scan.') }}</p>
                    <flux:error name="title" />
                </flux:field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Request type') }}</flux:label>
                        <select wire:model="requestType" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                            <option value="acknowledgment">{{ __('Acknowledgment') }}</option>
                            <option value="jurat">{{ __('Jurat') }}</option>
                            <option value="affidavit">{{ __('Affidavit') }}</option>
                            <option value="oath">{{ __('Oath') }}</option>
                        </select>
                        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Pick the legal act that best matches the case. This helps the team sort and review the request correctly.') }}</p>
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
                        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Choose a notary now when workload or specialization is already known. Otherwise leave it unassigned.') }}</p>
                        <flux:error name="notaryUserId" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>{{ __('Internal notes') }}</flux:label>
                    <flux:textarea wire:model="notes" rows="5" placeholder="{{ __('Context for the notary team, signer expectations, or special handling notes.') }}" />
                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Capture anything that will matter during intake: signer availability, urgency, identity concerns, or document handling instructions.') }}</p>
                    <flux:error name="notes" />
                </flux:field>

                <div class="flex items-center gap-3">
                    <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">{{ __('Create request') }}</span>
                        <span wire:loading wire:target="save">{{ __('Creating…') }}</span>
                    </flux:button>
                    <flux:button variant="ghost" :href="$isNotaryView ? route('notary.requests.index') : route('notary-requests.index')" wire:navigate type="button">{{ __('Cancel') }}</flux:button>
                </div>
            </form>
        </div>
    </div>
</div>
