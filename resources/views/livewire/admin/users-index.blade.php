<?php

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use App\Services\Admin\AdminUserService;
use App\Services\Admin\UserDeletionImpactService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    #[Url(as: 'role')]
    public string $roleFilter = 'all';

    #[Url(as: 'q')]
    public string $search = '';

    public bool $showUserModal = false;

    public bool $showDeleteModal = false;

    public ?int $editingUserId = null;

    public ?int $userToDeleteId = null;

    public string $formName = '';

    public string $formEmail = '';

    public string $formRole = 'client';

    public ?int $formOrganizationId = null;

    public string $formPassword = '';

    public string $deleteConfirmEmail = '';

    /** @var array<string, mixed>|null */
    public ?array $deleteImpact = null;

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function updatedRoleFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', User::class);
        $this->resetForm();
        $this->editingUserId = null;
        $this->showUserModal = true;
    }

    public function openEditModal(int $userId): void
    {
        $user = User::query()->findOrFail($userId);
        $this->authorize('update', $user);

        $this->editingUserId = $user->id;
        $this->formName = $user->name;
        $this->formEmail = $user->email;
        $this->formRole = $user->role->value;
        $this->formOrganizationId = $user->organization_id;
        $this->formPassword = '';
        $this->showUserModal = true;
    }

    public function saveUser(AdminUserService $adminUserService): void
    {
        $rules = [
            'formName' => ['required', 'string', 'max:255'],
            'formEmail' => ['required', 'email', 'max:255'],
            'formRole' => ['required', 'in:client,notary,notary_admin'],
            'formOrganizationId' => ['nullable', 'integer', 'exists:organizations,id'],
        ];

        if ($this->editingUserId === null) {
            $rules['formPassword'] = ['nullable', 'string', 'min:8'];
        }

        $this->validate($rules);

        if ($this->editingUserId === null) {
            $this->authorize('create', User::class);
            $adminUserService->create([
                'name' => $this->formName,
                'email' => $this->formEmail,
                'role' => $this->formRole,
                'organization_id' => $this->formOrganizationId,
                'password' => $this->formPassword !== '' ? $this->formPassword : 'password',
            ]);
            session()->flash('status', __('User created successfully.'));
        } else {
            $user = User::query()->findOrFail($this->editingUserId);
            $this->authorize('update', $user);
            $adminUserService->update($user, [
                'name' => $this->formName,
                'email' => $this->formEmail,
                'role' => $this->formRole,
                'organization_id' => $this->formOrganizationId,
            ]);
            session()->flash('status', __('User updated successfully.'));
        }

        $this->showUserModal = false;
        $this->resetForm();
    }

    public function openDeleteModal(int $userId, UserDeletionImpactService $impactService): void
    {
        $user = User::query()->findOrFail($userId);
        $this->authorize('delete', $user);

        $this->userToDeleteId = $user->id;
        $this->deleteImpact = $impactService->for($user);
        $this->deleteConfirmEmail = '';
        $this->showDeleteModal = true;
    }

    public function confirmDeleteUser(AdminUserService $adminUserService): void
    {
        if ($this->userToDeleteId === null) {
            return;
        }

        $user = User::query()->findOrFail($this->userToDeleteId);
        $this->authorize('delete', $user);

        $this->validate([
            'deleteConfirmEmail' => ['required', 'email', 'in:'.$user->email],
        ]);

        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);

        $adminUserService->delete($user, $actor);

        $this->showDeleteModal = false;
        $this->userToDeleteId = null;
        $this->deleteImpact = null;
        session()->flash('status', __('User permanently deleted.'));
    }

    public function deactivateUser(int $userId, AdminUserService $adminUserService): void
    {
        $user = User::query()->findOrFail($userId);
        $this->authorize('deactivate', $user);
        $adminUserService->deactivate($user);
        session()->flash('status', __('User deactivated.'));
    }

    public function reactivateUser(int $userId, AdminUserService $adminUserService): void
    {
        $user = User::query()->findOrFail($userId);
        $this->authorize('deactivate', $user);
        $adminUserService->reactivate($user);
        session()->flash('status', __('User reactivated.'));
    }

    private function resetForm(): void
    {
        $this->formName = '';
        $this->formEmail = '';
        $this->formRole = UserRole::Client->value;
        $this->formOrganizationId = null;
        $this->formPassword = '';
        $this->resetValidation();
    }

    public function with(): array
    {
        $users = User::query()
            ->with('organization')
            ->when($this->roleFilter !== 'all', fn ($query) => $query->where('role', $this->roleFilter))
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($searchQuery): void {
                    $searchQuery
                        ->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                });
            })
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return [
            'users' => $users,
            'organizations' => Organization::query()->orderBy('name')->get(['id', 'name']),
            'roleOptions' => [
                UserRole::Client->value => __('Client (signing)'),
                UserRole::Notary->value => __('Notary (attorney)'),
                UserRole::NotaryAdmin->value => __('Notary admin'),
                UserRole::SuperAdmin->value => __('Super admin'),
            ],
        ];
    }
}; ?>

