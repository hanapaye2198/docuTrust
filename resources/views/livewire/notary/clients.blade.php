<?php

use App\Enums\NotaryRequestStatus;
use App\Models\NotaryRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    private const PER_PAGE = 20;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'sort')]
    public string $sortBy = 'recent';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSortBy(): void
    {
        if (! in_array($this->sortBy, ['recent', 'name', 'cases'], true)) {
            $this->sortBy = 'recent';
        }

        $this->resetPage();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $notaryId = Auth::id();
        abort_unless($notaryId !== null, 401);

        $hasClients = $this->baseClientsQuery($notaryId)->exists();
        $clientsQuery = $this->baseClientsQuery($notaryId)
            ->withCount([
                'notaryRequestsAsRequester as total_cases' => fn (Builder $query) => $query
                    ->where('notary_user_id', $notaryId),
                'notaryRequestsAsRequester as active_cases' => fn (Builder $query) => $query
                    ->where('notary_user_id', $notaryId)
                    ->whereNotIn('status', $this->closedStatusValues()),
                'notaryRequestsAsRequester as completed_cases' => fn (Builder $query) => $query
                    ->where('notary_user_id', $notaryId)
                    ->where('status', NotaryRequestStatus::Notarized->value),
                'notaryRequestsAsRequester as pending_cases' => fn (Builder $query) => $query
                    ->where('notary_user_id', $notaryId)
                    ->whereIn('status', [
                        NotaryRequestStatus::Draft->value,
                        NotaryRequestStatus::Submitted->value,
                    ]),
            ])
            ->with(['notaryRequestsAsRequester' => fn ($query) => $query
                ->where('notary_user_id', $notaryId)
                ->latest()
                ->limit(1),
            ])
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.$this->search.'%';

                $query->where(function (Builder $nested) use ($term): void {
                    $nested
                        ->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            });

        $clients = match ($this->sortBy) {
            'name' => $clientsQuery->orderBy('name')->paginate(self::PER_PAGE),
            'cases' => $clientsQuery->orderByDesc('total_cases')->orderBy('name')->paginate(self::PER_PAGE),
            default => $clientsQuery
                ->orderByDesc(
                    NotaryRequest::query()
                        ->select('updated_at')
                        ->where('notary_user_id', $notaryId)
                        ->whereColumn('user_id', 'users.id')
                        ->latest()
                        ->limit(1)
                )
                ->orderBy('name')
                ->paginate(self::PER_PAGE),
        };

        return [
            'clients' => $clients,
            'hasClients' => $hasClients,
        ];
    }

    protected function baseClientsQuery(int $notaryId): Builder
    {
        return User::query()
            ->whereHas('notaryRequestsAsRequester', fn (Builder $query) => $query->where('notary_user_id', $notaryId));
    }

    /**
     * @return list<string>
     */
    protected function closedStatusValues(): array
    {
        return [
            NotaryRequestStatus::Notarized->value,
            NotaryRequestStatus::Rejected->value,
            NotaryRequestStatus::Cancelled->value,
            NotaryRequestStatus::Failed->value,
        ];
    }
}; ?>

<x-admin.page gap="gap-6">
    <div class="flex flex-col gap-3 border-b border-zinc-200/90 pb-4 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-800">
        <div class="min-w-0">
            <h1 class="ui-page-heading">{{ __('Clients') }}</h1>
            <p class="ui-muted mt-1 max-w-3xl text-base">
                {{ __('Everyone who has submitted a notarization request to you') }}
            </p>
        </div>

        <flux:badge color="zinc" class="self-start sm:self-center">
            {{ trans_choice(':count total|:count total', $clients->total()) }}
        </flux:badge>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
        <flux:field class="min-w-0 flex-1 lg:max-w-sm">
            <flux:label>{{ __('Search') }}</flux:label>
            <flux:input
                wire:model.live.debounce.300ms="search"
                type="search"
                placeholder="{{ __('Search by name or email...') }}"
                icon="magnifying-glass"
                autocomplete="off"
            />
        </flux:field>

        <flux:field class="w-full sm:w-48">
            <flux:label>{{ __('Sort') }}</flux:label>
            <flux:select wire:model.live="sortBy">
                <option value="recent">{{ __('Most recent') }}</option>
                <option value="name">{{ __('Name A-Z') }}</option>
                <option value="cases">{{ __('Most cases') }}</option>
            </flux:select>
        </flux:field>
    </div>

    @if (! $hasClients)
        <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300/90 bg-zinc-50/80 px-6 py-16 text-center dark:border-zinc-700 dark:bg-zinc-900/40">
            <flux:icon.users class="mx-auto size-10 text-zinc-400" />
            <p class="mt-3 text-base font-medium text-zinc-800 dark:text-zinc-100">{{ __('No clients yet') }}</p>
            <p class="ui-muted mt-1 text-sm">
                {{ __('Clients appear here once they submit a notarization request.') }}
            </p>
        </div>
    @else
        @if ($clients->isEmpty())
            <p class="ui-muted text-sm">{{ __('No clients match your search.') }}</p>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($clients as $client)
                    @php
                        /** @var User $client */
                        $lastCase = $client->notaryRequestsAsRequester->first();
                        $initial = Str::of($client->name)->trim()->substr(0, 1)->upper();
                    @endphp

                    <a
                        href="{{ route('notary.client.show', $client) }}"
                        wire:navigate
                        wire:key="notary-client-{{ $client->id }}"
                        class="ui-panel group block p-5 transition-colors hover:border-teal-400/80 dark:hover:border-teal-500/60"
                    >
                        <div class="flex items-center gap-3">
                            <div class="flex size-11 shrink-0 items-center justify-center rounded-full bg-teal-600 text-sm font-semibold text-white shadow-sm shadow-teal-600/20">
                                {{ $initial }}
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-zinc-900 transition-colors group-hover:text-teal-700 dark:text-zinc-100 dark:group-hover:text-teal-300">
                                    {{ $client->name }}
                                </p>
                                <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $client->email }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-5 grid grid-cols-4 gap-2 text-center">
                            <div>
                                <p class="text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ $client->total_cases }}</p>
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400">{{ __('Total') }}</p>
                            </div>
                            <div>
                                <p class="text-lg font-bold text-amber-500">{{ $client->active_cases }}</p>
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400">{{ __('Active') }}</p>
                            </div>
                            <div>
                                <p class="text-lg font-bold text-sky-500">{{ $client->pending_cases }}</p>
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400">{{ __('Pending') }}</p>
                            </div>
                            <div>
                                <p class="text-lg font-bold text-emerald-500">{{ $client->completed_cases }}</p>
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400">{{ __('Done') }}</p>
                            </div>
                        </div>

                        @if ($lastCase !== null)
                            <div class="mt-4 border-t border-zinc-100 pt-3 dark:border-zinc-800">
                                <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ __('Last: :title', ['title' => $lastCase->title]) }}
                                </p>
                                <p class="mt-0.5 text-xs text-zinc-400">
                                    {{ $lastCase->updated_at->diffForHumans() }}
                                </p>
                            </div>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif

        <div class="pb-1">
            {{ $clients->links() }}
        </div>
    @endif
</x-admin.page>
