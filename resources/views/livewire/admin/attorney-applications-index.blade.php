<?php

use App\Enums\NotaryCredentialStatus;
use App\Models\NotaryCredential;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    #[Url(as: 'status')]
    public string $statusFilter = 'pending';

    #[Url(as: 'q')]
    public string $search = '';

    public function mount(): void
    {
        $this->authorize('viewAny', NotaryCredential::class);
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $query = NotaryCredential::query()
            ->with(['user.organization', 'reviewedBy'])
            ->when($this->statusFilter === 'expired', function (Builder $builder): void {
                $builder
                    ->where(function (Builder $nested): void {
                        $nested
                            ->where('status', NotaryCredentialStatus::Expired->value)
                            ->orWhere(function (Builder $inner): void {
                                $inner
                                    ->where('status', NotaryCredentialStatus::Active->value)
                                    ->whereDate('commission_expires_at', '<', now()->toDateString());
                            });
                    });
            })
            ->when($this->statusFilter !== 'all' && $this->statusFilter !== 'expired', function (Builder $builder): void {
                $builder->where('status', $this->statusFilter);
            })
            ->when($this->search !== '', function (Builder $builder): void {
                $builder->where(function (Builder $nested): void {
                    $nested
                        ->where('commission_number', 'like', '%'.$this->search.'%')
                        ->orWhereHas('user', fn (Builder $user) => $user
                            ->where('name', 'like', '%'.$this->search.'%')
                            ->orWhere('email', 'like', '%'.$this->search.'%'));
                });
            })
            ->latest('submitted_at')
            ->latest('id');

        return [
            'applications' => $query->paginate(15),
            'pendingCount' => NotaryCredential::query()->where('status', NotaryCredentialStatus::Pending->value)->count(),
        ];
    }
}; ?>

<x-admin.page>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ __('Attorney applications') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Review attorney qualification submissions before granting e-Notary access.') }}</p>
        </div>
        @if ($pendingCount > 0)
            <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
                {{ trans_choice(':count pending|:count pending', $pendingCount, ['count' => $pendingCount]) }}
            </span>
        @endif
    </div>

    <div class="flex flex-col gap-3 sm:flex-row">
        <input
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="{{ __('Search name, email, or commission…') }}"
            class="flex-1 rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
        />
        <select wire:model.live="statusFilter" class="rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 sm:w-52">
            <option value="pending">{{ __('Pending') }}</option>
            <option value="active">{{ __('Approved') }}</option>
            <option value="rejected">{{ __('Rejected') }}</option>
            <option value="expired">{{ __('Expired') }}</option>
            <option value="all">{{ __('All') }}</option>
        </select>
    </div>

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
            <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Applicant') }}</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Commission') }}</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Status') }}</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Submitted') }}</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($applications as $application)
                    @php
                        $displayExpired = $application->isExpired() && $application->status === 'active';
                        $statusLabel = $displayExpired ? 'expired' : $application->status;
                    @endphp
                    <tr>
                        <td class="px-5 py-4">
                            <p class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $application->user?->name ?? '—' }}</p>
                            <p class="text-sm text-zinc-500">{{ $application->user?->email ?? '—' }}</p>
                            <p class="text-xs text-zinc-400">{{ $application->user?->organization?->name ?? '—' }}</p>
                        </td>
                        <td class="px-5 py-4 text-sm">
                            <p class="font-medium text-zinc-800 dark:text-zinc-200">{{ $application->commission_number }}</p>
                            <p class="text-xs text-zinc-500">{{ __('Expires') }} {{ $application->commission_expires_at?->format('M j, Y') ?? '—' }}</p>
                            @if ($application->is_renewal)
                                <span class="mt-1 inline-flex rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-medium text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300">{{ __('Renewal') }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium capitalize
                                @if ($statusLabel === 'pending') bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300
                                @elseif ($statusLabel === 'active') bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300
                                @elseif ($statusLabel === 'rejected') bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-300
                                @else bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400 @endif">
                                {{ str_replace('_', ' ', $statusLabel) }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-sm text-zinc-500">{{ $application->submitted_at?->diffForHumans() ?? '—' }}</td>
                        <td class="px-5 py-4 text-right">
                            <flux:button size="sm" variant="primary" :href="route('admin.attorney-applications.show', $application)" wire:navigate>{{ __('Review') }}</flux:button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center text-sm text-zinc-500">{{ __('No applications found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $applications->links() }}
</x-admin.page>
