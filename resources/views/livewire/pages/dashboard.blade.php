<?php

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentSigner;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    private function statsCacheKey(string $suffix): string
    {
        return 'dashboard:'.$suffix.':org:'.(string) auth()->user()?->organization_id;
    }

    /**
     * @return array{
     *   total_documents: int,
     *   by_status: array<string, int>,
     *   total_signers: int,
     *   signed_signers: int,
     *   pending_signers: int
     * }
     */
    private function cachedDashboardStats(): array
    {
        return Cache::remember($this->statsCacheKey('stats'), now()->addMinutes(3), function (): array {
            $organizationId = auth()->user()?->organization_id;
            $statusCounts = Document::query()
                ->where('organization_id', $organizationId)
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status');

            $signerCounts = DocumentSigner::query()
                ->whereHas('document', fn ($query) => $query->where('organization_id', $organizationId))
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status');

            $totalDocuments = (int) $statusCounts->sum();
            $pendingSigners = (int) ($signerCounts[DocumentSignerStatus::Pending->value] ?? 0);
            $signedSigners = (int) ($signerCounts[DocumentSignerStatus::Signed->value] ?? 0);

            return [
                'total_documents' => $totalDocuments,
                'by_status' => [
                    DocumentStatus::Draft->value => (int) ($statusCounts[DocumentStatus::Draft->value] ?? 0),
                    DocumentStatus::Pending->value => (int) ($statusCounts[DocumentStatus::Pending->value] ?? 0),
                    DocumentStatus::Completed->value => (int) ($statusCounts[DocumentStatus::Completed->value] ?? 0),
                    DocumentStatus::Declined->value => (int) ($statusCounts[DocumentStatus::Declined->value] ?? 0),
                    DocumentStatus::Cancelled->value => (int) ($statusCounts[DocumentStatus::Cancelled->value] ?? 0),
                    DocumentStatus::Archived->value => (int) ($statusCounts[DocumentStatus::Archived->value] ?? 0),
                ],
                'total_signers' => $pendingSigners + $signedSigners,
                'signed_signers' => $signedSigners,
                'pending_signers' => $pendingSigners,
            ];
        });
    }

    #[Computed]
    public function headerGreeting(): string
    {
        $hour = now()->hour;

        if ($hour >= 5 && $hour < 12) {
            return __('Good morning');
        }

        if ($hour >= 12 && $hour < 18) {
            return __('Good afternoon');
        }

        return __('Good evening');
    }

    #[Computed]
    public function headerGreetingIcon(): string
    {
        $hour = now()->hour;

        if ($hour >= 5 && $hour < 18) {
            return 'sun';
        }

        return 'moon';
    }

    #[Computed]
    public function totalDocuments(): int
    {
        return $this->cachedDashboardStats()['total_documents'];
    }

    #[Computed]
    public function completedDocuments(): int
    {
        return $this->cachedDashboardStats()['by_status'][DocumentStatus::Completed->value];
    }

    #[Computed]
    public function pendingDocuments(): int
    {
        return $this->cachedDashboardStats()['by_status'][DocumentStatus::Pending->value];
    }

    #[Computed]
    public function draftDocuments(): int
    {
        return $this->cachedDashboardStats()['by_status'][DocumentStatus::Draft->value];
    }

    #[Computed]
    public function completionRate(): int
    {
        $total = $this->totalDocuments;
        if ($total === 0) {
            return 0;
        }

        return (int) round(($this->completedDocuments / $total) * 100);
    }

    #[Computed]
    public function pendingRate(): int
    {
        $total = $this->totalDocuments;
        if ($total === 0) {
            return 0;
        }

        return (int) round(($this->pendingDocuments / $total) * 100);
    }

    #[Computed]
    public function draftRate(): int
    {
        $total = $this->totalDocuments;
        if ($total === 0) {
            return 0;
        }

        return (int) round(($this->draftDocuments / $total) * 100);
    }

    #[Computed]
    public function totalSigners(): int
    {
        return $this->cachedDashboardStats()['total_signers'];
    }

    #[Computed]
    public function signedSigners(): int
    {
        return $this->cachedDashboardStats()['signed_signers'];
    }

    #[Computed]
    public function pendingSigners(): int
    {
        return $this->cachedDashboardStats()['pending_signers'];
    }

    #[Computed]
    public function declinedDocuments(): int
    {
        return $this->cachedDashboardStats()['by_status'][DocumentStatus::Declined->value];
    }

    #[Computed]
    public function cancelledDocuments(): int
    {
        return $this->cachedDashboardStats()['by_status'][DocumentStatus::Cancelled->value];
    }

    #[Computed]
    public function archivedDocuments(): int
    {
        return $this->cachedDashboardStats()['by_status'][DocumentStatus::Archived->value];
    }

    #[Computed]
    public function statusSegments(): array
    {
        $total = $this->totalDocuments;
        $statusMap = $this->cachedDashboardStats()['by_status'];

        $segments = [
            ['label' => __('Completed'), 'status' => DocumentStatus::Completed, 'color' => '#10b981'],
            ['label' => __('Pending'), 'status' => DocumentStatus::Pending, 'color' => '#f59e0b'],
            ['label' => __('Draft'), 'status' => DocumentStatus::Draft, 'color' => '#71717a'],
            ['label' => __('Declined'), 'status' => DocumentStatus::Declined, 'color' => '#f43f5e'],
            ['label' => __('Cancelled'), 'status' => DocumentStatus::Cancelled, 'color' => '#f97316'],
            ['label' => __('Archived'), 'status' => DocumentStatus::Archived, 'color' => '#64748b'],
        ];

        $result = [];

        foreach ($segments as $segment) {
            $value = $statusMap[$segment['status']->value] ?? 0;

            $result[] = [
                'label' => $segment['label'],
                'value' => $value,
                'color' => $segment['color'],
                'percentage' => $total > 0 ? (int) round(($value / $total) * 100) : 0,
            ];
        }

        return $result;
    }

    /**
     * @return array{labels: list<string>, values: list<int>, colors: list<string>}
     */
    #[Computed]
    public function statusChartPayload(): array
    {
        return [
            'labels' => array_column($this->statusSegments, 'label'),
            'values' => array_column($this->statusSegments, 'value'),
            'colors' => array_column($this->statusSegments, 'color'),
        ];
    }

    #[Computed]
    public function recentDocuments()
    {
        return Document::query()
            ->where('organization_id', auth()->user()?->organization_id)
            ->withCount('documentSigners')
            ->latest()
            ->limit(6)
            ->get();
    }

    #[Computed]
    public function recentSignedDocuments()
    {
        return Document::query()
            ->where('organization_id', auth()->user()?->organization_id)
            ->where('status', DocumentStatus::Completed)
            ->latest('updated_at')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function recentUploads()
    {
        return Document::query()
            ->where('organization_id', auth()->user()?->organization_id)
            ->latest('created_at')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function mostActiveSigners()
    {
        return DocumentSigner::query()
            ->selectRaw('email, MAX(name) as name, COUNT(*) as total_requests, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as signed_requests', [DocumentSignerStatus::Signed->value])
            ->whereHas('document', fn ($q) => $q->where('organization_id', auth()->user()?->organization_id))
            ->groupBy('email')
            ->orderByDesc('signed_requests')
            ->orderByDesc('total_requests')
            ->limit(5)
            ->get();
    }

    /**
     * @return array{
     *   labels: list<string>,
     *   weeklyValues: list<int>,
     *   monthlyValues: list<int>
     * }
     */
    #[Computed]
    public function activityChartPayload(): array
    {
        $organizationId = auth()->user()?->organization_id;
        $now = now();
        $startOfWindow = $now->copy()->startOfMonth()->subMonths(5)->startOfDay();

        $labels = [];
        $weeklyValues = [];
        $monthlyValues = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = $now->copy()->startOfMonth()->subMonths($i);
            $labels[] = $month->format('M');
            $monthlyCount = Document::query()
                ->where('organization_id', $organizationId)
                ->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                ->count();
            $monthlyValues[] = $monthlyCount;

            $weeksInMonth = max(1, (int) ceil($month->daysInMonth / 7));
            $weeklyValues[] = (int) round($monthlyCount / $weeksInMonth);
        }

        return [
            'labels' => $labels,
            'weeklyValues' => $weeklyValues,
            'monthlyValues' => $monthlyValues,
        ];
    }

    #[Computed]
    public function signerCompletionRate(): int
    {
        if ($this->totalSigners === 0) {
            return 0;
        }

        return (int) round(($this->signedSigners / $this->totalSigners) * 100);
    }

    #[Computed]
    public function rejectedDocuments(): int
    {
        return $this->declinedDocuments;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-1 sm:gap-7">

    {{-- ── Page header ── --}}
    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div class="space-y-1">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                    {{ $this->headerGreeting }}, {{ auth()->user()?->name ?? __('Welcome') }} <span aria-hidden="true">👋</span> {{ __('Welcome to DocuTrust!') }}
                </span>
                @if ($this->headerGreetingIcon === 'sun')
                    <svg class="ml-2 h-5 w-5 text-yellow-400 animate-[pulse_3s_ease-in-out_infinite]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1.5m0 15V21m6.364-14.364-1.06 1.06M6.696 17.304l-1.06 1.06M21 12h-1.5m-15 0H3m15.364 5.304-1.06-1.06M6.696 6.696l-1.06-1.06M16.5 12a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z"/>
                    </svg>
                @else
                    <svg class="ml-2 h-5 w-5 text-indigo-400 animate-[pulse_3s_ease-in-out_infinite]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9 9 0 0 1 11.998 2.25a9 9 0 1 0 9.754 12.752Z"/>
                    </svg>
                @endif
            </div>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __("Here's what's happening with your documents today.") }}
            </p>
        </div>
        <p class="text-xs font-medium text-zinc-400 dark:text-zinc-500 sm:text-right">
            {{ now()->format('l, F j, Y') }}
        </p>
    </div>

    {{-- ── Quick-action cards ── --}}
    <div class="grid gap-4 sm:grid-cols-2">

        {{-- Upload & send --}}
        <a href="{{ route('documents.create') }}"
           wire:navigate
           class="group relative overflow-hidden rounded-2xl border border-teal-200 bg-gradient-to-br from-teal-500 to-emerald-600 p-6 shadow-sm transition-all duration-200 hover:shadow-md hover:brightness-105 dark:border-teal-700 dark:from-teal-600 dark:to-emerald-700">
            {{-- decorative circle --}}
            <span class="pointer-events-none absolute -right-6 -top-6 h-28 w-28 rounded-full bg-white/10 transition-transform duration-300 group-hover:scale-125"></span>
            <span class="pointer-events-none absolute -bottom-8 -right-2 h-20 w-20 rounded-full bg-white/5"></span>

            <div class="relative flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-teal-100">
                        {{ __('New document') }}
                    </p>
                    <p class="mt-2 text-xl font-bold leading-snug text-white">
                        {{ __('Upload & send for signing') }}
                    </p>
                    <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-teal-100 transition-gap group-hover:gap-2">
                        {{ __('Get started') }}
                        <svg class="h-4 w-4 transition-transform duration-200 group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                    </span>
                </div>
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/20 backdrop-blur-sm">
                    <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
                </div>
            </div>
        </a>

        {{-- Verify signatures --}}
        <a href="{{ route('verify.index') }}"
           wire:navigate
           class="group relative overflow-hidden rounded-2xl border border-violet-200 bg-gradient-to-br from-violet-500 to-indigo-600 p-6 shadow-sm transition-all duration-200 hover:shadow-md hover:brightness-105 dark:border-violet-700 dark:from-violet-600 dark:to-indigo-700">
            <span class="pointer-events-none absolute -right-6 -top-6 h-28 w-28 rounded-full bg-white/10 transition-transform duration-300 group-hover:scale-125"></span>
            <span class="pointer-events-none absolute -bottom-8 -right-2 h-20 w-20 rounded-full bg-white/5"></span>

            <div class="relative flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-violet-100">
                        {{ __('Verify signatures') }}
                    </p>
                    <p class="mt-2 text-xl font-bold leading-snug text-white">
                        {{ __('Check any signature code') }}
                    </p>
                    <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-violet-100 transition-gap group-hover:gap-2">
                        {{ __('Open verify') }}
                        <svg class="h-4 w-4 transition-transform duration-200 group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                    </span>
                </div>
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/20 backdrop-blur-sm">
                    <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9 12.75 2.25 2.25 4.5-4.5m0 0a9 9 0 1 1-12.728 0 9 9 0 0 1 12.728 0Z"/></svg>
                </div>
            </div>
        </a>

    </div>

    {{-- ── Analytics snapshot ── --}}
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-emerald-200/70 bg-gradient-to-br from-emerald-50 to-white p-5 shadow-sm dark:border-emerald-900/40 dark:from-emerald-950/25 dark:to-zinc-900">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-widest text-emerald-700 dark:text-emerald-400">{{ __('Completion health') }}</p>
                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                    {{ $this->completionRate }}%
                </span>
            </div>
            <p class="mt-3 text-2xl font-bold tracking-tight text-emerald-700 dark:text-emerald-300">{{ $this->completedDocuments }}</p>
            <p class="text-sm text-emerald-700/80 dark:text-emerald-400/90">{{ __('documents completed') }}</p>
            <div class="mt-4 h-2 w-full overflow-hidden rounded-full bg-emerald-100 dark:bg-emerald-900/40">
                <div class="h-full rounded-full bg-emerald-500 transition-all duration-500" style="width: {{ $this->completionRate }}%"></div>
            </div>
        </div>

        <div class="rounded-2xl border border-amber-200/70 bg-gradient-to-br from-amber-50 to-white p-5 shadow-sm dark:border-amber-900/40 dark:from-amber-950/25 dark:to-zinc-900">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-widest text-amber-700 dark:text-amber-400">{{ __('Action needed') }}</p>
                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-semibold text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                    {{ $this->pendingRate }}%
                </span>
            </div>
            <p class="mt-3 text-2xl font-bold tracking-tight text-amber-700 dark:text-amber-300">{{ $this->pendingDocuments }}</p>
            <p class="text-sm text-amber-700/80 dark:text-amber-400/90">{{ __('pending documents') }}</p>
            <div class="mt-4 h-2 w-full overflow-hidden rounded-full bg-amber-100 dark:bg-amber-900/40">
                <div class="h-full rounded-full bg-amber-500 transition-all duration-500" style="width: {{ $this->pendingRate }}%"></div>
            </div>
        </div>

        <div class="rounded-2xl border border-violet-200/70 bg-gradient-to-br from-violet-50 to-white p-5 shadow-sm dark:border-violet-900/40 dark:from-violet-950/25 dark:to-zinc-900">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-widest text-violet-700 dark:text-violet-400">{{ __('In drafting') }}</p>
                <span class="rounded-full bg-violet-100 px-2.5 py-1 text-[11px] font-semibold text-violet-700 dark:bg-violet-900/40 dark:text-violet-300">
                    {{ $this->draftRate }}%
                </span>
            </div>
            <p class="mt-3 text-2xl font-bold tracking-tight text-violet-700 dark:text-violet-300">{{ $this->draftDocuments }}</p>
            <p class="text-sm text-violet-700/80 dark:text-violet-400/90">{{ __('draft documents') }}</p>
            <div class="mt-4 h-2 w-full overflow-hidden rounded-full bg-violet-100 dark:bg-violet-900/40">
                <div class="h-full rounded-full bg-violet-500 transition-all duration-500" style="width: {{ $this->draftRate }}%"></div>
            </div>
        </div>
    </div>

    {{-- ── Stat counters ── --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">

        {{-- Draft --}}
        <div class="flex flex-col gap-3 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">{{ __('Draft') }}</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                    <svg class="h-4 w-4 text-zinc-400 dark:text-zinc-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
                </span>
            </div>
            <p class="text-3xl font-bold tabular-nums tracking-tight text-zinc-700 dark:text-zinc-300">{{ $this->draftDocuments }}</p>
            <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('not yet sent') }}</p>
        </div>

        {{-- Completed --}}
        <div class="flex flex-col gap-3 rounded-2xl border border-emerald-200/80 bg-white p-5 shadow-sm dark:border-emerald-900/40 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-widest text-emerald-600 dark:text-emerald-400">{{ __('Completed') }}</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-950/50">
                    <svg class="h-4 w-4 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                </span>
            </div>
            <p class="text-3xl font-bold tabular-nums tracking-tight text-emerald-600 dark:text-emerald-400">{{ $this->completedDocuments }}</p>
            <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ $this->completionRate }}% {{ __('completion rate') }}</p>
        </div>

        {{-- Pending --}}
        <div class="flex flex-col gap-3 rounded-2xl border border-amber-200/80 bg-white p-5 shadow-sm dark:border-amber-900/40 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-widest text-amber-600 dark:text-amber-400">{{ __('Pending') }}</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 dark:bg-amber-950/50">
                    <svg class="h-4 w-4 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                </span>
            </div>
            <p class="text-3xl font-bold tabular-nums tracking-tight text-amber-600 dark:text-amber-400">{{ $this->pendingDocuments }}</p>
            <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('awaiting signatures') }}</p>
        </div>

        {{-- Rejected --}}
        <div class="flex flex-col gap-3 rounded-2xl border border-rose-200/80 bg-white p-5 shadow-sm dark:border-rose-900/40 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-widest text-rose-600 dark:text-rose-400">{{ __('Rejected') }}</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 dark:bg-rose-950/40">
                    <svg class="h-4 w-4 text-rose-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </span>
            </div>
            <p class="text-3xl font-bold tabular-nums tracking-tight text-rose-600 dark:text-rose-400">{{ $this->rejectedDocuments }}</p>
            <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('declined by signer') }}</p>
        </div>

    </div>

    <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div class="mb-4">
            <h3 class="text-base font-bold tracking-tight text-zinc-900 dark:text-zinc-50">{{ __('Activity trend') }}</h3>
            <p class="mt-0.5 text-sm text-zinc-400 dark:text-zinc-500">{{ __('Average created per week and total created per month (last 6 months)') }}</p>
        </div>
        <div wire:ignore>
            <canvas id="docutrust-activity-chart" class="max-h-80 w-full" data-chart="@json($this->activityChartPayload)"></canvas>
        </div>
    </div>

    {{-- ── Middle row: Status overview + Signer progress ── --}}
    <div class="grid gap-4 lg:grid-cols-5">

        {{-- Status overview (wider) --}}
        <div class="flex flex-col gap-5 rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 lg:col-span-3">
            <div>
                <h2 class="text-base font-bold tracking-tight text-zinc-900 dark:text-zinc-50">{{ __('Status overview') }}</h2>
                <p class="mt-0.5 text-sm text-zinc-400 dark:text-zinc-500">{{ __('Where your pipeline is busiest right now') }}</p>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                {{-- Needs action --}}
                <div class="flex flex-col gap-2 rounded-xl border border-amber-100 bg-amber-50/60 p-4 dark:border-amber-900/30 dark:bg-amber-950/20">
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/40">
                        <svg class="h-3.5 w-3.5 text-amber-600 dark:text-amber-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
                    </div>
                    <p class="text-2xl font-bold tabular-nums text-amber-700 dark:text-amber-300">{{ $this->pendingDocuments }}</p>
                    <p class="text-xs font-medium text-amber-600/80 dark:text-amber-400/80">{{ __('Needs action') }}</p>
                </div>

                {{-- Draft --}}
                <div class="flex flex-col gap-2 rounded-xl border border-violet-100 bg-violet-50/60 p-4 dark:border-violet-900/30 dark:bg-violet-950/20">
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-violet-100 dark:bg-violet-900/40">
                        <svg class="h-3.5 w-3.5 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                    </div>
                    <p class="text-2xl font-bold tabular-nums text-violet-700 dark:text-violet-300">{{ $this->draftDocuments }}</p>
                    <p class="text-xs font-medium text-violet-600/80 dark:text-violet-400/80">{{ __('Draft') }}</p>
                </div>

                {{-- Completed --}}
                <div class="flex flex-col gap-2 rounded-xl border border-emerald-100 bg-emerald-50/60 p-4 dark:border-emerald-900/30 dark:bg-emerald-950/20">
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/40">
                        <svg class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    </div>
                    <p class="text-2xl font-bold tabular-nums text-emerald-700 dark:text-emerald-300">{{ $this->completedDocuments }}</p>
                    <p class="text-xs font-medium text-emerald-600/80 dark:text-emerald-400/80">{{ __('Completed') }}</p>
                </div>

                {{-- Declined --}}
                <div class="flex flex-col gap-2 rounded-xl border border-rose-100 bg-rose-50/60 p-4 dark:border-rose-900/30 dark:bg-rose-950/20">
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-rose-100 dark:bg-rose-900/40">
                        <svg class="h-3.5 w-3.5 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </div>
                    <p class="text-2xl font-bold tabular-nums text-rose-700 dark:text-rose-300">{{ $this->declinedDocuments }}</p>
                    <p class="text-xs font-medium text-rose-600/80 dark:text-rose-400/80">{{ __('Declined') }}</p>
                </div>

                {{-- Cancelled --}}
                <div class="flex flex-col gap-2 rounded-xl border border-orange-100 bg-orange-50/60 p-4 dark:border-orange-900/30 dark:bg-orange-950/20">
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-orange-100 dark:bg-orange-900/40">
                        <svg class="h-3.5 w-3.5 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    </div>
                    <p class="text-2xl font-bold tabular-nums text-orange-700 dark:text-orange-300">{{ $this->cancelledDocuments }}</p>
                    <p class="text-xs font-medium text-orange-600/80 dark:text-orange-400/80">{{ __('Cancelled') }}</p>
                </div>

                {{-- Archived --}}
                <div class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-700/30 dark:bg-slate-800/20">
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100 dark:bg-slate-800">
                        <svg class="h-3.5 w-3.5 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/></svg>
                    </div>
                    <p class="text-2xl font-bold tabular-nums text-slate-600 dark:text-slate-300">{{ $this->archivedDocuments }}</p>
                    <p class="text-xs font-medium text-slate-500/80 dark:text-slate-400/80">{{ __('Archived') }}</p>
                </div>
            </div>
        </div>

        {{-- Signer progress (narrower) --}}
        <div class="flex flex-col gap-5 rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 lg:col-span-2">
            <div>
                <h2 class="text-base font-bold tracking-tight text-zinc-900 dark:text-zinc-50">{{ __('Signer progress') }}</h2>
                <p class="mt-0.5 text-sm text-zinc-400 dark:text-zinc-500">{{ __('Signed vs total requests') }}</p>
            </div>

            {{-- Big rate + donut-style ring --}}
            <div class="flex items-center gap-5">
                <div class="relative flex h-24 w-24 shrink-0 items-center justify-center">
                    {{-- SVG ring --}}
                    <svg class="absolute inset-0 h-full w-full -rotate-90" viewBox="0 0 36 36">
                        <circle cx="18" cy="18" r="15.9"
                                fill="none"
                                stroke="currentColor"
                                class="text-zinc-100 dark:text-zinc-800"
                                stroke-width="3.5"/>
                        <circle cx="18" cy="18" r="15.9"
                                fill="none"
                                stroke="currentColor"
                                class="text-emerald-500"
                                stroke-width="3.5"
                                stroke-dasharray="{{ $this->signerCompletionRate }} {{ 100 - $this->signerCompletionRate }}"
                                stroke-linecap="round"/>
                    </svg>
                    <div class="relative text-center">
                        <p class="text-xl font-bold leading-none text-zinc-900 dark:text-zinc-100">{{ $this->signerCompletionRate }}%</p>
                        <p class="mt-0.5 text-[10px] font-medium text-zinc-400">{{ __('signed') }}</p>
                    </div>
                </div>

                <div class="flex flex-1 flex-col gap-3">
                    <div class="flex items-center justify-between rounded-xl bg-zinc-50 px-3 py-2.5 dark:bg-zinc-800">
                        <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Total signers') }}</span>
                        <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100">{{ $this->totalSigners }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded-xl bg-emerald-50 px-3 py-2.5 dark:bg-emerald-950/30">
                        <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">{{ __('Signed') }}</span>
                        <span class="text-sm font-bold text-emerald-700 dark:text-emerald-300">{{ $this->signedSigners }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded-xl bg-amber-50 px-3 py-2.5 dark:bg-amber-950/20">
                        <span class="text-xs font-medium text-amber-600 dark:text-amber-400">{{ __('Pending') }}</span>
                        <span class="text-sm font-bold text-amber-700 dark:text-amber-300">{{ $this->pendingSigners }}</span>
                    </div>
                </div>
            </div>

            {{-- Progress bar --}}
            <div>
                <div class="h-2.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <div class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-emerald-600 transition-all duration-500"
                         style="width: {{ $this->signerCompletionRate }}%"></div>
                </div>
                <p class="mt-2 text-xs text-zinc-400 dark:text-zinc-500">
                    {{ $this->signedSigners }} {{ __('of') }} {{ $this->totalSigners }} {{ __('signers completed') }}
                </p>
            </div>

            <div class="border-t border-zinc-100 pt-4 dark:border-zinc-800">
                <h4 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Most active signers') }}</h4>
                <div class="mt-3 space-y-2">
                    @forelse ($this->mostActiveSigners as $signer)
                        <div class="flex items-center justify-between rounded-lg bg-zinc-50 px-3 py-2 text-sm dark:bg-zinc-800/60">
                            <span class="truncate text-zinc-700 dark:text-zinc-200">{{ $signer->name ?: $signer->email }}</span>
                            <span class="ml-3 shrink-0 text-zinc-500 dark:text-zinc-400">{{ $signer->signed_requests }}/{{ $signer->total_requests }}</span>
                        </div>
                    @empty
                        <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('No signer activity yet.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

    </div>

    {{-- ── Bottom row: Recent docs + Status chart ── --}}
    <div class="grid gap-4 pb-4 lg:grid-cols-2">

        {{-- Recent activity --}}
        <div class="flex flex-col gap-4 rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold tracking-tight text-zinc-900 dark:text-zinc-50">{{ __('Recent activity') }}</h3>
                    <p class="mt-0.5 text-sm text-zinc-400 dark:text-zinc-500">{{ __('Recently signed and recently uploaded documents') }}</p>
                </div>
                <a href="{{ route('documents.index') }}" wire:navigate
                   class="rounded-lg px-3 py-1.5 text-xs font-semibold text-teal-700 transition hover:bg-teal-50 dark:text-teal-400 dark:hover:bg-teal-900/20">
                    {{ __('View all') }} →
                </a>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <h4 class="mb-2 text-xs font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">{{ __('Recently signed') }}</h4>
                    <div class="space-y-2">
                        @forelse ($this->recentSignedDocuments as $document)
                            <a href="{{ route('documents.show', $document) }}" wire:navigate class="group flex items-center justify-between rounded-xl border border-transparent px-3 py-2 transition-all duration-150 hover:border-zinc-200 hover:bg-zinc-50/80 dark:hover:border-zinc-700/60 dark:hover:bg-zinc-800/60">
                                <span class="truncate text-sm font-medium text-zinc-800 group-hover:text-zinc-900 dark:text-zinc-200 dark:group-hover:text-zinc-50">{{ $document->title }}</span>
                                <span class="ml-3 shrink-0 text-xs text-zinc-400 dark:text-zinc-500">{{ $document->updated_at?->diffForHumans() }}</span>
                            </a>
                        @empty
                            <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('No signed documents yet.') }}</p>
                        @endforelse
                    </div>
                </div>
                <div>
                    <h4 class="mb-2 text-xs font-semibold uppercase tracking-wider text-teal-600 dark:text-teal-400">{{ __('Recent uploads') }}</h4>
                    <div class="space-y-2">
                        @forelse ($this->recentUploads as $document)
                            <a href="{{ route('documents.show', $document) }}" wire:navigate class="group flex items-center justify-between rounded-xl border border-transparent px-3 py-2 transition-all duration-150 hover:border-zinc-200 hover:bg-zinc-50/80 dark:hover:border-zinc-700/60 dark:hover:bg-zinc-800/60">
                                <span class="truncate text-sm font-medium text-zinc-800 group-hover:text-zinc-900 dark:text-zinc-200 dark:group-hover:text-zinc-50">{{ $document->title }}</span>
                                <span class="ml-3 shrink-0 text-xs text-zinc-400 dark:text-zinc-500">{{ $document->created_at?->diffForHumans() }}</span>
                            </a>
                        @empty
                            <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('No uploads yet.') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Status chart --}}
        <div class="relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <span class="pointer-events-none absolute -right-10 -top-10 h-28 w-28 rounded-full bg-teal-100/70 blur-2xl dark:bg-teal-900/30"></span>
            <div>
                <h3 class="text-base font-bold tracking-tight text-zinc-900 dark:text-zinc-50">{{ __('Status breakdown') }}</h3>
                <p class="mt-0.5 text-sm text-zinc-400 dark:text-zinc-500">{{ __('Distribution across all document states') }}</p>
            </div>
            <div class="grid flex-1 gap-4 md:grid-cols-2">
                <div class="flex items-center justify-center" wire:ignore>
                    <canvas id="docutrust-status-pie"
                            class="max-h-72 w-full"
                            data-chart="@json($this->statusChartPayload)">
                    </canvas>
                </div>
                <div class="space-y-2.5">
                    @foreach ($this->statusSegments as $segment)
                        <div class="rounded-xl border border-zinc-100 bg-zinc-50/70 px-3 py-2 dark:border-zinc-800 dark:bg-zinc-800/60">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $segment['color'] }}"></span>
                                    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ $segment['label'] }}</p>
                                </div>
                                <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $segment['value'] }}</p>
                            </div>
                            <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-zinc-200/80 dark:bg-zinc-700/80">
                                <div class="h-full rounded-full transition-all duration-500"
                                     style="width: {{ $segment['percentage'] }}%; background-color: {{ $segment['color'] }}"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

    </div>

</div>
