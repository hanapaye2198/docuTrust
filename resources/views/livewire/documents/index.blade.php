<?php

use App\Models\Document;
use App\Models\Tag;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = 'all';

    #[Url(as: 'tag')]
    public string $tagFilter = 'all';

    #[Url(as: 'from')]
    public string $dateFrom = '';

    #[Url(as: 'to')]
    public string $dateTo = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTagFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $cacheScope = implode('|', [
            'documents-index',
            (string) auth()->user()?->organization_id,
            $this->search,
            $this->statusFilter,
            $this->tagFilter,
            $this->dateFrom,
            $this->dateTo,
        ]);

        $documentsQuery = Document::query()
            ->where('organization_id', auth()->user()?->organization_id)
            ->when($this->search !== '', function ($query) {
                $query->where(function ($searchQuery) {
                    $searchQuery
                        ->where('title', 'like', '%'.$this->search.'%')
                        ->orWhere('file_path', 'like', '%'.$this->search.'%')
                        ->orWhereHas('tags', function ($tagQuery) {
                            $tagQuery->where('name', 'like', '%'.$this->search.'%');
                        });
                });
            })
            ->when($this->statusFilter !== 'all', function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->tagFilter !== 'all', function ($query) {
                $query->whereHas('tags', fn ($tagQuery) => $tagQuery->whereKey((int) $this->tagFilter));
            })
            ->when($this->dateFrom !== '', function ($query) {
                $query->whereDate('created_at', '>=', $this->dateFrom);
            })
            ->when($this->dateTo !== '', function ($query) {
                $query->whereDate('created_at', '<=', $this->dateTo);
            });

        $cachedCounts = Cache::remember('counts:'.$cacheScope, now()->addMinutes(2), function () use ($documentsQuery): array {
            $grouped = (clone $documentsQuery)
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status');

            return [
                'total' => (int) $grouped->sum(),
                'pending' => (int) ($grouped[\App\Enums\DocumentStatus::Pending->value] ?? 0),
                'completed' => (int) ($grouped[\App\Enums\DocumentStatus::Completed->value] ?? 0),
                'draft' => (int) ($grouped[\App\Enums\DocumentStatus::Draft->value] ?? 0),
            ];
        });

        return [
            'documents' => $documentsQuery
                ->with(['tags', 'documentSigners', 'signatures'])
                ->withCount('documentSigners')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(10)
                ->withQueryString(),
            'availableTags' => Tag::query()
                ->where('organization_id', auth()->user()?->organization_id)
                ->orderBy('name')
                ->get(),
            'totalDocuments' => $cachedCounts['total'],
            'pendingDocuments' => $cachedCounts['pending'],
            'completedDocuments' => $cachedCounts['completed'],
            'draftDocuments' => $cachedCounts['draft'],
        ];
    }
}; ?>

