<?php

use App\Http\Requests\ContactRequest;
use App\Models\Contact;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public string $searchInput = '';

    public string $search = '';

    public bool $showContactModal = false;

    public ?int $editingContactId = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $company = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Contact::class);
    }

    public function applySearch(): void
    {
        $this->search = trim($this->searchInput);
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', Contact::class);
        $this->editingContactId = null;
        $this->name = '';
        $this->email = '';
        $this->phone = '';
        $this->company = '';
        $this->resetValidation();
        $this->showContactModal = true;
    }

    public function openEditModal(int $contactId): void
    {
        $contact = Contact::query()->where('user_id', auth()->id())->findOrFail($contactId);
        $this->authorize('update', $contact);
        $this->editingContactId = $contact->id;
        $this->name = $contact->name;
        $this->email = $contact->email;
        $this->phone = $contact->phone ?? '';
        $this->company = $contact->company ?? '';
        $this->resetValidation();
        $this->showContactModal = true;
    }

    public function saveContact(): void
    {
        $validated = $this->validate(ContactRequest::rulesForUser($this->editingContactId));
        $validated['email'] = strtolower($validated['email']);
        $validated['phone'] = isset($validated['phone']) && $validated['phone'] !== '' ? $validated['phone'] : null;
        $validated['company'] = isset($validated['company']) && $validated['company'] !== '' ? $validated['company'] : null;

        if ($this->editingContactId) {
            $contact = Contact::query()->where('user_id', auth()->id())->findOrFail($this->editingContactId);
            $this->authorize('update', $contact);
            $contact->update($validated);
        } else {
            $this->authorize('create', Contact::class);
            Contact::query()->create([
                ...$validated,
                'user_id' => auth()->id(),
            ]);
        }

        $this->showContactModal = false;
    }

    public function deleteContact(int $contactId): void
    {
        $contact = Contact::query()->where('user_id', auth()->id())->findOrFail($contactId);
        $this->authorize('delete', $contact);
        $contact->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $userId = auth()->id();

        $hasContacts = Contact::query()->where('user_id', $userId)->exists();

        $contacts = Contact::query()
            ->where('user_id', $userId)
            ->when($this->search !== '', function ($query): void {
                $term = '%'.$this->search.'%';
                $query->where(function ($q) use ($term): void {
                    $q->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->orderBy('name')
            ->paginate(15);

        return [
            'contacts' => $contacts,
            'hasContacts' => $hasContacts,
        ];
    }
}; ?>

<div class="flex h-full w-full min-w-0 flex-1 flex-col gap-4">
    <div
        class="flex flex-col gap-3 border-b border-zinc-200/90 pb-4 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-800"
    >
        <div class="min-w-0">
            <h1 class="ui-page-heading">{{ __('Contacts') }}</h1>
            <p class="ui-muted mt-1 max-w-3xl text-base">
                {{ __('Save people you send documents to often and reuse them when adding signers') }}
            </p>
        </div>
        <flux:button
            variant="primary"
            class="shrink-0 self-start sm:self-center"
            type="button"
            wire:click="openCreateModal"
        >
            {{ __('Add Contact') }}
        </flux:button>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
        <form wire:submit="applySearch" class="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row sm:items-end">
            <flux:field class="min-w-0 flex-1 lg:max-w-xl">
                <flux:label>{{ __('Search') }}</flux:label>
                <flux:input
                    wire:model="searchInput"
                    type="search"
                    placeholder="{{ __('Name or email') }}"
                    autocomplete="off"
                    class="w-full"
                />
            </flux:field>
            <flux:button variant="outline" type="submit">{{ __('Search') }}</flux:button>
        </form>
    </div>

    @if (! $hasContacts)
        <div
            class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300/90 bg-zinc-50/80 px-6 py-12 text-center dark:border-zinc-700 dark:bg-zinc-900/40"
        >
            <flux:icon.user-group class="mx-auto size-10 text-zinc-400" />
            <p class="mt-3 text-base font-medium text-zinc-800 dark:text-zinc-100">{{ __('No contacts yet') }}</p>
            <flux:button variant="primary" type="button" wire:click="openCreateModal" class="mt-5">
                {{ __('Add Contact') }}
            </flux:button>
        </div>
    @else
        @if ($contacts->isEmpty())
            <p class="ui-muted text-sm">{{ __('No contacts match your search.') }}</p>
        @else
            <div class="ui-panel overflow-hidden p-0">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] divide-y divide-zinc-200/80 text-left text-sm dark:divide-zinc-700/80">
                    <thead class="border-b border-zinc-200 bg-zinc-50/90 dark:border-zinc-800 dark:bg-zinc-900/60">
                        <tr>
                            <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-400">{{ __('Name') }}</th>
                            <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-400">{{ __('Email') }}</th>
                            <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-400">{{ __('Phone') }}</th>
                            <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-400">{{ __('Company') }}</th>
                            <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-400">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($contacts as $contact)
                            <tr class="transition-colors hover:bg-teal-500/[0.04] dark:hover:bg-white/[0.03]">
                                <td class="px-4 py-2.5 font-medium text-zinc-900 dark:text-zinc-100">{{ $contact->name }}</td>
                                <td class="px-4 py-2.5 text-zinc-600 dark:text-zinc-300">{{ $contact->email }}</td>
                                <td class="px-4 py-2.5 text-zinc-600 dark:text-zinc-300">{{ $contact->phone ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-zinc-600 dark:text-zinc-300">{{ $contact->company ?? '—' }}</td>
                                <td class="px-4 py-2 text-right">
                                    <div class="flex flex-wrap items-center justify-end gap-1">
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            type="button"
                                            wire:click="openEditModal({{ $contact->id }})"
                                        >
                                            {{ __('Edit') }}
                                        </flux:button>
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            type="button"
                                            wire:click="deleteContact({{ $contact->id }})"
                                            wire:confirm="{{ __('Delete this contact?') }}"
                                            class="!px-2"
                                        >
                                            <flux:icon.trash class="size-5 text-zinc-500" />
                                        </flux:button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="pb-1">
            {{ $contacts->links() }}
        </div>
    @endif

    <flux:modal wire:model="showContactModal" class="max-w-2xl">
        <form wire:submit="saveContact" class="space-y-6">
            <div class="space-y-3 border-b border-zinc-200/80 pb-5 dark:border-zinc-800/90">
                <div class="inline-flex size-11 items-center justify-center rounded-xl bg-teal-500/10 text-teal-600 dark:bg-teal-400/10 dark:text-teal-300">
                    <flux:icon.user-group class="size-5" />
                </div>
                <div>
                    <flux:heading size="lg">{{ $editingContactId ? __('Edit contact') : __('Add contact') }}</flux:heading>
                    <flux:subheading class="mt-1">
                        {{ __('Name and email are required. Add phone and company to speed up future signer selection.') }}
                    </flux:subheading>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field class="sm:col-span-1">
                    <flux:label>{{ __('Name') }}</flux:label>
                    <flux:input wire:model="name" type="text" autocomplete="name" required placeholder="{{ __('Full name') }}" />
                    <flux:error name="name" />
                </flux:field>
                <flux:field class="sm:col-span-1">
                    <flux:label>{{ __('Email') }}</flux:label>
                    <flux:input wire:model="email" type="email" autocomplete="email" required placeholder="{{ __('name@company.com') }}" />
                    <flux:error name="email" />
                </flux:field>
                <flux:field class="sm:col-span-1">
                    <flux:label>{{ __('Phone') }}</flux:label>
                    <flux:input wire:model="phone" type="tel" autocomplete="tel" placeholder="{{ __('Optional') }}" />
                    <flux:error name="phone" />
                </flux:field>
                <flux:field class="sm:col-span-1">
                    <flux:label>{{ __('Company') }}</flux:label>
                    <flux:input wire:model="company" type="text" autocomplete="organization" placeholder="{{ __('Optional') }}" />
                    <flux:error name="company" />
                </flux:field>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-zinc-200/80 pt-5 dark:border-zinc-800/90">
                <flux:modal.close>
                    <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">
                    {{ $editingContactId ? __('Save changes') : __('Save contact') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
