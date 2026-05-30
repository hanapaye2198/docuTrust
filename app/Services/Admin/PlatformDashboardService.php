<?php

namespace App\Services\Admin;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Enums\NotaryCredentialStatus;
use App\Enums\NotaryRequestStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\NotaryCredential;
use App\Models\NotaryRequest;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\SignerCertificate;
use App\Models\User;
use App\Services\Compliance\SignatureComplianceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PlatformDashboardService
{
    private const CACHE_MINUTES = 3;

    private const STUCK_REQUEST_DAYS = 3;

    private const STALE_DOCUMENT_DAYS = 7;

    private const TRIAL_WARNING_DAYS = 14;

    private const ACTION_QUEUE_LIMIT = 10;

    public function __construct(
        private readonly SignatureComplianceService $complianceService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return Cache::remember('platform-dashboard:payload', now()->addMinutes(self::CACHE_MINUTES), function (): array {
            return [
                'kpis' => $this->kpis(),
                'action_queue' => $this->actionQueue(),
                'top_organizations' => $this->topOrganizations(),
                'trials_ending_soon' => $this->trialsEndingSoon(),
                'compliance' => $this->complianceSummary(),
                'signing' => $this->signingSummary(),
                'recent_activity' => $this->recentActivity(),
                'awaiting_finalization' => $this->awaitingFinalizationPreview(),
            ];
        });
    }

    /**
     * @return array<string, int|array<string, int>>
     */
    private function kpis(): array
    {
        $usersByRole = User::query()
            ->selectRaw('role, COUNT(*) as aggregate')
            ->groupBy('role')
            ->pluck('aggregate', 'role');

        $requestCounts = NotaryRequest::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $documentCounts = Document::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $inProgressStatuses = [
            NotaryRequestStatus::Submitted,
            NotaryRequestStatus::IdentityReviewRequired,
            NotaryRequestStatus::IdentityVerified,
            NotaryRequestStatus::LocationReviewRequired,
            NotaryRequestStatus::LocationVerified,
            NotaryRequestStatus::SessionScheduled,
            NotaryRequestStatus::SessionInProgress,
            NotaryRequestStatus::SessionCompleted,
            NotaryRequestStatus::AttorneySigning,
            NotaryRequestStatus::AttorneyApproved,
        ];

        $totalDocuments = (int) $documentCounts->sum();
        $completedDocuments = (int) ($documentCounts[DocumentStatus::Completed->value] ?? 0);

        return [
            'organizations_total' => Organization::query()->count(),
            'organizations_trial_ending' => Organization::query()
                ->where('subscription_status', 'trial')
                ->whereBetween('trial_ends_at', [now(), now()->addDays(self::TRIAL_WARNING_DAYS)])
                ->count(),
            'users_total' => (int) $usersByRole->sum(),
            'users_by_role' => [
                UserRole::Client->value => (int) ($usersByRole[UserRole::Client->value] ?? 0),
                UserRole::Notary->value => (int) ($usersByRole[UserRole::Notary->value] ?? 0),
                UserRole::NotaryAdmin->value => (int) ($usersByRole[UserRole::NotaryAdmin->value] ?? 0),
                UserRole::SuperAdmin->value => (int) ($usersByRole[UserRole::SuperAdmin->value] ?? 0),
            ],
            'pending_attorney_applications' => NotaryCredential::query()
                ->where('status', NotaryCredentialStatus::Pending->value)
                ->count(),
            'notary_requests_total' => (int) $requestCounts->sum(),
            'notary_requests_awaiting_finalization' => (int) ($requestCounts[NotaryRequestStatus::Digitalized->value] ?? 0),
            'notary_requests_in_progress' => collect($inProgressStatuses)
                ->sum(fn (NotaryRequestStatus $status): int => (int) ($requestCounts[$status->value] ?? 0)),
            'notary_requests_notarized' => (int) ($requestCounts[NotaryRequestStatus::Notarized->value] ?? 0),
            'documents_total' => $totalDocuments,
            'documents_pending' => (int) ($documentCounts[DocumentStatus::Pending->value] ?? 0),
            'documents_completed' => $completedDocuments,
            'documents_completion_rate' => $totalDocuments > 0
                ? (int) round(($completedDocuments / $totalDocuments) * 100)
                : 0,
            'active_certificates' => SignerCertificate::query()
                ->where('status', 'active')
                ->whereNull('revoked_at')
                ->count(),
            'revoked_certificates' => SignerCertificate::query()
                ->where(function ($query): void {
                    $query->where('status', 'revoked')->orWhereNotNull('revoked_at');
                })
                ->count(),
            'failed_payments_recent' => Payment::query()
                ->where('status', PaymentStatus::Failed->value)
                ->where('created_at', '>=', now()->subDays(30))
                ->count(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function actionQueue(): array
    {
        $items = collect();

        NotaryCredential::query()
            ->with('user')
            ->where('status', NotaryCredentialStatus::Pending->value)
            ->latest('submitted_at')
            ->limit(5)
            ->get()
            ->each(function (NotaryCredential $credential) use ($items): void {
                $items->push([
                    'priority' => 1,
                    'type' => 'attorney_application',
                    'title' => __('Attorney application pending'),
                    'description' => $credential->user?->name ?? $credential->commission_number,
                    'url' => route('admin.attorney-applications.show', $credential),
                    'occurred_at' => $credential->submitted_at?->toIso8601String(),
                ]);
            });

        NotaryRequest::query()
            ->where('status', NotaryRequestStatus::Digitalized)
            ->latest('approved_at')
            ->limit(5)
            ->get()
            ->each(function (NotaryRequest $request) use ($items): void {
                $items->push([
                    'priority' => 1,
                    'type' => 'notary_finalization',
                    'title' => __('Awaiting finalization'),
                    'description' => $request->title,
                    'url' => route('notary-requests.show', $request),
                    'occurred_at' => $request->approved_at?->toIso8601String(),
                ]);
            });

        $stuckThreshold = now()->subDays(self::STUCK_REQUEST_DAYS);

        NotaryRequest::query()
            ->whereIn('status', [
                NotaryRequestStatus::IdentityReviewRequired,
                NotaryRequestStatus::LocationReviewRequired,
                NotaryRequestStatus::SessionInProgress,
            ])
            ->where('updated_at', '<=', $stuckThreshold)
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->each(function (NotaryRequest $request) use ($items): void {
                $items->push([
                    'priority' => 2,
                    'type' => 'stuck_notary_request',
                    'title' => __('Notarization needs attention'),
                    'description' => $request->title.' · '.ucfirst(str_replace('_', ' ', $request->status->value)),
                    'url' => route('notary-requests.show', $request),
                    'occurred_at' => $request->updated_at?->toIso8601String(),
                ]);
            });

        $staleDocumentThreshold = now()->subDays(self::STALE_DOCUMENT_DAYS);

        Document::query()
            ->where('status', DocumentStatus::Pending)
            ->where('updated_at', '<=', $staleDocumentThreshold)
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->each(function (Document $document) use ($items): void {
                $items->push([
                    'priority' => 2,
                    'type' => 'stale_document',
                    'title' => __('Signing stalled'),
                    'description' => $document->title,
                    'url' => route('documents.show', $document),
                    'occurred_at' => $document->updated_at?->toIso8601String(),
                ]);
            });

        $report = $this->complianceService->assess();

        foreach ($report['categories'] ?? [] as $category) {
            $status = $category['status'] ?? 'MISSING';

            if (! in_array($status, ['MISSING', 'PARTIAL'], true)) {
                continue;
            }

            $items->push([
                'priority' => 3,
                'type' => 'compliance',
                'title' => __('Compliance: :category', ['category' => $category['title'] ?? __('Category')]),
                'description' => $status,
                'url' => route('admin.compliance.dashboard'),
                'occurred_at' => $report['assessed_at'] ?? now()->toIso8601String(),
            ]);
        }

        return $items
            ->sortBy([
                ['priority', 'asc'],
                fn (array $item): string => (string) ($item['occurred_at'] ?? ''),
            ])
            ->take(self::ACTION_QUEUE_LIMIT)
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function topOrganizations(): array
    {
        return Organization::query()
            ->withCount(['users', 'notaryRequests'])
            ->orderByDesc('notary_requests_count')
            ->orderBy('name')
            ->limit(5)
            ->get()
            ->map(fn (Organization $organization): array => [
                'id' => $organization->id,
                'name' => $organization->name,
                'plan' => $organization->plan,
                'subscription_status' => $organization->subscription_status,
                'users_count' => $organization->users_count,
                'notary_requests_count' => $organization->notary_requests_count,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function trialsEndingSoon(): array
    {
        return Organization::query()
            ->where('subscription_status', 'trial')
            ->whereBetween('trial_ends_at', [now(), now()->addDays(self::TRIAL_WARNING_DAYS)])
            ->orderBy('trial_ends_at')
            ->limit(5)
            ->get()
            ->map(fn (Organization $organization): array => [
                'id' => $organization->id,
                'name' => $organization->name,
                'trial_ends_at' => $organization->trial_ends_at?->toIso8601String(),
                'days_remaining' => $organization->trial_ends_at !== null
                    ? (int) now()->diffInDays($organization->trial_ends_at, false)
                    : null,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function complianceSummary(): array
    {
        return Cache::remember('platform-dashboard:compliance', now()->addMinutes(10), function (): array {
            $report = $this->complianceService->assess();

            $attentionCategories = collect($report['categories'] ?? [])
                ->filter(fn (array $category): bool => in_array($category['status'] ?? '', ['MISSING', 'PARTIAL'], true))
                ->take(5)
                ->map(fn (array $category): array => [
                    'title' => $category['title'] ?? '',
                    'status' => $category['status'] ?? 'MISSING',
                    'score_percentage' => $category['score_percentage'] ?? 0,
                ])
                ->values()
                ->all();

            return [
                'overall_score' => $report['overall_score'] ?? 0,
                'trust_level_label' => $report['trust_level']['label'] ?? '',
                'phase' => $report['phase'] ?? '',
                'assessed_at' => $report['assessed_at'] ?? '',
                'attention_count' => count($attentionCategories),
                'attention_categories' => $attentionCategories,
            ];
        });
    }

    /**
     * @return array<string, int>
     */
    private function signingSummary(): array
    {
        $signerCounts = DocumentSigner::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $pendingSigners = (int) ($signerCounts[DocumentSignerStatus::Pending->value] ?? 0);
        $signedSigners = (int) ($signerCounts[DocumentSignerStatus::Signed->value] ?? 0);
        $totalSigners = $pendingSigners + $signedSigners;

        return [
            'total_signers' => $totalSigners,
            'signed_signers' => $signedSigners,
            'pending_signers' => $pendingSigners,
            'signer_completion_rate' => $totalSigners > 0
                ? (int) round(($signedSigners / $totalSigners) * 100)
                : 0,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentActivity(): array
    {
        $documents = Document::query()
            ->with('organization:id,name')
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn (Document $document): array => [
                'kind' => 'document',
                'title' => $document->title,
                'subtitle' => $document->organization?->name,
                'status' => $document->status->value,
                'url' => route('documents.show', $document),
                'occurred_at' => $document->created_at,
            ]);

        $requests = NotaryRequest::query()
            ->with('organization:id,name')
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn (NotaryRequest $request): array => [
                'kind' => 'notary_request',
                'title' => $request->title,
                'subtitle' => $request->organization?->name,
                'status' => $request->status->value,
                'url' => route('notary-requests.show', $request),
                'occurred_at' => $request->created_at,
            ]);

        return $documents
            ->concat($requests)
            ->sortByDesc('occurred_at')
            ->take(8)
            ->values()
            ->map(fn (array $item): array => [
                ...$item,
                'occurred_at' => $item['occurred_at']?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return Collection<int, NotaryRequest>
     */
    private function awaitingFinalizationPreview(): Collection
    {
        return NotaryRequest::query()
            ->where('status', NotaryRequestStatus::Digitalized)
            ->with(['requester', 'notary', 'organization'])
            ->latest('approved_at')
            ->limit(5)
            ->get();
    }
}