<div class="flex min-h-full w-full flex-1 flex-col gap-6 p-1">

    {{-- ── Page header ── --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50 sm:text-3xl">
                {{ __('Documents') }}
            </h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400 max-w-xl">
                {{ __('Manage your uploaded PDFs and track signing progress in one view.') }}
            </p>
        </div>
        <flux:button variant="primary" :href="route('documents.create')" wire:navigate>
            <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
            {{ __('Upload document') }}
        </flux:button>
    </div>

    {{-- ── Stat counters ── --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">

        <div class="flex flex-col gap-3 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-widest text-zinc-400 dark:text-zinc-500">{{ __('Total') }}</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                    <svg class="h-4 w-4 text-zinc-500 dark:text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                </span>
            </div>
            <p class="text-3xl font-bold tabular-nums tracking-tight text-zinc-900 dark:text-zinc-100">{{ $totalDocuments }}</p>
            <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('documents found') }}</p>
        </div>

        <div class="flex flex-col gap-3 rounded-2xl border border-amber-200/80 bg-white p-5 shadow-sm dark:border-amber-900/40 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-widest text-amber-600 dark:text-amber-400">{{ __('Pending') }}</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 dark:bg-amber-950/50">
                    <svg class="h-4 w-4 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                </span>
            </div>
            <p class="text-3xl font-bold tabular-nums tracking-tight text-amber-600 dark:text-amber-400">{{ $pendingDocuments }}</p>
            <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('awaiting signatures') }}</p>
        </div>

        <div class="flex flex-col gap-3 rounded-2xl border border-emerald-200/80 bg-white p-5 shadow-sm dark:border-emerald-900/40 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-widest text-emerald-600 dark:text-emerald-400">{{ __('Completed') }}</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-950/50">
                    <svg class="h-4 w-4 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                </span>
            </div>
            <p class="text-3xl font-bold tabular-nums tracking-tight text-emerald-600 dark:text-emerald-400">{{ $completedDocuments }}</p>
            <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('fully signed') }}</p>
        </div>

        <div class="flex flex-col gap-3 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">{{ __('Draft') }}</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                    <svg class="h-4 w-4 text-zinc-400 dark:text-zinc-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                </span>
            </div>
            <p class="text-3xl font-bold tabular-nums tracking-tight text-zinc-700 dark:text-zinc-300">{{ $draftDocuments }}</p>
            <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('not yet sent') }}</p>
        </div>

    </div>

    {{-- ── Search + Filter bar ── --}}
    <div class="flex flex-col gap-3 sm:flex-row">

        {{-- Search --}}
        <div class="relative flex-1">
            <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center">
                <svg class="h-4 w-4 text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
            </span>
            <label class="sr-only" for="search-documents">{{ __('Search documents') }}</label>
            <input
                id="search-documents"
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('Search by title…') }}"
                class="w-full rounded-xl border border-zinc-200 bg-white py-2.5 pl-10 pr-4 text-sm text-zinc-900 placeholder:text-zinc-400 shadow-sm transition focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder:text-zinc-600"
            />
        </div>

        <div class="relative sm:w-52">
            <label class="sr-only" for="tag-filter">{{ __('Filter by tag') }}</label>
            <select
                id="tag-filter"
                wire:model.live="tagFilter"
                class="w-full appearance-none rounded-xl border border-zinc-200 bg-white py-2.5 pl-3 pr-8 text-sm text-zinc-900 shadow-sm transition focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
            >
                <option value="all">{{ __('All tags') }}</option>
                @foreach ($availableTags as $tag)
                    <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                @endforeach
            </select>
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                <svg class="h-4 w-4 text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
            </span>
        </div>

        {{-- Status filter --}}
        <div class="relative sm:w-52">
            <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center">
                <svg class="h-4 w-4 text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591L15.75 12.5v5.25a.75.75 0 0 1-.364.643l-3 1.75a.75.75 0 0 1-1.136-.643v-6.5L4.659 7.409A2.25 2.25 0 0 1 4 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z"/></svg>
            </span>
            <label class="sr-only" for="status-filter">{{ __('Filter by status') }}</label>
            <select
                id="status-filter"
                wire:model.live="statusFilter"
                class="w-full appearance-none rounded-xl border border-zinc-200 bg-white py-2.5 pl-10 pr-8 text-sm text-zinc-900 shadow-sm transition focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
            >
                <option value="all">{{ __('All statuses') }}</option>
                <option value="draft">{{ __('Draft') }}</option>
                <option value="pending">{{ __('Pending') }}</option>
                <option value="completed">{{ __('Completed') }}</option>
                <option value="declined">{{ __('Declined') }}</option>
                <option value="cancelled">{{ __('Cancelled') }}</option>
                <option value="archived">{{ __('Archived') }}</option>
            </select>
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                <svg class="h-4 w-4 text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
            </span>
        </div>

        <div class="sm:w-44">
            <label class="sr-only" for="date-from">{{ __('Date from') }}</label>
            <input
                id="date-from"
                type="date"
                wire:model.live="dateFrom"
                class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm transition focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
            />
        </div>

        <div class="sm:w-44">
            <label class="sr-only" for="date-to">{{ __('Date to') }}</label>
            <input
                id="date-to"
                type="date"
                wire:model.live="dateTo"
                class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm transition focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
            />
        </div>

    </div>

    {{-- ── Documents table ── --}}
    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200/80 dark:divide-zinc-800">

                <thead>
                    <tr class="bg-zinc-50/80 dark:bg-zinc-800/50">
                        <th scope="col" class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Title') }}
                        </th>
                        <th scope="col" class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Status') }}
                        </th>
                        <th scope="col" class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Signers') }}
                        </th>
                        <th scope="col" class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Updated') }}
                        </th>
                        <th scope="col" class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Action') }}
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800/80">
                    @forelse ($documents as $document)
                        <tr class="group transition-colors duration-100 hover:bg-teal-50/40 dark:hover:bg-teal-900/10">

                            {{-- Title --}}
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-zinc-100 transition group-hover:bg-teal-100 dark:bg-zinc-800 dark:group-hover:bg-teal-900/40">
                                        <svg class="h-4 w-4 text-zinc-400 transition group-hover:text-teal-600 dark:text-zinc-500 dark:group-hover:text-teal-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                                    </div>
                                    <a href="{{ route('documents.show', $document) }}"
                                       wire:navigate
                                       class="truncate text-sm font-semibold text-zinc-800 transition hover:text-teal-700 dark:text-zinc-200 dark:hover:text-teal-300">
                                        {{ $document->title }}
                                    </a>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-1.5 pl-11">
                                    @forelse ($document->tags as $tag)
                                        <span class="inline-flex rounded-md bg-zinc-100 px-1.5 py-0.5 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                                            {{ $tag->name }}
                                        </span>
                                    @empty
                                        <span class="text-xs text-zinc-400">—</span>
                                    @endforelse
                                </div>
                            </td>

                            {{-- Status badge --}}
                            <td class="px-5 py-4 text-sm">
                                <x-document-status-badge :status="$document->status" />
                            </td>

                            {{-- Signers count --}}
                            <td class="px-5 py-4 text-sm">
                                <div class="flex items-center gap-1.5">
                                    <svg class="h-3.5 w-3.5 text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                                    <span class="tabular-nums text-zinc-600 dark:text-zinc-300">{{ $document->document_signers_count }}</span>
                                </div>
                            </td>

                            {{-- Updated --}}
                            <td class="px-5 py-4 text-sm tabular-nums text-zinc-400 dark:text-zinc-500">
                                {{ $document->updated_at->diffForHumans() }}
                            </td>

                            {{-- Action --}}
                            <td class="px-5 py-4 text-right text-sm">
                                <a href="{{ route('documents.show', $document) }}"
                                   wire:navigate
                                   class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 shadow-sm transition hover:border-teal-300 hover:bg-teal-50 hover:text-teal-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:border-teal-700 dark:hover:bg-teal-900/20 dark:hover:text-teal-300">
                                    {{ __('Open') }}
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                                </a>
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-16 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-zinc-100 dark:bg-zinc-800">
                                        <svg class="h-7 w-7 text-zinc-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m6.75 12H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">
                                            @if ($search !== '' || $statusFilter !== 'all' || $tagFilter !== 'all' || $dateFrom !== '' || $dateTo !== '')
                                                {{ __('No documents match your filters') }}
                                            @else
                                                {{ __('No documents yet') }}
                                            @endif
                                        </p>
                                        <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">
                                            @if ($search !== '' || $statusFilter !== 'all' || $tagFilter !== 'all' || $dateFrom !== '' || $dateTo !== '')
                                                {{ __('Try adjusting your search or filter.') }}
                                            @else
                                                {{ __('Upload a PDF to create your first document.') }}
                                            @endif
                                        </p>
                                    </div>
                                    @if ($search === '' && $statusFilter === 'all' && $tagFilter === 'all' && $dateFrom === '' && $dateTo === '')
                                        <flux:button variant="primary" size="sm" :href="route('documents.create')" wire:navigate>
                                            {{ __('Upload document') }}
                                        </flux:button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>

            </table>
        </div>
    </div>

    {{-- ── Pagination ── --}}
    <div class="pb-2">
        {{ $documents->links() }}
    </div>

</div>
