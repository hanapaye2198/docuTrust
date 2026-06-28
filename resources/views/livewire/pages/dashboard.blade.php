<?php

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Enums\SigningMethod;
use App\Enums\TemplateRoleType;
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
     *   signer_statuses: array<string, int>,
     *   signer_roles: array<string, int>,
     *   signing_methods: array<string, int>,
     *   total_signers: int,
     *   signed_signers: int,
     *   approved_signers: int,
     *   pending_signers: int,
     *   notified_signers: int,
     *   expiring_links: int,
     *   expired_links: int
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

            $roleCounts = DocumentSigner::query()
                ->when(! $global, fn ($query) => $query->whereHas(
                    'document',
                    fn ($documentQuery) => $documentQuery->where('organization_id', $organizationId)
                ))
                ->selectRaw('role_type, COUNT(*) as aggregate')
                ->groupBy('role_type')
                ->pluck('aggregate', 'role_type');

            $methodCounts = DocumentSigner::query()
                ->when(! $global, fn ($query) => $query->whereHas(
                    'document',
                    fn ($documentQuery) => $documentQuery->where('organization_id', $organizationId)
                ))
                ->selectRaw('signing_method, COUNT(*) as aggregate')
                ->groupBy('signing_method')
                ->pluck('aggregate', 'signing_method');

            $expiringLinks = DocumentSigner::query()
                ->when(! $global, fn ($query) => $query->whereHas(
                    'document',
                    fn ($documentQuery) => $documentQuery->where('organization_id', $organizationId)
                ))
                ->whereIn('status', [DocumentSignerStatus::Pending, DocumentSignerStatus::Notified])
                ->whereBetween('expires_at', [now(), now()->addDays(2)])
                ->count();

            $expiredLinks = DocumentSigner::query()
                ->when(! $global, fn ($query) => $query->whereHas(
                    'document',
                    fn ($documentQuery) => $documentQuery->where('organization_id', $organizationId)
                ))
                ->whereIn('status', [DocumentSignerStatus::Pending, DocumentSignerStatus::Notified])
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', now())
                ->count();

            $totalDocuments = (int) $statusCounts->sum();
            $pendingSigners = (int) ($signerCounts[DocumentSignerStatus::Pending->value] ?? 0);
            $notifiedSigners = (int) ($signerCounts[DocumentSignerStatus::Notified->value] ?? 0);
            $signedSigners = (int) ($signerCounts[DocumentSignerStatus::Signed->value] ?? 0);
            $approvedSigners = (int) ($signerCounts[DocumentSignerStatus::Approved->value] ?? 0);

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
                'signer_statuses' => [
                    DocumentSignerStatus::Pending->value => $pendingSigners,
                    DocumentSignerStatus::Notified->value => $notifiedSigners,
                    DocumentSignerStatus::Signed->value => $signedSigners,
                    DocumentSignerStatus::Approved->value => $approvedSigners,
                ],
                'signer_roles' => [
                    TemplateRoleType::Signer->value => (int) ($roleCounts[TemplateRoleType::Signer->value] ?? 0),
                    TemplateRoleType::Approver->value => (int) ($roleCounts[TemplateRoleType::Approver->value] ?? 0),
                    TemplateRoleType::Recipient->value => (int) ($roleCounts[TemplateRoleType::Recipient->value] ?? 0),
                ],
                'signing_methods' => [
                    SigningMethod::EmailLink->value => (int) ($methodCounts[SigningMethod::EmailLink->value] ?? 0),
                    SigningMethod::AccountVerified->value => (int) ($methodCounts[SigningMethod::AccountVerified->value] ?? 0),
                    SigningMethod::PkiCertificate->value => (int) ($methodCounts[SigningMethod::PkiCertificate->value] ?? 0),
                ],
                'total_signers' => (int) $signerCounts->sum(),
                'signed_signers' => $signedSigners,
                'approved_signers' => $approvedSigners,
                'pending_signers' => $pendingSigners,
                'notified_signers' => $notifiedSigners,
                'expiring_links' => $expiringLinks,
                'expired_links' => $expiredLinks,
            ];
        });
    }

    #[Computed]
    public function headerGreeting(): string
    {
        $hour = now('Asia/Manila')->hour;

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
        $hour = now('Asia/Manila')->hour;

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
    public function notifiedSigners(): int
    {
        return $this->cachedDashboardStats()['notified_signers'];
    }

    #[Computed]
    public function approvedSigners(): int
    {
        return $this->cachedDashboardStats()['approved_signers'];
    }

    #[Computed]
    public function actionableSigners(): int
    {
        return $this->pendingSigners + $this->notifiedSigners;
    }

    #[Computed]
    public function expiringLinks(): int
    {
        return $this->cachedDashboardStats()['expiring_links'];
    }

    #[Computed]
    public function expiredLinks(): int
    {
        return $this->cachedDashboardStats()['expired_links'];
    }

    #[Computed]
    public function pendingApprovers(): int
    {
        $roles = $this->cachedDashboardStats()['signer_roles'];

        if (($roles[TemplateRoleType::Approver->value] ?? 0) === 0) {
            return 0;
        }

        return DocumentSigner::query()
            ->where('role_type', TemplateRoleType::Approver)
            ->whereIn('status', [DocumentSignerStatus::Pending, DocumentSignerStatus::Notified])
            ->whereHas('document', fn ($query) => $this->scopeDocumentRelationQuery($query))
            ->count();
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

    /**
     * @return array{labels: list<string>, values: list<int>, colors: list<string>}
     */
    #[Computed]
    public function signingMethodChartPayload(): array
    {
        $methods = $this->cachedDashboardStats()['signing_methods'];

        return [
            'labels' => [
                __('Email link'),
                __('Account verified'),
                __('PKI certificate'),
            ],
            'values' => [
                $methods[SigningMethod::EmailLink->value] ?? 0,
                $methods[SigningMethod::AccountVerified->value] ?? 0,
                $methods[SigningMethod::PkiCertificate->value] ?? 0,
            ],
            'colors' => ['#6366f1', '#14b8a6', '#f59e0b'],
        ];
    }

    /**
     * @return array{labels: list<string>, completedValues: list<int>, pendingValues: list<int>}
     */
    #[Computed]
    public function signerTrendChartPayload(): array
    {
        $now = now();
        $labels = [];
        $completedValues = [];
        $pendingValues = [];

        for ($i = 5; $i >= 0; $i--) {
            $week = $now->copy()->startOfWeek()->subWeeks($i);
            $labels[] = $week->format('M j');

            $completedValues[] = DocumentSigner::query()
                ->whereIn('status', [DocumentSignerStatus::Signed, DocumentSignerStatus::Approved])
                ->whereBetween('signed_at', [$week->copy()->startOfWeek(), $week->copy()->endOfWeek()])
                ->whereHas('document', fn ($query) => $this->scopeDocumentRelationQuery($query))
                ->count();

            $pendingValues[] = DocumentSigner::query()
                ->whereIn('status', [DocumentSignerStatus::Pending, DocumentSignerStatus::Notified])
                ->whereBetween('created_at', [$week->copy()->startOfWeek(), $week->copy()->endOfWeek()])
                ->whereHas('document', fn ($query) => $this->scopeDocumentRelationQuery($query))
                ->count();
        }

        return [
            'labels' => $labels,
            'completedValues' => $completedValues,
            'pendingValues' => $pendingValues,
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
    public function expiringSignerRequests()
    {
        return DocumentSigner::query()
            ->with('document')
            ->whereIn('status', [DocumentSignerStatus::Pending, DocumentSignerStatus::Notified])
            ->whereBetween('expires_at', [now(), now()->addDays(2)])
            ->whereHas('document', fn ($query) => $this->scopeDocumentRelationQuery($query))
            ->orderBy('expires_at')
            ->limit(4)
            ->get();
    }

    #[Computed]
    public function pendingApprovalRequests()
    {
        return DocumentSigner::query()
            ->with('document')
            ->where('role_type', TemplateRoleType::Approver)
            ->whereIn('status', [DocumentSignerStatus::Pending, DocumentSignerStatus::Notified])
            ->whereHas('document', fn ($query) => $this->scopeDocumentRelationQuery($query)->where('status', DocumentStatus::Pending))
            ->oldest('created_at')
            ->limit(4)
            ->get();
    }

    #[Computed]
    public function draftDocumentsMissingFields()
    {
        return $this->scopeDocumentsQuery(Document::query())
            ->where('status', DocumentStatus::Draft)
            ->whereHas('documentSigners', fn ($query) => $query->where('role_type', TemplateRoleType::Signer))
            ->whereDoesntHave('signatureFields')
            ->latest('updated_at')
            ->limit(4)
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

<x-admin.page gap="gap-5" class="relative isolate min-h-full !py-3 font-sans sm:!py-4">
    <div class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-80 bg-[radial-gradient(circle_at_top_left,rgba(20,184,166,0.16),transparent_34%),radial-gradient(circle_at_top_right,rgba(99,102,241,0.14),transparent_30%)] dark:bg-[radial-gradient(circle_at_top_left,rgba(20,184,166,0.22),transparent_34%),radial-gradient(circle_at_top_right,rgba(99,102,241,0.20),transparent_30%)]"></div>

    {{-- ── 1. Page Header ── --}}
    <section
        x-data="{
            greeting: @js($this->headerGreeting),
            dateLabel: @js(now()->format('l, F j, Y')),
            timeLabel: @js(now()->format('g:i A')),
            init() {
                const updateGreeting = () => {
                    const now = new Date();
                    const hour = now.getHours();
                    this.greeting = hour >= 5 && hour < 12
                        ? @js(__('Good morning'))
                        : (hour >= 12 && hour < 18 ? @js(__('Good afternoon')) : @js(__('Good evening')));
                    this.dateLabel = new Intl.DateTimeFormat(undefined, {
                        weekday: 'long',
                        month: 'long',
                        day: 'numeric',
                        year: 'numeric',
                    }).format(now);
                    this.timeLabel = new Intl.DateTimeFormat(undefined, {
                        hour: 'numeric',
                        minute: '2-digit',
                    }).format(now);
                };

                updateGreeting();
                window.setInterval(updateGreeting, 60000);
            },
        }"
        class="rounded-2xl border border-zinc-200/80 bg-white px-4 py-3 shadow-sm dark:border-white/10 dark:bg-zinc-950 sm:px-5"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h1 class="truncate text-xl font-bold tracking-tight text-zinc-950 dark:text-white">
                    <span x-text="greeting">{{ $this->headerGreeting }}</span>, {{ auth()->user()?->name ?? __('Welcome') }}
                </h1>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Your signing command center is ready.') }}
                </p>
            </div>

            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-2 rounded-full bg-teal-50 px-3 py-1.5 text-xs font-semibold text-teal-700 dark:bg-teal-500/10 dark:text-teal-300">
                    <span x-text="dateLabel">{{ now()->format('l, F j, Y') }}</span>
                </span>
                <span class="inline-flex items-center rounded-full bg-zinc-100 px-3 py-1.5 text-xs font-semibold text-zinc-600 dark:bg-white/10 dark:text-zinc-300" x-text="timeLabel">
                    {{ now()->format('g:i A') }}
                </span>
            </div>
        </div>
    </section>

    {{-- ── 2. Stat Cards Row (Lunoz style: icon right, big number, small label, badge) ── --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            [
                'label' => __('Total Documents'),
                'value' => $this->totalDocuments,
                'meta' => $this->completionRate.'% '.__('completed'),
                'accent' => 'from-indigo-500 to-blue-500',
                'tone' => 'text-indigo-600 bg-indigo-50 dark:text-indigo-300 dark:bg-indigo-500/10',
            ],
            [
                'label' => __('Completed'),
                'value' => $this->completedDocuments,
                'meta' => $this->completionRate.'%',
                'accent' => 'from-emerald-500 to-teal-500',
                'tone' => 'text-emerald-600 bg-emerald-50 dark:text-emerald-300 dark:bg-emerald-500/10',
            ],
            [
                'label' => __('Pending'),
                'value' => $this->pendingDocuments,
                'meta' => $this->pendingRate.'% '.__('of total'),
                'accent' => 'from-amber-500 to-orange-500',
                'tone' => 'text-amber-600 bg-amber-50 dark:text-amber-300 dark:bg-amber-500/10',
            ],
            [
                'label' => __('Rejected'),
                'value' => $this->rejectedDocuments,
                'meta' => __('declined'),
                'accent' => 'from-rose-500 to-pink-500',
                'tone' => 'text-rose-600 bg-rose-50 dark:text-rose-300 dark:bg-rose-500/10',
            ],
        ] as $stat)
            <div class="group relative overflow-hidden rounded-3xl border border-white/70 bg-white/85 p-5 shadow-sm shadow-zinc-200/70 backdrop-blur transition duration-200 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-zinc-200/80 dark:border-white/10 dark:bg-zinc-950/70 dark:shadow-black/20">
                <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r {{ $stat['accent'] }}"></div>
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-zinc-400 dark:text-zinc-500">{{ $stat['label'] }}</p>
                        <p class="mt-3 text-3xl font-bold tabular-nums tracking-tight text-zinc-950 dark:text-white">{{ $stat['value'] }}</p>
                    </div>
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br {{ $stat['accent'] }} text-sm font-black text-white shadow-lg shadow-zinc-900/10">
                        {{ mb_substr($stat['label'], 0, 1) }}
                    </div>
                </div>
                <span class="mt-4 inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $stat['tone'] }}">
                    {{ $stat['meta'] }}
                </span>
            </div>
        @endforeach
    </div>

    {{-- ── 3. Quick Action Cards ── --}}
    <div class="grid gap-4 lg:grid-cols-2">

        {{-- Upload & send --}}
        <a href="{{ route('documents.create') }}"
           wire:navigate
           class="group relative overflow-hidden rounded-3xl border border-blue-400/20 bg-gradient-to-br from-blue-600 via-indigo-600 to-indigo-800 p-5 shadow-xl shadow-blue-950/10 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-blue-500/20 dark:border-blue-300/10 dark:from-blue-600 dark:via-indigo-700 dark:to-zinc-950 sm:p-6">
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
           class="group relative overflow-hidden rounded-3xl border border-teal-300/25 bg-gradient-to-br from-teal-500 via-emerald-500 to-emerald-700 p-5 shadow-xl shadow-teal-950/10 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-teal-500/20 dark:border-teal-300/10 dark:from-teal-500 dark:via-emerald-700 dark:to-zinc-950 sm:p-6">
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

    @php
        $signerTrendPayload = $this->signerTrendChartPayload;
        $signerTrendMax = max([
            1,
            ...$signerTrendPayload['completedValues'],
            ...$signerTrendPayload['pendingValues'],
        ]);
        $signingMethodPayload = $this->signingMethodChartPayload;
        $signingMethodTotal = array_sum($signingMethodPayload['values']);
        $signingMethodStops = [];
        $signingMethodCursor = 0;

        foreach ($signingMethodPayload['values'] as $methodIndex => $methodValue) {
            if ($signingMethodTotal <= 0 || $methodValue <= 0) {
                continue;
            }

            $start = $signingMethodCursor;
            $signingMethodCursor += ($methodValue / $signingMethodTotal) * 100;
            $signingMethodStops[] = "{$signingMethodPayload['colors'][$methodIndex]} {$start}% {$signingMethodCursor}%";
        }

        $signingMethodGradient = $signingMethodStops === []
            ? '#e4e4e7'
            : 'conic-gradient('.implode(', ', $signingMethodStops).')';
        $activityPayload = $this->activityChartPayload;
        $activityMax = max([
            1,
            ...$activityPayload['weeklyValues'],
            ...$activityPayload['monthlyValues'],
        ]);
    @endphp

    {{-- ── 4. Signer Operations ── --}}
    <div class="space-y-5">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-3xl border border-white/70 bg-white/85 p-5 shadow-sm shadow-zinc-200/60 backdrop-blur dark:border-white/10 dark:bg-zinc-950/70 dark:shadow-black/20">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Needs signature') }}</p>
                <p class="mt-2 text-3xl font-bold tabular-nums text-amber-600 dark:text-amber-400">{{ $this->actionableSigners }}</p>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Pending or notified signer actions') }}</p>
            </div>
            <div class="rounded-3xl border border-white/70 bg-white/85 p-5 shadow-sm shadow-zinc-200/60 backdrop-blur dark:border-white/10 dark:bg-zinc-950/70 dark:shadow-black/20">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Pending approvers') }}</p>
                <p class="mt-2 text-3xl font-bold tabular-nums text-indigo-600 dark:text-indigo-400">{{ $this->pendingApprovers }}</p>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Approvals blocking signer flow') }}</p>
            </div>
            <div class="rounded-3xl border border-white/70 bg-white/85 p-5 shadow-sm shadow-zinc-200/60 backdrop-blur dark:border-white/10 dark:bg-zinc-950/70 dark:shadow-black/20">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Links expiring') }}</p>
                <p class="mt-2 text-3xl font-bold tabular-nums text-orange-600 dark:text-orange-400">{{ $this->expiringLinks }}</p>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Signer links expiring within 48 hours') }}</p>
            </div>
            <div class="rounded-3xl border border-white/70 bg-white/85 p-5 shadow-sm shadow-zinc-200/60 backdrop-blur dark:border-white/10 dark:bg-zinc-950/70 dark:shadow-black/20">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Expired links') }}</p>
                <p class="mt-2 text-3xl font-bold tabular-nums text-rose-600 dark:text-rose-400">{{ $this->expiredLinks }}</p>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Pending signers with stale access') }}</p>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-5">
            <div class="rounded-3xl border border-white/70 bg-white/85 p-5 shadow-sm shadow-zinc-200/60 backdrop-blur dark:border-white/10 dark:bg-zinc-950/70 dark:shadow-black/20 sm:p-6 lg:col-span-3">
                <div class="mb-5">
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white">{{ __('Signer completion trend') }}</h3>
                    <p class="mt-0.5 text-sm text-zinc-400 dark:text-zinc-500">{{ __('Completed versus newly pending signer actions over the last 6 weeks') }}</p>
                </div>
                <div class="relative h-64" wire:ignore>
                    <canvas id="docutrust-signer-trend-chart" class="absolute inset-0 z-10 h-full w-full" data-chart="@json($signerTrendPayload)"></canvas>
                    <div data-chart-fallback="docutrust-signer-trend-chart" class="absolute inset-0 z-0 flex items-end gap-2 overflow-hidden rounded-2xl border-b border-zinc-200 bg-gradient-to-b from-zinc-50/70 to-white px-2 pb-8 dark:border-zinc-800 dark:from-white/[0.03] dark:to-transparent sm:gap-3">
                        @foreach ($signerTrendPayload['labels'] as $trendIndex => $trendLabel)
                            @php
                                $completedHeight = max(6, (int) round((($signerTrendPayload['completedValues'][$trendIndex] ?? 0) / $signerTrendMax) * 160));
                                $pendingHeight = max(6, (int) round((($signerTrendPayload['pendingValues'][$trendIndex] ?? 0) / $signerTrendMax) * 160));
                            @endphp
                            <div class="flex min-w-0 flex-1 flex-col items-center gap-2">
                                <div class="flex h-44 items-end gap-1.5">
                                    <div class="w-3 rounded-t bg-emerald-400/80 dark:bg-emerald-500" style="height: {{ $completedHeight }}px"></div>
                                    <div class="w-3 rounded-t bg-amber-400/80 dark:bg-amber-500" style="height: {{ $pendingHeight }}px"></div>
                                </div>
                                <span class="truncate text-[10px] font-medium text-zinc-400 dark:text-zinc-500">{{ $trendLabel }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div data-chart-fallback="docutrust-signer-trend-chart" class="absolute right-2 top-1 z-0 flex gap-3 text-[11px] text-zinc-500 dark:text-zinc-400">
                        <span class="inline-flex items-center gap-1"><span class="size-2 rounded-full bg-emerald-400"></span>{{ __('Completed') }}</span>
                        <span class="inline-flex items-center gap-1"><span class="size-2 rounded-full bg-amber-400"></span>{{ __('New pending') }}</span>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-white/70 bg-white/85 p-5 shadow-sm shadow-zinc-200/60 backdrop-blur dark:border-white/10 dark:bg-zinc-950/70 dark:shadow-black/20 sm:p-6 lg:col-span-2">
                <div class="mb-5">
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white">{{ __('Signing methods') }}</h3>
                    <p class="mt-0.5 text-sm text-zinc-400 dark:text-zinc-500">{{ __('How participants are asked to sign') }}</p>
                </div>
                <div class="space-y-4" wire:ignore>
                    <div class="relative flex h-44 items-center justify-center">
                        <canvas id="docutrust-signing-method-chart" class="absolute inset-0 z-10 mx-auto h-full w-full max-w-[220px]" data-chart="@json($signingMethodPayload)"></canvas>
                    <div data-chart-fallback="docutrust-signing-method-chart" class="absolute inset-0 z-0 flex items-center justify-center">
                        <div class="relative flex size-40 items-center justify-center rounded-full" style="background: {{ $signingMethodGradient }};">
                            <div class="flex size-24 flex-col items-center justify-center rounded-full bg-white text-center shadow-sm dark:bg-zinc-900">
                                <span class="text-2xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ $signingMethodTotal }}</span>
                                <span class="text-[10px] font-semibold uppercase tracking-wider text-zinc-400">{{ __('Signers') }}</span>
                            </div>
                        </div>
                    </div>
                    </div>
                    <div data-chart-fallback="docutrust-signing-method-chart" class="grid gap-1.5 text-xs">
                        @foreach ($signingMethodPayload['labels'] as $methodIndex => $methodLabel)
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-zinc-200/70 bg-zinc-50/80 px-3 py-1.5 dark:border-white/10 dark:bg-white/5">
                                <span class="flex min-w-0 items-center gap-2 text-zinc-600 dark:text-zinc-300">
                                    <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $signingMethodPayload['colors'][$methodIndex] }}"></span>
                                    <span class="truncate">{{ $methodLabel }}</span>
                                </span>
                                <span class="font-bold tabular-nums text-zinc-900 dark:text-white">{{ $signingMethodPayload['values'][$methodIndex] ?? 0 }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-3xl border border-white/70 bg-white/85 p-5 shadow-sm shadow-zinc-200/60 backdrop-blur dark:border-white/10 dark:bg-zinc-950/70 dark:shadow-black/20">
                <h3 class="text-sm font-bold text-zinc-900 dark:text-white">{{ __('Expiring signer links') }}</h3>
                <p class="mt-0.5 text-xs text-zinc-400 dark:text-zinc-500">{{ __('Follow up before access expires') }}</p>
                <div class="mt-4 space-y-2">
                    @forelse ($this->expiringSignerRequests as $signer)
                        <a href="{{ route('documents.show', $signer->document) }}" wire:navigate class="block rounded-2xl border border-orange-100 bg-orange-50/70 px-3 py-2.5 transition hover:-translate-y-0.5 hover:bg-orange-50 dark:border-orange-900/30 dark:bg-orange-950/20">
                            <p class="truncate text-sm font-semibold text-orange-800 dark:text-orange-200">{{ $signer->name }}</p>
                            <p class="truncate text-xs text-orange-700 dark:text-orange-300">{{ $signer->document?->title }}</p>
                            <p class="mt-1 text-xs text-orange-600 dark:text-orange-400">{{ __('Expires') }} {{ $signer->expires_at?->diffForHumans() }}</p>
                        </a>
                    @empty
                        <p class="py-4 text-center text-xs text-zinc-400 dark:text-zinc-500">{{ __('No signer links expiring soon.') }}</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-3xl border border-white/70 bg-white/85 p-5 shadow-sm shadow-zinc-200/60 backdrop-blur dark:border-white/10 dark:bg-zinc-950/70 dark:shadow-black/20">
                <h3 class="text-sm font-bold text-zinc-900 dark:text-white">{{ __('Waiting on approvers') }}</h3>
                <p class="mt-0.5 text-xs text-zinc-400 dark:text-zinc-500">{{ __('Approvals that may block signing') }}</p>
                <div class="mt-4 space-y-2">
                    @forelse ($this->pendingApprovalRequests as $approver)
                        <a href="{{ route('documents.show', $approver->document) }}" wire:navigate class="block rounded-2xl border border-indigo-100 bg-indigo-50/70 px-3 py-2.5 transition hover:-translate-y-0.5 hover:bg-indigo-50 dark:border-indigo-900/30 dark:bg-indigo-950/20">
                            <p class="truncate text-sm font-semibold text-indigo-800 dark:text-indigo-200">{{ $approver->name }}</p>
                            <p class="truncate text-xs text-indigo-700 dark:text-indigo-300">{{ $approver->document?->title }}</p>
                            <p class="mt-1 text-xs text-indigo-600 dark:text-indigo-400">{{ $approver->created_at?->diffForHumans() }}</p>
                        </a>
                    @empty
                        <p class="py-4 text-center text-xs text-zinc-400 dark:text-zinc-500">{{ __('No pending approvals.') }}</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-3xl border border-white/70 bg-white/85 p-5 shadow-sm shadow-zinc-200/60 backdrop-blur dark:border-white/10 dark:bg-zinc-950/70 dark:shadow-black/20">
                <h3 class="text-sm font-bold text-zinc-900 dark:text-white">{{ __('Drafts missing fields') }}</h3>
                <p class="mt-0.5 text-xs text-zinc-400 dark:text-zinc-500">{{ __('Prepared signers still need document fields') }}</p>
                <div class="mt-4 space-y-2">
                    @forelse ($this->draftDocumentsMissingFields as $document)
                        <a href="{{ route('documents.prepare', $document) }}" wire:navigate class="block rounded-2xl border border-teal-100 bg-teal-50/70 px-3 py-2.5 transition hover:-translate-y-0.5 hover:bg-teal-50 dark:border-teal-900/30 dark:bg-teal-950/20">
                            <p class="truncate text-sm font-semibold text-teal-800 dark:text-teal-200">{{ $document->title }}</p>
                            <p class="mt-1 text-xs text-teal-600 dark:text-teal-400">{{ __('Updated') }} {{ $document->updated_at?->diffForHumans() }}</p>
                        </a>
                    @empty
                        <p class="py-4 text-center text-xs text-zinc-400 dark:text-zinc-500">{{ __('No drafts are missing fields.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- ── 5. Activity Chart ── --}}
    <div class="rounded-3xl border border-white/70 bg-white/85 p-5 shadow-sm shadow-zinc-200/60 backdrop-blur dark:border-white/10 dark:bg-zinc-950/70 dark:shadow-black/20 sm:p-6">
        <div class="mb-5 flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-zinc-900 dark:text-white">{{ __('Activity trend') }}</h3>
                <p class="mt-0.5 text-sm text-zinc-400 dark:text-zinc-500">{{ __('Average created per week and total created per month (last 6 months)') }}</p>
            </div>
        </div>
        <div class="relative h-72" wire:ignore>
            <canvas id="docutrust-activity-chart" class="absolute inset-0 z-10 h-full w-full" data-chart="@json($activityPayload)"></canvas>
            <div data-chart-fallback="docutrust-activity-chart" class="absolute inset-0 z-0 flex items-end gap-2 overflow-hidden rounded-2xl border-b border-zinc-200 bg-gradient-to-b from-zinc-50/70 to-white px-2 pb-8 dark:border-zinc-800 dark:from-white/[0.03] dark:to-transparent sm:gap-3">
                @foreach ($activityPayload['labels'] as $activityIndex => $activityLabel)
                    @php
                        $monthlyHeight = max(8, (int) round((($activityPayload['monthlyValues'][$activityIndex] ?? 0) / $activityMax) * 190));
                        $weeklyHeight = max(8, (int) round((($activityPayload['weeklyValues'][$activityIndex] ?? 0) / $activityMax) * 190));
                    @endphp
                    <div class="flex min-w-0 flex-1 flex-col items-center gap-2">
                        <div class="flex h-52 items-end gap-1.5">
                            <div class="w-5 rounded-t bg-teal-400/70 dark:bg-teal-500" style="height: {{ $monthlyHeight }}px"></div>
                            <div class="w-3 rounded-t bg-indigo-400/80 dark:bg-indigo-500" style="height: {{ $weeklyHeight }}px"></div>
                        </div>
                        <span class="truncate text-[10px] font-medium text-zinc-400 dark:text-zinc-500">{{ $activityLabel }}</span>
                    </div>
                @endforeach
            </div>
            <div data-chart-fallback="docutrust-activity-chart" class="absolute right-2 top-1 z-0 flex gap-3 text-[11px] text-zinc-500 dark:text-zinc-400">
                <span class="inline-flex items-center gap-1"><span class="size-2 rounded-full bg-teal-400"></span>{{ __('Created / month') }}</span>
                <span class="inline-flex items-center gap-1"><span class="size-2 rounded-full bg-indigo-400"></span>{{ __('Avg / week') }}</span>
            </div>
        </div>
    </div>

    {{-- ── 6. Two-Column: Status Overview + Signer Completion & Certificates ── --}}
    <div class="grid gap-4 lg:grid-cols-5">

        {{-- Left: Status overview with progress bars (Lunoz style) --}}
        <div class="rounded-3xl border border-white/70 bg-white/85 p-5 shadow-sm shadow-zinc-200/60 backdrop-blur dark:border-white/10 dark:bg-zinc-950/70 dark:shadow-black/20 sm:p-6 lg:col-span-3">
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
                        <div class="h-2.5 w-full overflow-hidden rounded-full bg-zinc-100 ring-1 ring-zinc-200/70 dark:bg-zinc-800 dark:ring-white/10">
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
        <div class="flex flex-col gap-4 lg:col-span-2">

            {{-- Signer completion ring --}}
            <div class="rounded-3xl border border-white/70 bg-white/85 p-5 shadow-sm shadow-zinc-200/60 backdrop-blur dark:border-white/10 dark:bg-zinc-950/70 dark:shadow-black/20 sm:p-6">
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
                        <div class="flex items-center justify-between rounded-xl border border-zinc-200/70 bg-zinc-50/80 px-3 py-2 dark:border-white/10 dark:bg-white/5">
                            <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Total') }}</span>
                            <span class="text-sm font-bold text-zinc-900 dark:text-white">{{ $this->totalSigners }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-xl border border-emerald-100 bg-emerald-50/80 px-3 py-2 dark:border-emerald-500/20 dark:bg-emerald-500/10">
                            <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">{{ __('Signed') }}</span>
                            <span class="text-sm font-bold text-emerald-700 dark:text-emerald-300">{{ $this->signedSigners }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-xl border border-amber-100 bg-amber-50/80 px-3 py-2 dark:border-amber-500/20 dark:bg-amber-500/10">
                            <span class="text-xs font-medium text-amber-600 dark:text-amber-400">{{ __('Pending') }}</span>
                            <span class="text-sm font-bold text-amber-700 dark:text-amber-300">{{ $this->pendingSigners }}</span>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-zinc-100 ring-1 ring-zinc-200/70 dark:bg-zinc-800 dark:ring-white/10">
                        <div class="h-full rounded-full bg-indigo-500 transition-all duration-500" style="width: {{ $this->signerCompletionRate }}%"></div>
                    </div>
                    <p class="mt-1.5 text-xs text-zinc-400 dark:text-zinc-500">
                        {{ $this->signedSigners }} {{ __('of') }} {{ $this->totalSigners }} {{ __('signers completed') }}
                    </p>
                </div>
            </div>

            {{-- Certificate stats --}}
            <div class="grid grid-cols-2 gap-4">
                <div class="rounded-3xl border border-white/70 bg-white/85 p-5 shadow-sm shadow-zinc-200/60 backdrop-blur dark:border-white/10 dark:bg-zinc-950/70 dark:shadow-black/20">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-950/30">
                        <svg class="h-4.5 w-4.5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/></svg>
                    </div>
                    <p class="mt-3 text-2xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ $this->totalActiveCertificates }}</p>
                    <p class="mt-0.5 text-xs font-medium text-zinc-400 dark:text-zinc-500">{{ __('Active certs') }}</p>
                </div>
                <div class="rounded-3xl border border-white/70 bg-white/85 p-5 shadow-sm shadow-zinc-200/60 backdrop-blur dark:border-white/10 dark:bg-zinc-950/70 dark:shadow-black/20">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-rose-50 dark:bg-rose-950/30">
                        <svg class="h-4.5 w-4.5 text-rose-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0-10.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.75h-.152c-3.196 0-6.1-1.249-8.25-3.286Zm0 13.036h.008v.008H12v-.008Z"/></svg>
                    </div>
                    <p class="mt-3 text-2xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ $this->totalRevokedCertificates }}</p>
                    <p class="mt-0.5 text-xs font-medium text-zinc-400 dark:text-zinc-500">{{ __('Revoked') }}</p>
                </div>
            </div>

            {{-- Recent revocations --}}
            <div class="rounded-3xl border border-white/70 bg-white/85 p-5 shadow-sm shadow-zinc-200/60 backdrop-blur dark:border-white/10 dark:bg-zinc-950/70 dark:shadow-black/20">
                <h4 class="text-sm font-bold text-zinc-900 dark:text-white">{{ __('Recent revocations') }}</h4>
                <p class="mt-0.5 text-xs text-zinc-400 dark:text-zinc-500">{{ __('Latest certificate trust changes') }}</p>
                <div class="mt-3 space-y-2">
                    @forelse ($this->recentRevokedCertificates as $certificate)
                        <div class="rounded-2xl border border-rose-100 bg-rose-50/70 px-3 py-2.5 dark:border-rose-900/30 dark:bg-rose-950/20">
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
    <div class="rounded-3xl border border-white/70 bg-white/85 p-5 shadow-sm shadow-zinc-200/60 backdrop-blur dark:border-white/10 dark:bg-zinc-950/70 dark:shadow-black/20 sm:p-6">
        <div class="mb-5 flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-zinc-900 dark:text-white">{{ __('Recent documents') }}</h3>
                <p class="mt-0.5 text-sm text-zinc-400 dark:text-zinc-500">{{ __('Your latest document activity') }}</p>
            </div>
            <a href="{{ route('documents.index') }}" wire:navigate
               class="rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-600 transition-colors hover:bg-indigo-100 dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/15">
                {{ __('View all') }}
            </a>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-zinc-100 bg-white/60 dark:border-white/10 dark:bg-white/[0.03]">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-zinc-100 bg-zinc-50/80 dark:border-white/10 dark:bg-white/[0.03]">
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Title') }}</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Signers') }}</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Created') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-50 dark:divide-zinc-800/50">
                    @forelse ($this->recentDocuments as $document)
                        <tr class="group transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                            <td class="px-4 py-3.5">
                                <a href="{{ route('documents.show', $document) }}" wire:navigate class="text-sm font-medium text-zinc-800 transition-colors group-hover:text-indigo-600 dark:text-zinc-200 dark:group-hover:text-indigo-400">
                                    {{ $document->title }}
                                </a>
                            </td>
                            <td class="px-4 py-3.5">
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
                            <td class="px-4 py-3.5">
                                <span class="text-sm tabular-nums text-zinc-500 dark:text-zinc-400">{{ $document->document_signers_count }}</span>
                            </td>
                            <td class="px-4 py-3.5">
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
    <div class="rounded-3xl border border-white/70 bg-white/85 p-5 shadow-sm shadow-zinc-200/60 backdrop-blur dark:border-white/10 dark:bg-zinc-950/70 dark:shadow-black/20 sm:p-6">
        <div class="mb-5">
            <h3 class="text-base font-bold text-zinc-900 dark:text-white">{{ __('Most active signers') }}</h3>
            <p class="mt-0.5 text-sm text-zinc-400 dark:text-zinc-500">{{ __('Top signers by completed requests') }}</p>
        </div>

        <div class="space-y-3">
            @forelse ($this->mostActiveSigners as $index => $signer)
                <div class="flex flex-col gap-3 rounded-2xl border border-zinc-100 bg-zinc-50/80 px-4 py-3 transition hover:-translate-y-0.5 hover:bg-white hover:shadow-sm dark:border-white/10 dark:bg-white/[0.04] dark:hover:bg-white/[0.07] sm:flex-row sm:items-center sm:gap-4">
                    {{-- Rank --}}
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-xs font-bold text-indigo-600 ring-1 ring-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-500/20">
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
                    <div class="flex flex-wrap items-center gap-3">
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

</x-admin.page>
