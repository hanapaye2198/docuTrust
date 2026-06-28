<?php

use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Models\Document;
use App\Services\SigningMethodService;
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
        $user = auth()->user();
        $isPlatformView = $user?->isSuperAdmin() === true;
        $isSignerView = ! $isPlatformView && $user?->role === UserRole::Client && ! $user?->isOrganizationAdmin();

        $documentsQuery = Document::query()
            ->where('status', DocumentStatus::Completed)
            ->when(! $isPlatformView, fn ($query) => $query
                ->where('organization_id', $user?->organization_id)
                ->whereNull('notary_request_id'))
            ->when($isSignerView, function ($query) use ($user): void {
                $query->where(function ($scopedQuery) use ($user): void {
                    $scopedQuery
                        ->where('user_id', $user?->id)
                        ->orWhereHas('documentSigners', fn ($signerQuery) => $signerQuery->where('user_id', $user?->id));
                });
            })
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($searchQuery): void {
                    $searchQuery
                        ->where('title', 'like', '%'.$this->search.'%')
                        ->orWhereHas('documentHash', fn ($hashQuery) => $hashQuery->where('hash', 'like', '%'.$this->search.'%'));
                });
            });

        return [
            'documents' => $documentsQuery
                ->with([
                    'documentHash',
                    'documentSigners' => fn ($query) => $query
                        ->when($isSignerView, fn ($signerQuery) => $signerQuery->where('user_id', $user?->id))
                        ->orderBy('id'),
                ])
                ->withCount([
                    'documentSigners',
                    'documentSigners as signed_signers_count' => fn ($query) => $query->where('status', 'signed'),
                ])
                ->latest('updated_at')
                ->latest('id')
                ->paginate(10)
                ->withQueryString(),
            'completedDocumentsCount' => (clone $documentsQuery)->count(),
            'isSignerView' => $isSignerView,
        ];
    }
}; ?>

<div class="flex min-h-full w-full flex-1 flex-col gap-6 p-1">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50 sm:text-3xl">
                {{ __('Completed Documents') }}
            </h1>
            <p class="mt-1 max-w-2xl text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Find fully signed documents, download the generated PDF, and review their verification hash or blockchain proof.') }}
            </p>
        </div>

        <flux:button variant="outline" :href="route('documents.index')" wire:navigate>
            {{ __('All documents') }}
        </flux:button>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-emerald-200/80 bg-white p-5 shadow-sm dark:border-emerald-900/40 dark:bg-zinc-900">
            <p class="text-xs font-semibold uppercase tracking-widest text-emerald-600 dark:text-emerald-400">{{ __('Completed') }}</p>
            <p class="mt-3 text-3xl font-bold tabular-nums text-emerald-600 dark:text-emerald-400">{{ $completedDocumentsCount }}</p>
            <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">{{ __('fully signed documents') }}</p>
        </div>
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <p class="text-xs font-semibold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">{{ __('Final PDF') }}</p>
            <p class="mt-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Generated on demand') }}</p>
            <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">{{ __('Download will prepare artifacts if needed.') }}</p>
        </div>
        <div class="rounded-2xl border border-teal-200/80 bg-white p-5 shadow-sm dark:border-teal-900/40 dark:bg-zinc-900">
            <p class="text-xs font-semibold uppercase tracking-widest text-teal-600 dark:text-teal-400">{{ __('Verification') }}</p>
            <p class="mt-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('SHA-256 + blockchain proof') }}</p>
            <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">{{ __('Available once the completion job stores a hash.') }}</p>
        </div>
    </div>

    <div class="relative">
        <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center">
            <svg class="h-4 w-4 text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
        </span>
        <label class="sr-only" for="search-completed-documents">{{ __('Search completed documents') }}</label>
        <input
            id="search-completed-documents"
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="{{ __('Search by title or hash...') }}"
            class="w-full rounded-xl border border-zinc-200 bg-white py-2.5 pl-10 pr-4 text-sm text-zinc-900 placeholder:text-zinc-400 shadow-sm transition focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder:text-zinc-600"
        />
    </div>

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200/80 dark:divide-zinc-800">
                <thead>
                    <tr class="bg-zinc-50/80 dark:bg-zinc-800/50">
                        <th scope="col" class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Document') }}</th>
                        <th scope="col" class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Hash') }}</th>
                        <th scope="col" class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Signers') }}</th>
                        <th scope="col" class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Updated') }}</th>
                        <th scope="col" class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800/80">
                    @forelse ($documents as $document)
                        @php
                            $assignedSigner = $isSignerView ? $document->documentSigners->first() : null;
                            $documentUrl = $assignedSigner !== null
                                ? app(SigningMethodService::class)->signerEntryUrl($assignedSigner)
                                : route('documents.show', $document);
                            $verifyIdentifier = $document->documentHash?->hash ?? (string) $document->id;
                        @endphp
                        <tr class="group transition-colors duration-100 hover:bg-teal-50/40 dark:hover:bg-teal-900/10" wire:key="completed-document-{{ $document->id }}">
                            <td class="px-5 py-4">
                                <a href="{{ $documentUrl }}" wire:navigate class="text-sm font-semibold text-zinc-800 transition hover:text-teal-700 dark:text-zinc-200 dark:hover:text-teal-300">
                                    {{ $document->title }}
                                </a>
                                <div class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">{{ __('Document #:id', ['id' => $document->id]) }}</div>
                            </td>
                            <td class="px-5 py-4">
                                @if ($document->documentHash !== null)
                                    <div class="max-w-xs break-all font-mono text-xs text-zinc-600 dark:text-zinc-300">{{ $document->documentHash->hash }}</div>
                                    @if ($document->documentHash->transaction_id)
                                        <div class="mt-1 text-[10px] font-semibold uppercase tracking-wider text-teal-600 dark:text-teal-400">{{ __('Anchored') }}</div>
                                        <div class="mt-1 max-w-xs break-all font-mono text-[10px] text-zinc-500 dark:text-zinc-400">{{ $document->documentHash->transaction_id }}</div>
                                    @else
                                        <div class="mt-1 text-[10px] font-semibold uppercase tracking-wider text-amber-600 dark:text-amber-400">{{ __('Transaction pending') }}</div>
                                    @endif
                                @else
                                    <span class="text-sm text-zinc-400 dark:text-zinc-500">{{ __('Hash pending') }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-sm tabular-nums text-zinc-600 dark:text-zinc-300">
                                {{ $document->signed_signers_count }}/{{ $document->document_signers_count }}
                            </td>
                            <td class="px-5 py-4 text-sm tabular-nums text-zinc-400 dark:text-zinc-500">
                                {{ $document->updated_at->diffForHumans() }}
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap justify-end gap-2">
                                    @if ($assignedSigner === null)
                                        <flux:button size="sm" variant="ghost" :href="route('documents.show', $document)" wire:navigate>{{ __('Open') }}</flux:button>
                                    @endif
                                    <flux:button size="sm" variant="outline" :href="route('documents.download', $document)">{{ __('Download PDF') }}</flux:button>
                                    <flux:button size="sm" variant="outline" :href="route('verify.index', ['documentIdentifier' => $verifyIdentifier])">{{ __('Verify') }}</flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-16 text-center">
                                <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">{{ __('No completed documents found') }}</p>
                                <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">{{ __('Completed documents will appear here after every required signer finishes signing.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="pb-2">
        {{ $documents->links() }}
    </div>
</div>
