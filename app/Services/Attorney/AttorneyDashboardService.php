<?php

namespace App\Services\Attorney;

use App\Enums\DocumentStatus;
use App\Enums\NotaryRequestStatus;
use App\Enums\TemplateRoleType;
use App\Models\NotaryRequest;
use App\Models\SignerCertificate;
use App\Models\User;
use App\Services\AttorneyApplicationService;
use App\Services\TrustProfile\TrustProfileService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class AttorneyDashboardService
{
    private const SEQUENTIAL_SIGNING_WORKFLOW = 'sequential';

    /**
     * @var list<string>
     */
    private const CLOSED_STATUSES = [
        NotaryRequestStatus::Rejected->value,
        NotaryRequestStatus::Failed->value,
        NotaryRequestStatus::Notarized->value,
        NotaryRequestStatus::Cancelled->value,
    ];

    /**
     * @var list<string>
     */
    private const SESSION_STATUSES = [
        NotaryRequestStatus::SessionScheduled->value,
        NotaryRequestStatus::SessionInProgress->value,
    ];

    /**
     * @var list<string>
     */
    private const SIGNING_STATUSES = [
        NotaryRequestStatus::AttorneySigning->value,
        NotaryRequestStatus::AttorneyApproved->value,
    ];

    public function __construct(
        private readonly AttorneyApplicationService $attorneyApplications,
        private readonly TrustProfileService $trustProfile,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboardData(User $user): array
    {
        $baseQuery = $this->assignedRequestsQuery($user);

        $metrics = [
            'total' => (clone $baseQuery)->count(),
            'open' => (clone $baseQuery)->whereNotIn('status', self::CLOSED_STATUSES)->count(),
            'blocked' => $this->countForQueue($user, 'blocked'),
            'ready_to_send' => $this->countForQueue($user, 'ready_to_send'),
            'awaiting_signatures' => $this->countForQueue($user, 'awaiting_signatures'),
            'sessions' => (clone $baseQuery)->whereIn('status', self::SESSION_STATUSES)->count(),
            'attorney_signing' => (clone $baseQuery)->whereIn('status', self::SIGNING_STATUSES)->count(),
            'notarized' => (clone $baseQuery)->where('status', NotaryRequestStatus::Notarized->value)->count(),
        ];

        return [
            'eligibility' => $this->attorneyApplications->practiceEligibility($user),
            'requiresRenewal' => $this->attorneyApplications->requiresRenewal($user),
            'credential' => $this->credentialSummary($user),
            'enotaryReadiness' => $this->enotaryReadinessSummary($user),
            'metrics' => $metrics,
            'continueWork' => $this->continueWorkRequests($user),
            'upcomingSessions' => $this->upcomingSessionRequests($user),
            'certificates' => $this->certificateSummary($user),
        ];
    }

    /**
     * @return array{allowed: bool, message: string|null}
     */
    public function practiceEligibility(User $user): array
    {
        return $this->attorneyApplications->practiceEligibility($user);
    }

    /**
     * @return Builder<NotaryRequest>
     */
    public function assignedRequestsQuery(User $user): Builder
    {
        return NotaryRequest::query()
            ->where('notary_user_id', $user->id);
    }

    public function countForQueue(User $user, string $queue): int
    {
        $query = $this->assignedRequestsQuery($user);
        $this->applyQueueFilter($query, $queue);

        return $query->count();
    }

    /**
     * @return Collection<int, NotaryRequest>
     */
    public function continueWorkRequests(User $user, int $limit = 8): Collection
    {
        $picked = collect();
        $excludeIds = [];

        foreach (['blocked', 'ready_to_send'] as $queue) {
            $remaining = $limit - $picked->count();
            if ($remaining <= 0) {
                break;
            }

            $query = $this->assignedRequestsQuery($user)
                ->with(['requester', 'organization'])
                ->whereNotIn('status', self::CLOSED_STATUSES);

            if ($excludeIds !== []) {
                $query->whereNotIn('id', $excludeIds);
            }

            $this->applyQueueFilter($query, $queue);

            $items = $query->latest('updated_at')->limit($remaining)->get();
            $picked = $picked->merge($items);
            $excludeIds = $picked->pluck('id')->all();
        }

        if ($picked->count() < $limit) {
            $remaining = $limit - $picked->count();
            $query = $this->assignedRequestsQuery($user)
                ->with(['requester', 'organization'])
                ->whereIn('status', array_merge(self::SESSION_STATUSES, self::SIGNING_STATUSES));

            if ($excludeIds !== []) {
                $query->whereNotIn('id', $excludeIds);
            }

            $items = $query->latest('updated_at')->limit($remaining)->get();
            $picked = $picked->merge($items);
            $excludeIds = $picked->pluck('id')->all();
        }

        if ($picked->count() < $limit) {
            $remaining = $limit - $picked->count();
            $query = $this->assignedRequestsQuery($user)
                ->with(['requester', 'organization'])
                ->whereNotIn('status', self::CLOSED_STATUSES);

            if ($excludeIds !== []) {
                $query->whereNotIn('id', $excludeIds);
            }

            $picked = $picked->merge(
                $query->latest('updated_at')->limit($remaining)->get()
            );
        }

        return new Collection($picked->unique('id')->values()->all());
    }

    /**
     * @return Collection<int, NotaryRequest>
     */
    public function upcomingSessionRequests(User $user, int $limit = 5): Collection
    {
        return $this->assignedRequestsQuery($user)
            ->with(['requester', 'sessions'])
            ->where('status', NotaryRequestStatus::SessionScheduled->value)
            ->whereHas('sessions', fn (Builder $sessions) => $sessions->where('scheduled_for', '>=', now()->startOfDay()))
            ->latest('updated_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{
     *   active: Collection<int, SignerCertificate>,
     *   revoked: Collection<int, SignerCertificate>,
     *   total_active: int,
     *   total_revoked: int
     * }
     */
    public function certificateSummary(User $user): array
    {
        $organizationId = $user->organization_id;

        $query = SignerCertificate::query()
            ->with(['documentSigner.document'])
            ->whereHas('documentSigner.document', fn (Builder $builder) => $builder->where('organization_id', $organizationId))
            ->latest();

        return [
            'active' => (clone $query)
                ->where('status', 'active')
                ->whereNull('revoked_at')
                ->limit(5)
                ->get(),
            'revoked' => (clone $query)
                ->where(function (Builder $builder): void {
                    $builder->where('status', 'revoked')->orWhereNotNull('revoked_at');
                })
                ->limit(5)
                ->get(),
            'total_active' => (clone $query)
                ->where('status', 'active')
                ->whereNull('revoked_at')
                ->count(),
            'total_revoked' => (clone $query)
                ->where(function (Builder $builder): void {
                    $builder->where('status', 'revoked')->orWhereNotNull('revoked_at');
                })
                ->count(),
        ];
    }

    /**
     * @return array{
     *   met: int,
     *   total: int,
     *   checks: list<array{key: string, label: string, met: bool, description: string}>,
     *   ready: bool
     * }
     */
    public function enotaryReadinessSummary(User $user): array
    {
        $checks = $this->trustProfile->enotaryReadinessChecks($user);
        $met = collect($checks)->filter(fn (array $check): bool => $check['met'])->count();

        return [
            'met' => $met,
            'total' => count($checks),
            'checks' => $checks,
            'ready' => $this->trustProfile->isEnotaryReady($user),
        ];
    }

    /**
     * @return array{
     *   has_credential: bool,
     *   commission_expires_at: ?Carbon,
     *   days_until_expiry: ?int,
     *   has_seal: bool,
     *   has_signature: bool,
     *   status: ?string
     * }
     */
    public function credentialSummary(User $user): array
    {
        $credential = $this->attorneyApplications->latestCredential($user);

        if ($credential === null) {
            return [
                'has_credential' => false,
                'commission_expires_at' => null,
                'days_until_expiry' => null,
                'has_seal' => false,
                'has_signature' => false,
                'status' => null,
            ];
        }

        $expiresAt = $credential->commission_expires_at;

        return [
            'has_credential' => true,
            'commission_expires_at' => $expiresAt,
            'days_until_expiry' => $expiresAt !== null ? (int) now()->startOfDay()->diffInDays($expiresAt->copy()->startOfDay(), false) : null,
            'has_seal' => filled($credential->seal_image_path),
            'has_signature' => filled($credential->signature_image_path),
            'status' => $credential->status,
        ];
    }

    public function requestsIndexUrl(string $queue = 'all', string $status = 'all'): string
    {
        $params = array_filter([
            'queue' => $queue !== 'all' ? $queue : null,
            'status' => $status !== 'all' ? $status : null,
        ]);

        return route('notary.requests.index', $params);
    }

    public function workActionLabel(NotaryRequest $request): string
    {
        return match ($request->status) {
            NotaryRequestStatus::SessionInProgress => __('Join session'),
            NotaryRequestStatus::SessionScheduled => __('View session'),
            NotaryRequestStatus::AttorneySigning, NotaryRequestStatus::AttorneyApproved => __('Continue signing'),
            default => __('Open request'),
        };
    }

    public function statusLabel(NotaryRequest $request): string
    {
        return match ($request->status) {
            NotaryRequestStatus::SessionInProgress => __('Session in progress'),
            NotaryRequestStatus::SessionScheduled => __('Session scheduled'),
            NotaryRequestStatus::AttorneySigning => __('Attorney signing'),
            NotaryRequestStatus::AttorneyApproved => __('Attorney reviewed'),
            NotaryRequestStatus::Submitted => __('Submitted'),
            NotaryRequestStatus::IdentityReviewRequired => __('Identity review'),
            NotaryRequestStatus::IdentityVerified => __('Identity verified'),
            NotaryRequestStatus::LocationReviewRequired => __('Location review'),
            NotaryRequestStatus::LocationVerified => __('Location verified'),
            NotaryRequestStatus::SessionCompleted => __('Session completed'),
            NotaryRequestStatus::Digitalized => __('Digitalized'),
            NotaryRequestStatus::Notarized => __('Notarized'),
            default => str($request->status->value)->replace('_', ' ')->title()->toString(),
        };
    }

    /**
     * @return array{badge: string, dot: string}
     */
    public function statusPresentation(NotaryRequest $request): array
    {
        return match ($request->status) {
            NotaryRequestStatus::SessionInProgress,
            NotaryRequestStatus::SessionScheduled => [
                'badge' => 'bg-sky-100 text-sky-800 ring-sky-200/80 dark:bg-sky-500/15 dark:text-sky-300 dark:ring-sky-500/30',
                'dot' => 'bg-sky-500',
            ],
            NotaryRequestStatus::AttorneySigning,
            NotaryRequestStatus::AttorneyApproved => [
                'badge' => 'bg-violet-100 text-violet-800 ring-violet-200/80 dark:bg-violet-500/15 dark:text-violet-300 dark:ring-violet-500/30',
                'dot' => 'bg-violet-500',
            ],
            NotaryRequestStatus::Notarized => [
                'badge' => 'bg-emerald-100 text-emerald-800 ring-emerald-200/80 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30',
                'dot' => 'bg-emerald-500',
            ],
            NotaryRequestStatus::Rejected,
            NotaryRequestStatus::Failed,
            NotaryRequestStatus::Cancelled => [
                'badge' => 'bg-red-100 text-red-800 ring-red-200/80 dark:bg-red-500/15 dark:text-red-300 dark:ring-red-500/30',
                'dot' => 'bg-red-500',
            ],
            default => [
                'badge' => 'bg-zinc-100 text-zinc-700 ring-zinc-200/80 dark:bg-zinc-500/15 dark:text-zinc-300 dark:ring-zinc-500/30',
                'dot' => 'bg-zinc-400',
            ],
        };
    }

    protected function applyQueueFilter(Builder $builder, string $queueFilter): void
    {
        switch ($queueFilter) {
            case 'awaiting_signatures':
                $builder->whereHas('documents', fn (Builder $documents) => $documents->where('status', DocumentStatus::Pending->value));

                return;

            case 'ready_to_send':
                $builder
                    ->whereDoesntHave('documents', fn (Builder $documents) => $documents->where('status', DocumentStatus::Pending->value))
                    ->whereHas('documents', fn (Builder $documents) => $this->applyReadyDraftDocumentConstraint($documents));

                return;

            case 'blocked':
                $builder
                    ->whereDoesntHave('documents', fn (Builder $documents) => $documents->where('status', DocumentStatus::Pending->value))
                    ->whereDoesntHave('documents', fn (Builder $documents) => $this->applyReadyDraftDocumentConstraint($documents))
                    ->whereHas('documents', fn (Builder $documents) => $this->applyBlockedDraftDocumentConstraint($documents));

                return;
        }
    }

    protected function applyReadyDraftDocumentConstraint(Builder $builder): void
    {
        $builder
            ->where('status', DocumentStatus::Draft->value)
            ->whereHas('documentSigners', fn (Builder $signers) => $signers->where('role_type', TemplateRoleType::Signer->value))
            ->whereHas('signatureFields')
            ->whereDoesntHave('documentSigners', function (Builder $signers): void {
                $signers
                    ->where('role_type', TemplateRoleType::Signer->value)
                    ->whereDoesntHave('signatureFields');
            })
            ->where(function (Builder $workflow): void {
                $workflow
                    ->whereRaw('COALESCE(signing_workflow, ?) != ?', [self::SEQUENTIAL_SIGNING_WORKFLOW, self::SEQUENTIAL_SIGNING_WORKFLOW])
                    ->orWhereRaw(
                        '(SELECT MIN(ds.signing_order) FROM document_signers ds WHERE ds.document_id = documents.id) = 1
                        AND (SELECT MAX(ds.signing_order) FROM document_signers ds WHERE ds.document_id = documents.id) = (SELECT COUNT(*) FROM document_signers ds WHERE ds.document_id = documents.id)
                        AND (SELECT COUNT(DISTINCT ds.signing_order) FROM document_signers ds WHERE ds.document_id = documents.id) = (SELECT COUNT(*) FROM document_signers ds WHERE ds.document_id = documents.id)'
                    );
            });
    }

    protected function applyBlockedDraftDocumentConstraint(Builder $builder): void
    {
        $builder
            ->where('status', DocumentStatus::Draft->value)
            ->where(function (Builder $draft): void {
                $draft
                    ->whereDoesntHave('documentSigners', fn (Builder $signers) => $signers->where('role_type', TemplateRoleType::Signer->value))
                    ->orWhereDoesntHave('signatureFields')
                    ->orWhereHas('documentSigners', function (Builder $signers): void {
                        $signers
                            ->where('role_type', TemplateRoleType::Signer->value)
                            ->whereDoesntHave('signatureFields');
                    })
                    ->orWhere(function (Builder $workflow): void {
                        $workflow
                            ->whereRaw('COALESCE(signing_workflow, ?) = ?', [self::SEQUENTIAL_SIGNING_WORKFLOW, self::SEQUENTIAL_SIGNING_WORKFLOW])
                            ->whereRaw(
                                '(SELECT MIN(ds.signing_order) FROM document_signers ds WHERE ds.document_id = documents.id) != 1
                                OR (SELECT MAX(ds.signing_order) FROM document_signers ds WHERE ds.document_id = documents.id) != (SELECT COUNT(*) FROM document_signers ds WHERE ds.document_id = documents.id)
                                OR (SELECT COUNT(DISTINCT ds.signing_order) FROM document_signers ds WHERE ds.document_id = documents.id) != (SELECT COUNT(*) FROM document_signers ds WHERE ds.document_id = documents.id)'
                            );
                    });
            });
    }
}
