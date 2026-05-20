<?php

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\SignerCertificate;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    private function isGlobalPlatformStats(): bool
    {
        return auth()->user()?->isSuperAdmin() === true;
    }

    private function scopeDocumentsQuery($query)
    {
        if (! $this->isGlobalPlatformStats()) {
            $query->where('organization_id', auth()->user()?->organization_id);
        }

        return $query;
    }

    private function scopeDocumentRelationQuery($query)
    {
        if (! $this->isGlobalPlatformStats()) {
            $query->where('organization_id', auth()->user()?->organization_id);
        }

        return $query;
    }

    private function statsCacheKey(string $suffix): string
    {
        $scope = $this->isGlobalPlatformStats()
            ? 'platform'
            : 'org:'.(string) auth()->user()?->organization_id;

        return 'dashboard:'.$suffix.':'.$scope;
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
            $global = $this->isGlobalPlatformStats();

            $statusCounts = Document::query()
                ->when(! $global, fn ($query) => $query->where('organization_id', $organizationId))
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status');

            $signerCounts = DocumentSigner::query()
                ->when(! $global, fn ($query) => $query->whereHas(
                    'document',
                    fn ($documentQuery) => $documentQuery->where('organization_id', $organizationId)
                ))
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
        return $this->scopeDocumentsQuery(Document::query())
            ->withCount('documentSigners')
            ->latest()
            ->limit(6)
            ->get();
    }

    #[Computed]
    public function recentSignedDocuments()
    {
        return $this->scopeDocumentsQuery(Document::query())
            ->where('status', DocumentStatus::Completed)
            ->latest('updated_at')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function recentUploads()
    {
        return $this->scopeDocumentsQuery(Document::query())
            ->latest('created_at')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function mostActiveSigners()
    {
        return DocumentSigner::query()
            ->selectRaw('email, MAX(name) as name, COUNT(*) as total_requests, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as signed_requests', [DocumentSignerStatus::Signed->value])
            ->whereHas('document', fn ($q) => $this->scopeDocumentRelationQuery($q))
            ->groupBy('email')
            ->orderByDesc('signed_requests')
            ->orderByDesc('total_requests')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function totalActiveCertificates(): int
    {
        return SignerCertificate::query()
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->whereHas('documentSigner.document', fn ($query) => $this->scopeDocumentRelationQuery($query))
            ->count();
    }

    #[Computed]
    public function totalRevokedCertificates(): int
    {
        return SignerCertificate::query()
            ->where(function ($query): void {
                $query->where('status', 'revoked')->orWhereNotNull('revoked_at');
            })
            ->whereHas('documentSigner.document', fn ($query) => $this->scopeDocumentRelationQuery($query))
            ->count();
    }

    #[Computed]
    public function recentRevokedCertificates()
    {
        return SignerCertificate::query()
            ->with(['documentSigner.document'])
            ->where(function ($query): void {
                $query->where('status', 'revoked')->orWhereNotNull('revoked_at');
            })
            ->whereHas('documentSigner.document', fn ($query) => $this->scopeDocumentRelationQuery($query))
            ->latest('revoked_at')
            ->limit(4)
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
        $now = now();
        $startOfWindow = $now->copy()->startOfMonth()->subMonths(5)->startOfDay();

        $labels = [];
        $weeklyValues = [];
        $monthlyValues = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = $now->copy()->startOfMonth()->subMonths($i);
            $labels[] = $month->format('M');
            $monthlyCount = $this->scopeDocumentsQuery(Document::query())
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

<div class="flex h-full w-full flex-1 flex-col gap-8 px-2 py-4 font-sans sm:px-4 lg:px-6">

    {{-- ── 1. Page Header ── --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">
                    {{ $this->headerGreeting }}, {{ auth()->user()?->name ?? __('Welcome') }}
                </h1>
                @if ($this->headerGreetingIcon === 'sun')
                    <svg class="h-6 w-6 text-amber-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1.5m0 15V21m6.364-14.364-1.06 1.06M6.696 17.304l-1.06 1.06M21 12h-1.5m-15 0H3m15.364 5.304-1.06-1.06M6.696 6.696l-1.06-1.06M16.5 12a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z"/>
                    </svg>
                @else
                    <svg class="h-6 w-6 text-indigo-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9 9 0 0 1 11.998 2.25a9 9 0 1 0 9.754 12.752Z"/>
                    </svg>
                @endif
            </div>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __("Here's what's happening with your documents today.") }}
            </p>
        </div>
        <div class="text-sm font-medium text-zinc-400 dark:text-zinc-500">
            {{ now()->format('l, F j, Y') }}
        </div>
    </div>

    {{-- ── 2. Stat Cards Row (Lunoz style: icon right, big number, small label, badge) ── --}}
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">

        {{-- Total Documents --}}
        <div class="group relative rounded-xl bg-white p-6 shadow-sm transition-shadow duration-200 hover:shadow-md dark:bg-zinc-900">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Total Documents') }}</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ $this->totalDocuments }}</p>
                    <div class="mt-2">
                        <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400">
                            {{ $this->completionRate }}% {{ __('completed') }}
                        </span>
                    </div>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-50 dark:bg-indigo-950/30">
                    <svg class="h-6 w-6 text-indigo-400 dark:text-indigo-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                    </svg>
                </div>
            </div>
        </div>

        {{-- Completed --}}
        <div class="group relative rounded-xl bg-white p-6 shadow-sm transition-shadow duration-200 hover:shadow-md dark:bg-zinc-900">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Completed') }}</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-emerald-600 dark:text-emerald-400">{{ $this->completedDocuments }}</p>
                    <div class="mt-2">
                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400">
                            <svg class="mr-1 h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.577 4.878a.75.75 0 0 1 .919-.53l4.78 1.281a.75.75 0 0 1 .531.919l-1.281 4.78a.75.75 0 0 1-1.449-.387l.81-3.022a19.407 19.407 0 0 0-5.594 5.203.75.75 0 0 1-1.139.093L7 10.06l-4.72 4.72a.75.75 0 0 1-1.06-1.06l5.25-5.25a.75.75 0 0 1 1.06 0l3.046 3.046a20.902 20.902 0 0 1 5.441-5.185l-2.523.676a.75.75 0 0 1-.919-.53Z" clip-rule="evenodd"/></svg>
                            {{ $this->completionRate }}%
                        </span>
                    </div>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 dark:bg-emerald-950/30">
                    <svg class="h-6 w-6 text-emerald-400 dark:text-emerald-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                </div>
            </div>
        </div>

        {{-- Pending --}}
        <div class="group relative rounded-xl bg-white p-6 shadow-sm transition-shadow duration-200 hover:shadow-md dark:bg-zinc-900">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Pending') }}</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-amber-600 dark:text-amber-400">{{ $this->pendingDocuments }}</p>
                    <div class="mt-2">
                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-600 dark:bg-amber-950/40 dark:text-amber-400">
                            {{ $this->pendingRate }}% {{ __('of total') }}
                        </span>
                    </div>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-50 dark:bg-amber-950/30">
                    <svg class="h-6 w-6 text-amber-400 dark:text-amber-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                </div>
            </div>
        </div>

        {{-- Rejected --}}
        <div class="group relative rounded-xl bg-white p-6 shadow-sm transition-shadow duration-200 hover:shadow-md dark:bg-zinc-900">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Rejected') }}</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-rose-600 dark:text-rose-400">{{ $this->rejectedDocuments }}</p>
                    <div class="mt-2">
                        <span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-xs font-semibold text-rose-600 dark:bg-rose-950/40 dark:text-rose-400">
                            {{ __('declined') }}
                        </span>
                    </div>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-rose-50 dark:bg-rose-950/30">
                    <svg class="h-6 w-6 text-rose-400 dark:text-rose-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                </div>
            </div>
        </div>

    </div>

    {{-- ── 3. Quick Action Cards ── --}}
    <div class="grid gap-5 sm:grid-cols-2">

        {{-- Upload & send --}}
        <a href="{{ route('documents.create') }}"
           wire:navigate
           class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-blue-600 to-indigo-700 p-6 shadow-sm transition-all duration-200 hover:shadow-lg hover:shadow-blue-500/10 dark:from-blue-700 dark:to-indigo-800">
            <span class="pointer-events-none absolute -right-8 -top-8 h-32 w-32 rounded-full bg-white/5 transition-transform duration-300 group-hover:scale-110"></span>
            <span class="pointer-events-none absolute -bottom-6 -left-6 h-24 w-24 rounded-full bg-white/5"></span>

            <div class="relative flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-blue-200">
                        {{ __('New document') }}
                    </p>
                    <p class="mt-2 text-lg font-bold text-white">
                        {{ __('Upload & send for signing') }}
                    </p>
                    <span class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-blue-100 transition-all group-hover:gap-2.5">
                        {{ __('Get started') }}
                        <svg class="h-4 w-4 transition-transform duration-200 group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                    </span>
                </div>
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-white/10 backdrop-blur-sm">
                    <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
                </div>
            </div>
        </a>

        {{-- Verify signatures --}}
        <a href="{{ route('verify.index') }}"
           wire:navigate
           class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-teal-500 to-emerald-600 p-6 shadow-sm transition-all duration-200 hover:shadow-lg hover:shadow-teal-500/10 dark:from-teal-600 dark:to-emerald-700">
            <span class="pointer-events-none absolute -right-8 -top-8 h-32 w-32 rounded-full bg-white/5 transition-transform duration-300 group-hover:scale-110"></span>
            <span class="pointer-events-none absolute -bottom-6 -left-6 h-24 w-24 rounded-full bg-white/5"></span>

            <div class="relative flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-teal-100">
                        {{ __('Verify signatures') }}
                    </p>
                    <p class="mt-2 text-lg font-bold text-white">
                        {{ __('Check any signature code') }}
                    </p>
                    <span class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-teal-100 transition-all group-hover:gap-2.5">
                        {{ __('Open verify') }}
                        <svg class="h-4 w-4 transition-transform duration-200 group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                    </span>
                </div>
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-white/10 backdrop-blur-sm">
                    <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                </div>
            </div>
        </a>

    </div>

    {{-- ── 4. Activity Chart ── --}}
    <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
        <div class="mb-5 flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-zinc-900 dark:text-white">{{ __('Activity trend') }}</h3>
                <p class="mt-0.5 text-sm text-zinc-400 dark:text-zinc-500">{{ __('Average created per week and total created per month (last 6 months)') }}</p>
            </div>
        </div>
        <div wire:ignore>
            <canvas id="docutrust-activity-chart" class="max-h-80 w-full" data-chart="@json($this->activityChartPayload)"></canvas>
        </div>
    </div>

    {{-- ── 5. Two-Column: Status Overview + Signer Completion & Certificates ── --}}
    <div class="grid gap-5 lg:grid-cols-5">

        {{-- Left: Status overview with progress bars (Lunoz style) --}}
        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900 lg:col-span-3">
            <div class="mb-6">
                <h2 class="text-base font-bold text-zinc-900 dark:text-white">{{ __('Status overview') }}</h2>
                <p class="mt-0.5 text-sm text-zinc-400 dark:text-zinc-500">{{ __('Document distribution across all states') }}</p>
            </div>

            <div class="space-y-4">
                @foreach ($this->statusSegments as $segment)
                    <div>
                        <div class="mb-1.5 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $segment['color'] }}"></span>
                                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $segment['label'] }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-bold tabular-nums text-zinc-900 dark:text-white">{{ $segment['value'] }}</span>
                                <span class="inline-flex min-w-[40px] items-center justify-center rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                    {{ $segment['percentage'] }}%
                                </span>
                            </div>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                            <div class="h-full rounded-full transition-all duration-500" style="width: {{ $segment['percentage'] }}%; background-color: {{ $segment['color'] }}"></div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Status chart --}}
            <div class="mt-6 border-t border-zinc-100 pt-6 dark:border-zinc-800">
                <div wire:ignore>
                    <canvas id="docutrust-status-pie"
                            class="mx-auto max-h-56 w-full max-w-[240px]"
                            data-chart="@json($this->statusChartPayload)">
                    </canvas>
                </div>
            </div>
        </div>

        {{-- Right: Signer completion + Certificate stats --}}
        <div class="flex flex-col gap-5 lg:col-span-2">

            {{-- Signer completion ring --}}
            <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
                <h2 class="text-base font-bold text-zinc-900 dark:text-white">{{ __('Signer progress') }}</h2>
                <p class="mt-0.5 text-sm text-zinc-400 dark:text-zinc-500">{{ __('Signed vs total requests') }}</p>

                <div class="mt-5 flex items-center gap-5">
                    {{-- SVG Ring --}}
                    <div class="relative flex h-24 w-24 shrink-0 items-center justify-center">
                        <svg class="absolute inset-0 h-full w-full -rotate-90" viewBox="0 0 36 36">
                            <circle cx="18" cy="18" r="15.9" fill="none" stroke="currentColor" class="text-zinc-100 dark:text-zinc-800" stroke-width="3"/>
                            <circle cx="18" cy="18" r="15.9" fill="none" stroke="currentColor" class="text-indigo-500" stroke-width="3" stroke-dasharray="{{ $this->signerCompletionRate }} {{ 100 - $this->signerCompletionRate }}" stroke-linecap="round"/>
                        </svg>
                        <div class="relative text-center">
                            <p class="text-xl font-bold leading-none text-zinc-900 dark:text-white">{{ $this->signerCompletionRate }}%</p>
                        </div>
                    </div>

                    <div class="flex flex-1 flex-col gap-2.5">
                        <div class="flex items-center justify-between rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-800">
                            <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Total') }}</span>
                            <span class="text-sm font-bold text-zinc-900 dark:text-white">{{ $this->totalSigners }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-lg bg-emerald-50 px-3 py-2 dark:bg-emerald-950/30">
                            <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">{{ __('Signed') }}</span>
                            <span class="text-sm font-bold text-emerald-700 dark:text-emerald-300">{{ $this->signedSigners }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-lg bg-amber-50 px-3 py-2 dark:bg-amber-950/20">
                            <span class="text-xs font-medium text-amber-600 dark:text-amber-400">{{ __('Pending') }}</span>
                            <span class="text-sm font-bold text-amber-700 dark:text-amber-300">{{ $this->pendingSigners }}</span>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <div class="h-full rounded-full bg-indigo-500 transition-all duration-500" style="width: {{ $this->signerCompletionRate }}%"></div>
                    </div>
                    <p class="mt-1.5 text-xs text-zinc-400 dark:text-zinc-500">
                        {{ $this->signedSigners }} {{ __('of') }} {{ $this->totalSigners }} {{ __('signers completed') }}
                    </p>
                </div>
            </div>

            {{-- Certificate stats --}}
            <div class="grid grid-cols-2 gap-4">
                <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-zinc-900">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-950/30">
                        <svg class="h-4.5 w-4.5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/></svg>
                    </div>
                    <p class="mt-3 text-2xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ $this->totalActiveCertificates }}</p>
                    <p class="mt-0.5 text-xs font-medium text-zinc-400 dark:text-zinc-500">{{ __('Active certs') }}</p>
                </div>
                <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-zinc-900">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-rose-50 dark:bg-rose-950/30">
                        <svg class="h-4.5 w-4.5 text-rose-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0-10.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.75h-.152c-3.196 0-6.1-1.249-8.25-3.286Zm0 13.036h.008v.008H12v-.008Z"/></svg>
                    </div>
                    <p class="mt-3 text-2xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ $this->totalRevokedCertificates }}</p>
                    <p class="mt-0.5 text-xs font-medium text-zinc-400 dark:text-zinc-500">{{ __('Revoked') }}</p>
                </div>
            </div>

            {{-- Recent revocations --}}
            <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-zinc-900">
                <h4 class="text-sm font-bold text-zinc-900 dark:text-white">{{ __('Recent revocations') }}</h4>
                <p class="mt-0.5 text-xs text-zinc-400 dark:text-zinc-500">{{ __('Latest certificate trust changes') }}</p>
                <div class="mt-3 space-y-2">
                    @forelse ($this->recentRevokedCertificates as $certificate)
                        <div class="rounded-lg border border-rose-100 bg-rose-50/60 px-3 py-2.5 dark:border-rose-900/30 dark:bg-rose-950/20">
                            <p class="text-sm font-medium text-rose-800 dark:text-rose-200">{{ $certificate->documentSigner?->name ?? __('Unknown signer') }}</p>
                            <p class="mt-0.5 text-xs text-rose-600 dark:text-rose-300">{{ $certificate->documentSigner?->document?->title ?? '-' }}</p>
                            <p class="mt-0.5 text-xs text-rose-500 dark:text-rose-400">{{ $certificate->revocation_reason ?? __('No reason recorded') }}</p>
                        </div>
                    @empty
                        <p class="py-3 text-center text-xs text-zinc-400 dark:text-zinc-500">{{ __('No revoked certificates yet.') }}</p>
                    @endforelse
                </div>
            </div>

        </div>
    </div>

    {{-- ── 6. Recent Documents Table (Lunoz clean table style) ── --}}
    <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
        <div class="mb-5 flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-zinc-900 dark:text-white">{{ __('Recent documents') }}</h3>
                <p class="mt-0.5 text-sm text-zinc-400 dark:text-zinc-500">{{ __('Your latest document activity') }}</p>
            </div>
            <a href="{{ route('documents.index') }}" wire:navigate
               class="rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-600 transition-colors hover:bg-indigo-100 dark:bg-indigo-950/30 dark:text-indigo-400 dark:hover:bg-indigo-950/50">
                {{ __('View all') }}
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-zinc-100 dark:border-zinc-800">
                        <th class="pb-3 text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Title') }}</th>
                        <th class="pb-3 text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Status') }}</th>
                        <th class="pb-3 text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Signers') }}</th>
                        <th class="pb-3 text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Created') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-50 dark:divide-zinc-800/50">
                    @forelse ($this->recentDocuments as $document)
                        <tr class="group transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                            <td class="py-3.5 pr-4">
                                <a href="{{ route('documents.show', $document) }}" wire:navigate class="text-sm font-medium text-zinc-800 transition-colors group-hover:text-indigo-600 dark:text-zinc-200 dark:group-hover:text-indigo-400">
                                    {{ $document->title }}
                                </a>
                            </td>
                            <td class="py-3.5 pr-4">
                                @php
                                    $statusColors = [
                                        'completed' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400',
                                        'pending' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400',
                                        'draft' => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400',
                                        'declined' => 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400',
                                        'cancelled' => 'bg-orange-50 text-orange-700 dark:bg-orange-950/40 dark:text-orange-400',
                                        'archived' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
                                    ];
                                    $colorClass = $statusColors[$document->status->value] ?? 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400';
                                @endphp
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $colorClass }}">
                                    {{ ucfirst($document->status->value) }}
                                </span>
                            </td>
                            <td class="py-3.5 pr-4">
                                <span class="text-sm tabular-nums text-zinc-500 dark:text-zinc-400">{{ $document->document_signers_count }}</span>
                            </td>
                            <td class="py-3.5">
                                <span class="text-sm text-zinc-400 dark:text-zinc-500">{{ $document->created_at?->diffForHumans() }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-8 text-center text-sm text-zinc-400 dark:text-zinc-500">
                                {{ __('No documents yet. Upload your first document to get started.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── 7. Most Active Signers ── --}}
    <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
        <div class="mb-5">
            <h3 class="text-base font-bold text-zinc-900 dark:text-white">{{ __('Most active signers') }}</h3>
            <p class="mt-0.5 text-sm text-zinc-400 dark:text-zinc-500">{{ __('Top signers by completed requests') }}</p>
        </div>

        <div class="space-y-3">
            @forelse ($this->mostActiveSigners as $index => $signer)
                <div class="flex items-center gap-4 rounded-lg bg-zinc-50 px-4 py-3 transition-colors hover:bg-zinc-100/80 dark:bg-zinc-800/50 dark:hover:bg-zinc-800">
                    {{-- Rank --}}
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-xs font-bold text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400">
                        {{ $index + 1 }}
                    </div>

                    {{-- Info --}}
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $signer->name ?: $signer->email }}</p>
                        @if ($signer->name)
                            <p class="truncate text-xs text-zinc-400 dark:text-zinc-500">{{ $signer->email }}</p>
                        @endif
                    </div>

                    {{-- Stats --}}
                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400">
                            {{ $signer->signed_requests }} {{ __('signed') }}
                        </span>
                        <span class="text-xs tabular-nums text-zinc-400 dark:text-zinc-500">
                            / {{ $signer->total_requests }} {{ __('total') }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="py-8 text-center text-sm text-zinc-400 dark:text-zinc-500">
                    {{ __('No signer activity yet.') }}
                </div>
            @endforelse
        </div>
    </div>

</div>
