<?php

use App\Services\Admin\PlatformDashboardService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(PlatformDashboardService $dashboard): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $this->data = $dashboard->payload();
    }
}; ?>

@php
    $kpis = $data['kpis'] ?? [];
    $actionQueue = $data['action_queue'] ?? [];
    $topOrganizations = $data['top_organizations'] ?? [];
    $trialsEndingSoon = $data['trials_ending_soon'] ?? [];
    $compliance = $data['compliance'] ?? [];
    $signing = $data['signing'] ?? [];
    $recentActivity = $data['recent_activity'] ?? [];
    $awaitingFinalization = $data['awaiting_finalization'] ?? collect();
    $usersByRole = $kpis['users_by_role'] ?? [];
@endphp

<x-admin.page>

    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ __('Platform Dashboard') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Cross-tenant operations, triage queue, and platform health.') }}
            </p>
        </div>
        <div class="text-sm font-medium text-zinc-400 dark:text-zinc-500">
            {{ now()->format('l, F j, Y') }}
        </div>
    </div>

    {{-- Platform KPIs --}}
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-zinc-900">
            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('Organizations') }}</p>
            <p class="mt-2 text-3xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ $kpis['organizations_total'] ?? 0 }}</p>
            @if (($kpis['organizations_trial_ending'] ?? 0) > 0)
                <p class="mt-1 text-xs font-medium text-amber-600 dark:text-amber-400">
                    {{ trans_choice(':count trial ending soon|:count trials ending soon', $kpis['organizations_trial_ending'], ['count' => $kpis['organizations_trial_ending']]) }}
                </p>
            @endif
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-zinc-900">
            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('Users') }}</p>
            <p class="mt-2 text-3xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ $kpis['users_total'] ?? 0 }}</p>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                {{ $usersByRole['client'] ?? 0 }} {{ __('clients') }} · {{ $usersByRole['notary'] ?? 0 }} {{ __('notaries') }}
            </p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-zinc-900">
            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('Notarizations') }}</p>
            <p class="mt-2 text-3xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ $kpis['notary_requests_total'] ?? 0 }}</p>
            <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                {{ $kpis['notary_requests_awaiting_finalization'] ?? 0 }} {{ __('awaiting finalization') }}
            </p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-zinc-900">
            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('Documents') }}</p>
            <p class="mt-2 text-3xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ $kpis['documents_total'] ?? 0 }}</p>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                {{ $kpis['documents_completion_rate'] ?? 0 }}% {{ __('completed') }}
            </p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-zinc-900">
            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('Attorney apps') }}</p>
            <p class="mt-2 text-3xl font-bold tabular-nums text-amber-600 dark:text-amber-400">{{ $kpis['pending_attorney_applications'] ?? 0 }}</p>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('pending review') }}</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-zinc-900">
            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('Certificates') }}</p>
            <p class="mt-2 text-3xl font-bold tabular-nums text-emerald-600 dark:text-emerald-400">{{ $kpis['active_certificates'] ?? 0 }}</p>
            <p class="mt-1 text-xs text-rose-500 dark:text-rose-400">
                {{ $kpis['revoked_certificates'] ?? 0 }} {{ __('revoked') }}
            </p>
        </div>
    </div>

    {{-- Quick links --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <a href="{{ route('admin.users.index') }}" wire:navigate class="group rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition hover:border-indigo-200 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-indigo-500/30">
            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('Users') }}</p>
            <p class="mt-1 text-sm font-semibold text-zinc-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400">{{ __('Platform users') }}</p>
        </a>
        <a href="{{ route('admin.compliance.dashboard') }}" wire:navigate class="group rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition hover:border-teal-200 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-teal-500/30">
            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('Compliance') }}</p>
            <p class="mt-1 text-sm font-semibold text-zinc-900 group-hover:text-teal-600 dark:text-white dark:group-hover:text-teal-400">{{ __('Signature audit') }}</p>
        </a>
        <a href="{{ route('admin.attorney-applications.index', ['status' => 'pending']) }}" wire:navigate class="group rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition hover:border-amber-200 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-amber-500/30">
            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('Attorney apps') }}</p>
            <p class="mt-1 text-sm font-semibold text-zinc-900 group-hover:text-amber-600 dark:text-white dark:group-hover:text-amber-400">{{ __('Review applications') }}</p>
        </a>
        <a href="{{ route('admin.signing.dashboard') }}" wire:navigate class="group rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition hover:border-indigo-200 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-indigo-500/30">
            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('Signing') }}</p>
            <p class="mt-1 text-sm font-semibold text-zinc-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400">{{ __('Global signing metrics') }}</p>
        </a>
    </div>

    <div class="grid gap-5 lg:grid-cols-5">

        {{-- Action queue --}}
        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900 lg:col-span-3">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-zinc-900 dark:text-white">{{ __('Action queue') }}</h2>
                    <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Prioritized items requiring platform attention') }}</p>
                </div>
                <a href="{{ route('admin.enotary.dashboard') }}" wire:navigate class="text-xs font-semibold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">
                    {{ __('e-Notary ops') }}
                </a>
            </div>

            @if (count($actionQueue) === 0)
                <div class="rounded-xl border border-dashed border-zinc-300 px-4 py-8 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                    {{ __('No urgent items in the queue.') }}
                </div>
            @else
                <div class="space-y-2">
                    @foreach ($actionQueue as $item)
                        @php
                            $priorityBadge = match ($item['priority'] ?? 3) {
                                1 => 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400',
                                2 => 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400',
                                default => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400',
                            };
                        @endphp
                        <a href="{{ $item['url'] }}" wire:navigate class="flex items-start justify-between gap-3 rounded-lg border border-zinc-100 px-4 py-3 transition hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/50">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $item['title'] }}</p>
                                <p class="mt-0.5 truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $item['description'] }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase {{ $priorityBadge }}">
                                {{ match ($item['priority'] ?? 3) {
                                    1 => __('Urgent'),
                                    2 => __('Attention'),
                                    default => __('Review'),
                                } }}
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Compliance + signing snapshot --}}
        <div class="flex flex-col gap-5 lg:col-span-2">
            <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-base font-bold text-zinc-900 dark:text-white">{{ __('Compliance') }}</h2>
                        <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ $compliance['trust_level_label'] ?? '' }}</p>
                    </div>
                    <a href="{{ route('admin.compliance.dashboard') }}" wire:navigate class="text-xs font-semibold text-teal-600 dark:text-teal-400">{{ __('Details') }}</a>
                </div>
                <p class="mt-3 text-4xl font-bold tabular-nums text-teal-600 dark:text-teal-400">{{ $compliance['overall_score'] ?? 0 }}%</p>
                @if (($compliance['attention_count'] ?? 0) > 0)
                    <p class="mt-2 text-xs font-medium text-amber-600 dark:text-amber-400">
                        {{ trans_choice(':count category needs attention|:count categories need attention', $compliance['attention_count'], ['count' => $compliance['attention_count']]) }}
                    </p>
                    <ul class="mt-3 space-y-1.5">
                        @foreach ($compliance['attention_categories'] ?? [] as $category)
                            <li class="text-xs text-zinc-600 dark:text-zinc-300">
                                <span class="font-medium">{{ $category['title'] }}</span>
                                <span class="text-zinc-400">· {{ $category['status'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-2 text-xs text-emerald-600 dark:text-emerald-400">{{ __('All scored categories are ready.') }}</p>
                @endif
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
                <h2 class="text-base font-bold text-zinc-900 dark:text-white">{{ __('Signing health') }}</h2>
                <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Platform-wide signer completion') }}</p>
                <p class="mt-3 text-3xl font-bold tabular-nums text-indigo-600 dark:text-indigo-400">{{ $signing['signer_completion_rate'] ?? 0 }}%</p>
                <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-lg bg-zinc-50 px-2 py-2 dark:bg-zinc-800">
                        <p class="text-lg font-bold text-zinc-900 dark:text-white">{{ $signing['total_signers'] ?? 0 }}</p>
                        <p class="text-[10px] uppercase text-zinc-400">{{ __('Total') }}</p>
                    </div>
                    <div class="rounded-lg bg-emerald-50 px-2 py-2 dark:bg-emerald-950/30">
                        <p class="text-lg font-bold text-emerald-700 dark:text-emerald-300">{{ $signing['signed_signers'] ?? 0 }}</p>
                        <p class="text-[10px] uppercase text-emerald-600 dark:text-emerald-400">{{ __('Signed') }}</p>
                    </div>
                    <div class="rounded-lg bg-amber-50 px-2 py-2 dark:bg-amber-950/20">
                        <p class="text-lg font-bold text-amber-700 dark:text-amber-300">{{ $signing['pending_signers'] ?? 0 }}</p>
                        <p class="text-[10px] uppercase text-amber-600 dark:text-amber-400">{{ __('Pending') }}</p>
                    </div>
                </div>
                <a href="{{ route('documents.index') }}" wire:navigate class="mt-4 inline-flex text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                    {{ __('All documents') }}
                </a>
            </div>
        </div>
    </div>

    {{-- Org health + trials --}}
    <div class="grid gap-5 lg:grid-cols-2">
        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <h2 class="text-base font-bold text-zinc-900 dark:text-white">{{ __('Top organizations') }}</h2>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('By notarization volume') }}</p>
            <div class="mt-4 space-y-2">
                @forelse ($topOrganizations as $organization)
                    <div class="flex items-center justify-between rounded-lg border border-zinc-100 px-4 py-3 dark:border-zinc-800">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $organization['name'] }}</p>
                            <p class="text-xs text-zinc-400">{{ ucfirst($organization['plan'] ?? '') }} · {{ ucfirst($organization['subscription_status'] ?? '') }}</p>
                        </div>
                        <div class="ml-3 text-right text-xs tabular-nums text-zinc-500">
                            <p>{{ $organization['notary_requests_count'] }} {{ __('notarizations') }}</p>
                            <p>{{ $organization['users_count'] }} {{ __('users') }}</p>
                        </div>
                    </div>
                @empty
                    <p class="py-4 text-center text-sm text-zinc-400">{{ __('No organizations yet.') }}</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <h2 class="text-base font-bold text-zinc-900 dark:text-white">{{ __('Trials ending soon') }}</h2>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Within the next :days days', ['days' => 14]) }}</p>
            <div class="mt-4 space-y-2">
                @forelse ($trialsEndingSoon as $organization)
                    <div class="flex items-center justify-between rounded-lg border border-amber-100 bg-amber-50/50 px-4 py-3 dark:border-amber-900/30 dark:bg-amber-950/20">
                        <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $organization['name'] }}</p>
                        <p class="text-xs font-semibold text-amber-700 dark:text-amber-300">
                            @if ($organization['days_remaining'] !== null && $organization['days_remaining'] >= 0)
                                {{ trans_choice(':count day|:count days', $organization['days_remaining'], ['count' => $organization['days_remaining']]) }}
                            @else
                                {{ __('Expired') }}
                            @endif
                        </p>
                    </div>
                @empty
                    <p class="py-4 text-center text-sm text-zinc-400">{{ __('No trials ending in this window.') }}</p>
                @endforelse
            </div>
            @if (($kpis['failed_payments_recent'] ?? 0) > 0)
                <p class="mt-4 text-xs font-medium text-rose-600 dark:text-rose-400">
                    {{ trans_choice(':count failed payment in 30 days|:count failed payments in 30 days', $kpis['failed_payments_recent'], ['count' => $kpis['failed_payments_recent']]) }}
                </p>
            @endif
        </div>
    </div>

    {{-- Awaiting finalization preview --}}
    <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h2 class="text-base font-bold text-zinc-900 dark:text-white">{{ __('Awaiting finalization') }}</h2>
                <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Ready for blockchain storage') }}</p>
            </div>
            <a href="{{ route('admin.enotary.dashboard') }}" wire:navigate class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">{{ __('View all') }}</a>
        </div>

        @if ($awaitingFinalization->isEmpty())
            <div class="rounded-xl border border-dashed border-zinc-300 px-4 py-6 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                {{ __('No notarizations awaiting finalization.') }}
            </div>
        @else
            <div class="space-y-2">
                @foreach ($awaitingFinalization as $request)
                    <div class="flex items-center justify-between rounded-lg border border-zinc-100 px-4 py-3 dark:border-zinc-800">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $request->title }}</p>
                            <p class="text-xs text-zinc-400">{{ $request->organization?->name ?? '—' }}</p>
                        </div>
                        <flux:button variant="primary" size="sm" :href="route('notary-requests.show', $request)" wire:navigate>
                            {{ __('Review') }}
                        </flux:button>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Recent platform activity --}}
    <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
        <h2 class="text-base font-bold text-zinc-900 dark:text-white">{{ __('Recent platform activity') }}</h2>
        <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Latest documents and notarizations across all organizations') }}</p>
        <div class="mt-4 space-y-2">
            @forelse ($recentActivity as $item)
                <a href="{{ $item['url'] }}" wire:navigate class="flex items-center justify-between rounded-lg px-3 py-2.5 transition hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $item['title'] }}</p>
                        <p class="text-xs text-zinc-400">
                            {{ $item['subtitle'] ?? '—' }}
                            <span class="mx-1 text-zinc-300">·</span>
                            {{ $item['kind'] === 'document' ? __('Document') : __('Notarization') }}
                        </p>
                    </div>
                    <span class="ml-3 shrink-0 rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                        {{ ucfirst(str_replace('_', ' ', $item['status'] ?? '')) }}
                    </span>
                </a>
            @empty
                <p class="py-4 text-center text-sm text-zinc-400">{{ __('No recent activity.') }}</p>
            @endforelse
        </div>
    </div>

</x-admin.page>
