<?php

use App\Enums\NotaryRequestStatus;
use App\Models\NotaryRequest;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public function with(): array
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        // NotaryAdmin sees ALL requests globally (single admin manages all organizations)
        $awaitingFinalization = NotaryRequest::query()
            ->where('status', NotaryRequestStatus::Digitalized)
            ->with(['requester', 'notary', 'documents', 'organization'])
            ->latest('approved_at')
            ->get();

        $recentlyNotarized = NotaryRequest::query()
            ->where('status', NotaryRequestStatus::Notarized)
            ->with(['requester', 'notary', 'organization'])
            ->latest('completed_at')
            ->limit(10)
            ->get();

        $totalByStatus = NotaryRequest::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $inProgressStatuses = [
            NotaryRequestStatus::Submitted->value,
            NotaryRequestStatus::IdentityReviewRequired->value,
            NotaryRequestStatus::IdentityVerified->value,
            NotaryRequestStatus::LocationReviewRequired->value,
            NotaryRequestStatus::LocationVerified->value,
            NotaryRequestStatus::SessionScheduled->value,
            NotaryRequestStatus::SessionInProgress->value,
            NotaryRequestStatus::SessionCompleted->value,
            NotaryRequestStatus::AttorneySigning->value,
            NotaryRequestStatus::AttorneyApproved->value,
        ];

        return [
            'awaitingFinalization' => $awaitingFinalization,
            'recentlyNotarized' => $recentlyNotarized,
            'totalByStatus' => $totalByStatus,
            'totalRequests' => $totalByStatus->sum(),
            'totalNotarized' => (int) ($totalByStatus[NotaryRequestStatus::Notarized->value] ?? 0),
            'totalPending' => (int) ($totalByStatus[NotaryRequestStatus::Digitalized->value] ?? 0),
            'totalInProgress' => collect($inProgressStatuses)
                ->sum(fn (string $status): int => (int) ($totalByStatus[$status] ?? 0)),
        ];
    }
}; ?>

<x-admin.page>

    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ __('Notary Admin Dashboard') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Manage finalization of notarizations and blockchain storage.') }}</p>
        </div>
        <div class="text-sm font-medium text-zinc-400 dark:text-zinc-500">
            {{ now()->format('l, F j, Y') }}
        </div>
    </div>

    {{-- Stat Cards --}}
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Total notarizations') }}</p>
            <p class="mt-2 text-3xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ $totalRequests }}</p>
        </div>
        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Awaiting Finalization') }}</p>
            <p class="mt-2 text-3xl font-bold tabular-nums text-amber-600 dark:text-amber-400">{{ $totalPending }}</p>
        </div>
        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Notarized') }}</p>
            <p class="mt-2 text-3xl font-bold tabular-nums text-emerald-600 dark:text-emerald-400">{{ $totalNotarized }}</p>
        </div>
        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('In Progress') }}</p>
            <p class="mt-2 text-3xl font-bold tabular-nums text-blue-600 dark:text-blue-400">{{ $totalInProgress }}</p>
        </div>
    </div>

    {{-- Awaiting Finalization --}}
    <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
        <h2 class="text-base font-bold text-zinc-900 dark:text-white">{{ __('Awaiting Finalization') }}</h2>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('These notarizations have been digitally completed by the attorney and are ready for finalization and blockchain storage.') }}</p>

        @if ($awaitingFinalization->isEmpty())
            <div class="mt-4 rounded-xl border border-dashed border-zinc-300 px-4 py-8 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                {{ __('No notarizations awaiting finalization.') }}
            </div>
        @else
            <div class="mt-4 space-y-3">
                @foreach ($awaitingFinalization as $request)
                    <div class="flex items-center justify-between rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-4 dark:border-zinc-700 dark:bg-zinc-800/40">
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $request->title }}</div>
                            <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ __('Organization') }}: {{ $request->organization?->name ?? '—' }}
                                <span class="mx-1.5 text-zinc-300 dark:text-zinc-600">•</span>
                                {{ __('Client') }}: {{ $request->requester?->name ?? '—' }}
                                <span class="mx-1.5 text-zinc-300 dark:text-zinc-600">•</span>
                                {{ __('Attorney') }}: {{ $request->notary?->name ?? '—' }}
                                <span class="mx-1.5 text-zinc-300 dark:text-zinc-600">•</span>
                                {{ __('Attorney reviewed') }}: {{ $request->approved_at?->diffForHumans() ?? '—' }}
                                <span class="mx-1.5 text-zinc-300 dark:text-zinc-600">•</span>
                                {{ trans_choice(':count document|:count documents', $request->documents->count(), ['count' => $request->documents->count()]) }}
                            </div>
                        </div>
                        <flux:button variant="primary" size="sm" :href="route('notary-requests.show', $request)" wire:navigate>
                            {{ __('Review & Finalize') }}
                        </flux:button>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Recently Notarized --}}
    <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
        <h2 class="text-base font-bold text-zinc-900 dark:text-white">{{ __('Recently Notarized') }}</h2>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Completed notarizations with blockchain proof.') }}</p>

        @if ($recentlyNotarized->isEmpty())
            <div class="mt-4 rounded-xl border border-dashed border-zinc-300 px-4 py-8 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                {{ __('No completed notarizations yet.') }}
            </div>
        @else
            <div class="mt-4 space-y-2">
                @foreach ($recentlyNotarized as $request)
                    <a href="{{ route('notary-requests.show', $request) }}" wire:navigate class="flex items-center justify-between rounded-lg border border-zinc-100 px-4 py-3 transition hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/40">
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $request->title }}</div>
                            <div class="mt-0.5 text-xs text-zinc-400 dark:text-zinc-500">
                                {{ __('Organization') }}: {{ $request->organization?->name ?? '—' }}
                                <span class="mx-1.5 text-zinc-300 dark:text-zinc-600">•</span>
                                {{ $request->completed_at?->format('M j, Y g:i A') ?? '—' }}
                            </div>
                        </div>
                        <span class="ml-3 inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400">
                            {{ __('Notarized') }}
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

</x-admin.page>