<x-admin.page>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ __('Platform users') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Manage client signing accounts, notaries, and organization administrators.') }}
            </p>
        </div>
        <flux:button variant="primary" wire:click="openCreateModal">{{ __('Add user') }}</flux:button>
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    <div class="flex flex-col gap-3 sm:flex-row">
        <input
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="{{ __('Search name or email…') }}"
            class="flex-1 rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
        />
        <select wire:model.live="roleFilter" class="rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 sm:w-52">
            <option value="all">{{ __('All roles') }}</option>
            @foreach ($roleOptions as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
            <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('User') }}</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Role') }}</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Organization') }}</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Status') }}</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($users as $user)
                    <tr>
                        <td class="px-5 py-4">
                            <p class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $user->name }}</p>
                            <p class="text-sm text-zinc-500">{{ $user->email }}</p>
                        </td>
                        <td class="px-5 py-4 text-sm capitalize">{{ str_replace('_', ' ', $user->role->value) }}</td>
                        <td class="px-5 py-4 text-sm">{{ $user->organization?->name ?? '—' }}</td>
                        <td class="px-5 py-4 text-sm">
                            @if ($user->deactivated_at)
                                <span class="text-amber-600">{{ __('Deactivated') }}</span>
                            @else
                                <span class="text-emerald-600">{{ __('Active') }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-right">
                            <div class="flex flex-wrap justify-end gap-2">
                                <flux:button size="sm" variant="outline" wire:click="openEditModal({{ $user->id }})">{{ __('Edit') }}</flux:button>
                                @if ($user->deactivated_at)
                                    <flux:button size="sm" variant="outline" wire:click="reactivateUser({{ $user->id }})">{{ __('Reactivate') }}</flux:button>
                                @elseif ($user->role !== \App\Enums\UserRole::SuperAdmin)
                                    <flux:button size="sm" variant="outline" wire:click="deactivateUser({{ $user->id }})">{{ __('Deactivate') }}</flux:button>
                                @endif
                                @can('delete', $user)
                                    <flux:button size="sm" variant="danger" wire:click="openDeleteModal({{ $user->id }})">{{ __('Delete') }}</flux:button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center text-sm text-zinc-500">{{ __('No users found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $users->links() }}

    <flux:modal wire:model="showUserModal" class="max-w-lg">
        <form wire:submit="saveUser" class="space-y-4">
            <flux:heading size="lg">{{ $editingUserId ? __('Edit user') : __('Create user') }}</flux:heading>
            <flux:input wire:model="formName" label="{{ __('Name') }}" required />
            <flux:input wire:model="formEmail" type="email" label="{{ __('Email') }}" required />
            <flux:select wire:model="formRole" label="{{ __('Role') }}">
                <flux:select.option value="client">{{ __('Client (signing)') }}</flux:select.option>
                <flux:select.option value="notary">{{ __('Notary (attorney)') }}</flux:select.option>
                <flux:select.option value="notary_admin">{{ __('Notary admin') }}</flux:select.option>
            </flux:select>
            <flux:select wire:model="formOrganizationId" label="{{ __('Organization') }}" placeholder="{{ __('Auto-create organization') }}">
                @foreach ($organizations as $organization)
                    <flux:select.option value="{{ $organization->id }}">{{ $organization->name }}</flux:select.option>
                @endforeach
            </flux:select>
            @if ($editingUserId === null)
                <flux:input wire:model="formPassword" type="password" label="{{ __('Password (optional)') }}" placeholder="{{ __('Defaults to password') }}" />
            @endif
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="outline" wire:click="$set('showUserModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showDeleteModal" class="max-w-lg">
        @if ($deleteImpact !== null && $userToDeleteId !== null)
            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Delete user permanently?') }}</flux:heading>
                <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ $deleteImpact['warning_message'] }}</p>
                <ul class="list-inside list-disc text-sm text-zinc-600 dark:text-zinc-300">
                    <li>{{ __(':count documents (:completed completed)', ['count' => $deleteImpact['documents_total'], 'completed' => $deleteImpact['documents_completed']]) }}</li>
                    <li>{{ __(':count notary requests', ['count' => $deleteImpact['notary_requests_as_requester']]) }}</li>
                    <li>{{ __(':count templates, :contacts contacts', ['count' => $deleteImpact['templates_count'], 'contacts' => $deleteImpact['contacts_count']]) }}</li>
                </ul>
                @if (! $deleteImpact['can_hard_delete'])
                    <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300">
                        {{ $deleteImpact['block_reason'] }}
                    </p>
                @else
                    <flux:input
                        wire:model="deleteConfirmEmail"
                        type="email"
                        label="{{ __('Type the user email to confirm deletion') }}"
                        placeholder="{{ User::query()->find($userToDeleteId)?->email }}"
                    />
                    <div class="flex justify-end gap-2">
                        <flux:button type="button" variant="outline" wire:click="$set('showDeleteModal', false)">{{ __('Cancel') }}</flux:button>
                        <flux:button type="button" variant="danger" wire:click="confirmDeleteUser">{{ __('Delete permanently') }}</flux:button>
                    </div>
                @endif
            </div>
        @endif
    </flux:modal>
</x-admin.page>
