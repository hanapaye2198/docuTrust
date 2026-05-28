<?php

use App\Models\AttorneyNotarialRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $query = AttorneyNotarialRegistry::query()
            ->with(['notaryRequest'])
            ->whereHas('notaryRequest', function (Builder $builder) use ($user): void {
                $builder->where('notary_user_id', $user->id);
            })
            ->when($this->search !== '', function (Builder $builder): void {
                $builder->where(function (Builder $nested): void {
                    $nested->where('title', 'like', '%'.$this->search.'%')
                        ->orWhere('entry_no', 'like', '%'.$this->search.'%')
                        ->orWhereHas('notaryRequest', fn (Builder $requestQuery) => $requestQuery->where('title', 'like', '%'.$this->search.'%'));
                });
            })
            ->latest('updated_at');

        return [
            'entries' => $query->paginate(15),
        ];
    }
}; ?>

<div class="flex min-h-full w-full flex-1 flex-col gap-6 p-1">
    <header>
        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50 sm:text-3xl">{{ __('Notary registry') }}</h1>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('Draft and saved attorney registry records across your notary requests. Open a request to complete payment, seal, and final register entry.') }}
        </p>
    </header>

    <div class="ui-panel p-4 sm:p-5">
        <flux:input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('Search title, entry no., or request…') }}" icon="magnifying-glass" />
    </div>

    <div class="ui-panel overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900/50 dark:text-zinc-400">
                    <tr>
                        <th class="px-4 py-3">{{ __('Request') }}</th>
                        <th class="px-4 py-3">{{ __('Entry no.') }}</th>
                        <th class="px-4 py-3">{{ __('Title') }}</th>
                        <th class="px-4 py-3">{{ __('Act') }}</th>
                        <th class="px-4 py-3">{{ __('Fees') }}</th>
                        <th class="px-4 py-3">{{ __('Updated') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($entries as $entry)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-900/30">
                            <td class="px-4 py-3">
                                <a href="{{ route('notary.requests.show', $entry->notaryRequest) }}" wire:navigate class="font-medium text-teal-700 hover:underline dark:text-teal-400">
                                    {{ $entry->notaryRequest->title }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">{{ $entry->entry_no ?: '—' }}</td>
                            <td class="px-4 py-3">{{ $entry->title }}</td>
                            <td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', $entry->notarial_act_type) }}</td>
                            <td class="px-4 py-3">PHP {{ number_format((float) $entry->fees, 2) }}</td>
                            <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">{{ $entry->updated_at?->timezone(config('docutrust.notary.timezone', 'Asia/Manila'))->format('M j, Y g:i A') }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <flux:button size="sm" variant="outline" :href="route('notary.attorney-registry', $entry->notaryRequest)" wire:navigate>{{ __('Edit draft') }}</flux:button>
                                    <flux:button size="sm" variant="ghost" :href="route('notary.requests.show', $entry->notaryRequest)" wire:navigate>{{ __('Open request') }}</flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-zinc-500 dark:text-zinc-400">
                                {{ __('No registry records yet. Save a draft from any notary request’s Settlement tab.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-700">
            {{ $entries->links() }}
        </div>
    </div>
</div>
