<?php

use App\Enums\DocumentStatus;
use App\Enums\TemplateRoleType;
use App\Models\NotaryRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    private const CLOSED_STATUSES = ['rejected', 'failed', 'notarized', 'cancelled'];

    private const PER_PAGE = 10;

    private const SEQUENTIAL_SIGNING_WORKFLOW = 'sequential';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = 'all';

    #[Url(as: 'queue')]
    public string $queueFilter = 'all';

    #[Url(as: 'trust')]
    public string $trustFilter = 'all';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedQueueFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTrustFilter(): void
    {
        $this->resetPage();
    }

    protected function baseRequestQuery(User $user): Builder
    {
        $isNotaryView = $user->role->value === 'notary';
        $isNotaryAdmin = $user->role->value === 'notary_admin';

        return NotaryRequest::query()
            ->with(['requester', 'notary', 'organization'])
            ->when($isNotaryView, function (Builder $builder) use ($user): void {
                $builder->where('notary_user_id', $user->id);
            })
            ->when(! $isNotaryView && ! $isNotaryAdmin, function (Builder $builder) use ($user): void {
                $builder->where('organization_id', $user->organization_id);
            })
            ->when($this->search !== '', function (Builder $builder): void {
                $builder->where(function (Builder $nested): void {
                    $nested
                        ->where('title', 'like', '%'.$this->search.'%')
                        ->orWhere('request_type', 'like', '%'.$this->search.'%')
                        ->orWhereHas('requester', fn (Builder $requester) => $requester->where('name', 'like', '%'.$this->search.'%'))
                        ->orWhereHas('notary', fn (Builder $notary) => $notary->where('name', 'like', '%'.$this->search.'%'));
                });
            })
            ->when($this->statusFilter !== 'all', fn (Builder $builder) => $builder->where('status', $this->statusFilter));
    }

    protected function filteredRequestQuery(User $user): Builder
    {
        $query = $this->baseRequestQuery($user);

        $this->applyQueueFilter($query, $this->queueFilter);
        $this->applyTrustFilter($query, $this->trustFilter);

        return $query;
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

            case 'completed_documents':
                $builder
                    ->whereDoesntHave('documents', fn (Builder $documents) => $documents->where('status', DocumentStatus::Pending->value))
                    ->whereDoesntHave('documents', fn (Builder $documents) => $this->applyReadyDraftDocumentConstraint($documents))
                    ->whereDoesntHave('documents', fn (Builder $documents) => $this->applyBlockedDraftDocumentConstraint($documents))
                    ->whereHas('documents', fn (Builder $documents) => $documents->where('status', DocumentStatus::Completed->value));

                return;

            case 'empty':
                $builder
                    ->whereDoesntHave('documents', fn (Builder $documents) => $documents->where('status', DocumentStatus::Pending->value))
                    ->whereDoesntHave('documents', fn (Builder $documents) => $this->applyReadyDraftDocumentConstraint($documents))
                    ->whereDoesntHave('documents', fn (Builder $documents) => $this->applyBlockedDraftDocumentConstraint($documents))
                    ->whereDoesntHave('documents', fn (Builder $documents) => $documents->where('status', DocumentStatus::Completed->value));

                return;
        }
    }

    protected function applyTrustFilter(Builder $builder, string $trustFilter): void
    {
        switch ($trustFilter) {
            case 'trust_ready':
                $builder
                    ->whereHas('documents', fn (Builder $documents) => $documents->where('status', DocumentStatus::Completed->value))
                    ->whereDoesntHave('documents', fn (Builder $documents) => $this->applyMissingCertificateDocumentConstraint($documents))
                    ->whereDoesntHave('documents', fn (Builder $documents) => $this->applyMissingHashDocumentConstraint($documents))
                    ->whereDoesntHave('documents', fn (Builder $documents) => $this->applyMissingBlockchainDocumentConstraint($documents));

                return;

            case 'missing_certificate':
                $builder
                    ->whereHas('documents', fn (Builder $documents) => $documents->where('status', DocumentStatus::Completed->value))
                    ->whereHas('documents', fn (Builder $documents) => $this->applyMissingCertificateDocumentConstraint($documents));

                return;

            case 'missing_hash':
                $builder
                    ->whereHas('documents', fn (Builder $documents) => $documents->where('status', DocumentStatus::Completed->value))
                    ->whereDoesntHave('documents', fn (Builder $documents) => $this->applyMissingCertificateDocumentConstraint($documents))
                    ->whereHas('documents', fn (Builder $documents) => $this->applyMissingHashDocumentConstraint($documents));

                return;

            case 'missing_blockchain':
                $builder
                    ->whereHas('documents', fn (Builder $documents) => $documents->where('status', DocumentStatus::Completed->value))
                    ->whereDoesntHave('documents', fn (Builder $documents) => $this->applyMissingCertificateDocumentConstraint($documents))
                    ->whereDoesntHave('documents', fn (Builder $documents) => $this->applyMissingHashDocumentConstraint($documents))
                    ->whereHas('documents', fn (Builder $documents) => $this->applyMissingBlockchainDocumentConstraint($documents));

                return;

            case 'not_applicable':
                $builder->whereDoesntHave('documents', fn (Builder $documents) => $documents->where('status', DocumentStatus::Completed->value));

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
                        "(SELECT MIN(ds.signing_order) FROM document_signers ds WHERE ds.document_id = documents.id) = 1
                        AND (SELECT MAX(ds.signing_order) FROM document_signers ds WHERE ds.document_id = documents.id) = (SELECT COUNT(*) FROM document_signers ds WHERE ds.document_id = documents.id)
                        AND (SELECT COUNT(DISTINCT ds.signing_order) FROM document_signers ds WHERE ds.document_id = documents.id) = (SELECT COUNT(*) FROM document_signers ds WHERE ds.document_id = documents.id)"
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
                                "(SELECT MIN(ds.signing_order) FROM document_signers ds WHERE ds.document_id = documents.id) != 1
                                OR (SELECT MAX(ds.signing_order) FROM document_signers ds WHERE ds.document_id = documents.id) != (SELECT COUNT(*) FROM document_signers ds WHERE ds.document_id = documents.id)
                                OR (SELECT COUNT(DISTINCT ds.signing_order) FROM document_signers ds WHERE ds.document_id = documents.id) != (SELECT COUNT(*) FROM document_signers ds WHERE ds.document_id = documents.id)"
                            );
                    });
            });
    }

    protected function applyMissingCertificateDocumentConstraint(Builder $builder): void
    {
        $builder
            ->where('status', DocumentStatus::Completed->value)
            ->where(function (Builder $documents): void {
                $documents->whereNull('certificate_path')->orWhere('certificate_path', '');
            });
    }

    protected function applyMissingHashDocumentConstraint(Builder $builder): void
    {
        $builder
            ->where('status', DocumentStatus::Completed->value)
            ->where(function (Builder $documents): void {
                $documents
                    ->whereDoesntHave('documentHash')
                    ->orWhereHas('documentHash', function (Builder $hashes): void {
                        $hashes->whereNull('hash')->orWhere('hash', '');
                    });
            });
    }

    protected function applyMissingBlockchainDocumentConstraint(Builder $builder): void
    {
        $builder
            ->where('status', DocumentStatus::Completed->value)
            ->whereHas('documentHash')
            ->where(function (Builder $documents): void {
                $documents->whereDoesntHave('documentHash', function (Builder $hashes): void {
                    $hashes->whereNotNull('hash')->where('hash', '!=', '');
                })->orWhereHas('documentHash', function (Builder $hashes): void {
                    $hashes->whereNull('transaction_id')->orWhere('transaction_id', '');
                });
            });
    }

    public function with(): array
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        $isNotaryView = $user->role->value === 'notary';
        $isNotaryAdmin = $user->role->value === 'notary_admin';

        $requests = $this->filteredRequestQuery($user)
            ->latest('created_at')
            ->latest('id')
            ->paginate(self::PER_PAGE);
        $requests->getCollection()->load(['documents.documentSigners', 'documents.signatureFields', 'documents.documentHash']);

        $filteredMetricsQuery = $this->filteredRequestQuery($user);

        $requestSummaries = $requests->getCollection()->mapWithKeys(function (NotaryRequest $request): array {
            $documents = $request->documents;
            $blockedCount = 0;
            $readyToSendCount = 0;
            $awaitingSignaturesCount = 0;
            $completedCount = 0;
            $missingCertificateCount = 0;
            $missingHashCount = 0;
            $missingBlockchainCount = 0;
            $trustReadyCount = 0;

            foreach ($documents as $document) {
                if ($document->status === DocumentStatus::Pending) {
                    $awaitingSignaturesCount++;
                    continue;
                }

                if ($document->status === DocumentStatus::Completed) {
                    $completedCount++;

                     $hasCertificate = is_string($document->certificate_path) && $document->certificate_path !== '';
                     $hasHash = $document->documentHash !== null && is_string($document->documentHash->hash) && $document->documentHash->hash !== '';
                     $hasBlockchain = $document->documentHash !== null
                        && is_string($document->documentHash->transaction_id)
                        && $document->documentHash->transaction_id !== '';

                    if (! $hasCertificate) {
                        $missingCertificateCount++;
                    }

                    if (! $hasHash) {
                        $missingHashCount++;
                    }

                    if (! $hasBlockchain) {
                        $missingBlockchainCount++;
                    }

                    if ($hasCertificate && $hasHash && $hasBlockchain) {
                        $trustReadyCount++;
                    }

                    continue;
                }

                if ($document->status === DocumentStatus::Draft) {
                    if ($document->canSendForSigning()) {
                        $readyToSendCount++;
                    } else {
                        $blockedCount++;
                    }
                }
            }

            $queueState = match (true) {
                $awaitingSignaturesCount > 0 => 'awaiting_signatures',
                $readyToSendCount > 0 => 'ready_to_send',
                $blockedCount > 0 => 'blocked',
                $completedCount > 0 => 'completed_documents',
                default => 'empty',
            };

            $trustState = match (true) {
                $completedCount === 0 => 'not_applicable',
                $missingCertificateCount > 0 => 'missing_certificate',
                $missingHashCount > 0 => 'missing_hash',
                $missingBlockchainCount > 0 => 'missing_blockchain',
                $trustReadyCount === $completedCount => 'trust_ready',
                default => 'partial',
            };

            $queueLabel = match (true) {
                $queueState === 'awaiting_signatures' => __('Waiting on signers'),
                $queueState === 'ready_to_send' => __('Pending: send document'),
                $queueState === 'blocked' => __('Needs setup'),
                $request->status->value === 'session_completed' => __('Ready for attorney signing'),
                $request->status->value === 'attorney_signing' => __('Attorney signing'),
                in_array($request->status->value, ['session_scheduled', 'session_in_progress'], true) => __('Waiting for video'),
                $queueState === 'completed_documents' => __('Waiting for video'),
                default => __('No document yet'),
            };

            $queueDescription = match (true) {
                $queueState === 'awaiting_signatures' => trans_choice(':count document is awaiting signatures|:count documents are awaiting signatures', $awaitingSignaturesCount, ['count' => $awaitingSignaturesCount]),
                $queueState === 'ready_to_send' => trans_choice(':count document is ready to send|:count documents are ready to send', $readyToSendCount, ['count' => $readyToSendCount]),
                $queueState === 'blocked' => trans_choice(':count document needs fields or signers|:count documents need fields or signers', $blockedCount, ['count' => $blockedCount]),
                $request->status->value === 'session_completed' => __('Video is complete. Attorney signing is next.'),
                $request->status->value === 'attorney_signing' => __('Attorney signature is in progress.'),
                in_array($request->status->value, ['session_scheduled', 'session_in_progress'], true) => __('Video verification is pending.'),
                $queueState === 'completed_documents' => __('Client signing is done. Video verification is next.'),
                default => __('Upload and prepare the case document.'),
            };

            $queueColor = match (true) {
                $queueState === 'awaiting_signatures' => 'bg-amber-100 text-amber-800 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-900/50',
                $queueState === 'ready_to_send' => 'bg-sky-100 text-sky-800 ring-sky-200 dark:bg-sky-950/40 dark:text-sky-300 dark:ring-sky-900/50',
                $queueState === 'blocked' => 'bg-rose-100 text-rose-800 ring-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:ring-rose-900/50',
                $request->status->value === 'session_completed' || $request->status->value === 'attorney_signing' => 'bg-indigo-100 text-indigo-800 ring-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-300 dark:ring-indigo-900/50',
                in_array($request->status->value, ['session_scheduled', 'session_in_progress'], true) || $queueState === 'completed_documents' => 'bg-violet-100 text-violet-800 ring-violet-200 dark:bg-violet-950/40 dark:text-violet-300 dark:ring-violet-900/50',
                default => 'bg-zinc-100 text-zinc-700 ring-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:ring-zinc-700',
            };

            return [
                $request->id => [
                    'document_count' => $documents->count(),
                    'blocked_count' => $blockedCount,
                    'ready_to_send_count' => $readyToSendCount,
                    'awaiting_signatures_count' => $awaitingSignaturesCount,
                    'completed_count' => $completedCount,
                    'queue_state' => $queueState,
                    'queue_label' => $queueLabel,
                    'queue_description' => $queueDescription,
                    'queue_color' => $queueColor,
                    'missing_certificate_count' => $missingCertificateCount,
                    'missing_hash_count' => $missingHashCount,
                    'missing_blockchain_count' => $missingBlockchainCount,
                    'trust_ready_count' => $trustReadyCount,
                    'trust_state' => $trustState,
                ],
            ];
        });

        return [
            'requests' => $requests,
            'requestSummaries' => $requestSummaries,
            'isNotaryView' => $isNotaryView,
            'isNotaryAdmin' => $isNotaryAdmin,
            'requestCount' => $requests->total(),
            'openCount' => (clone $filteredMetricsQuery)->whereNotIn('status', self::CLOSED_STATUSES)->count(),
            'closedCount' => (clone $filteredMetricsQuery)->whereIn('status', self::CLOSED_STATUSES)->count(),
            'blockedCount' => tap($query = $this->baseRequestQuery($user), fn (Builder $builder) => $this->applyQueueFilter($builder, 'blocked'))->count(),
            'readyToSendCount' => tap($query = $this->baseRequestQuery($user), fn (Builder $builder) => $this->applyQueueFilter($builder, 'ready_to_send'))->count(),
            'awaitingSignaturesCount' => tap($query = $this->baseRequestQuery($user), fn (Builder $builder) => $this->applyQueueFilter($builder, 'awaiting_signatures'))->count(),
            'trustReadyRequestCount' => tap($query = $this->baseRequestQuery($user), fn (Builder $builder) => $this->applyTrustFilter($builder, 'trust_ready'))->count(),
            'missingCertificateRequestCount' => tap($query = $this->baseRequestQuery($user), fn (Builder $builder) => $this->applyTrustFilter($builder, 'missing_certificate'))->count(),
            'missingBlockchainRequestCount' => tap($query = $this->baseRequestQuery($user), fn (Builder $builder) => $this->applyTrustFilter($builder, 'missing_blockchain'))->count(),
        ];
    }
}; ?>

<div class="flex min-h-full w-full flex-1 flex-col gap-6 p-1">
    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50 sm:text-3xl">{{ __('Notarizations') }}</h1>
            <p class="mt-1 max-w-2xl text-sm text-zinc-500 dark:text-zinc-400">
                {{ $isNotaryView
                    ? __('Review notarizations assigned to you, schedule sessions, and record attorney decisions.')
                    : __('Track remote notarizations through identity verification, signing, and final completion.') }}
            </p>
        </div>
        <flux:button
            variant="primary"
            :href="$isNotaryView ? route('notary.requests.create') : route('notary-requests.create')"
            wire:navigate
            icon="plus"
        >
            {{ __('New notarization') }}
        </flux:button>
    </div>

    {{-- Primary metrics --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <div class="text-xs font-semibold uppercase tracking-widest text-zinc-400 dark:text-zinc-500">{{ __('Total') }}</div>
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                    <svg class="h-4 w-4 text-zinc-500 dark:text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                </div>
            </div>
            <div class="mt-3 text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">{{ $requestCount }}</div>
            <div class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">{{ __(':open open · :closed closed', ['open' => $openCount, 'closed' => $closedCount]) }}</div>
        </div>
        <div class="rounded-2xl border border-amber-200/60 bg-amber-50/50 p-5 shadow-sm dark:border-amber-900/30 dark:bg-amber-950/20">
            <div class="flex items-center justify-between">
                <div class="text-xs font-semibold uppercase tracking-widest text-amber-600 dark:text-amber-400">{{ __('Needs attention') }}</div>
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/40">
                    <svg class="h-4 w-4 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                </div>
            </div>
            <div class="mt-3 text-3xl font-bold tracking-tight text-amber-700 dark:text-amber-300">{{ $blockedCount }}</div>
            <div class="mt-1 text-xs text-amber-600/80 dark:text-amber-400/70">{{ __(':ready ready to send', ['ready' => $readyToSendCount]) }}</div>
        </div>
        <div class="rounded-2xl border border-violet-200/60 bg-violet-50/50 p-5 shadow-sm dark:border-violet-900/30 dark:bg-violet-950/20">
            <div class="flex items-center justify-between">
                <div class="text-xs font-semibold uppercase tracking-widest text-violet-600 dark:text-violet-400">{{ __('In progress') }}</div>
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-100 dark:bg-violet-900/40">
                    <svg class="h-4 w-4 text-violet-600 dark:text-violet-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                </div>
            </div>
            <div class="mt-3 text-3xl font-bold tracking-tight text-violet-700 dark:text-violet-300">{{ $awaitingSignaturesCount }}</div>
            <div class="mt-1 text-xs text-violet-600/80 dark:text-violet-400/70">{{ __('awaiting signatures') }}</div>
        </div>
        <div class="rounded-2xl border border-emerald-200/60 bg-emerald-50/50 p-5 shadow-sm dark:border-emerald-900/30 dark:bg-emerald-950/20">
            <div class="flex items-center justify-between">
                <div class="text-xs font-semibold uppercase tracking-widest text-emerald-600 dark:text-emerald-400">{{ __('Trust ready') }}</div>
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/40">
                    <svg class="h-4 w-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                </div>
            </div>
            <div class="mt-3 text-3xl font-bold tracking-tight text-emerald-700 dark:text-emerald-300">{{ $trustReadyRequestCount }}</div>
            <div class="mt-1 text-xs text-emerald-600/80 dark:text-emerald-400/70">
                @if ($missingCertificateRequestCount > 0 || $missingBlockchainRequestCount > 0)
                    {{ __(':certs missing cert · :chain missing chain', ['certs' => $missingCertificateRequestCount, 'chain' => $missingBlockchainRequestCount]) }}
                @else
                    {{ __('all artifacts complete') }}
                @endif
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400 dark:text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Search by title, type, owner, or notary...') }}"
                    class="w-full rounded-xl border border-zinc-200 bg-zinc-50 py-2.5 pl-10 pr-4 text-sm text-zinc-900 transition placeholder:text-zinc-400 focus:border-teal-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-teal-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 dark:placeholder:text-zinc-500 dark:focus:bg-zinc-900"
                />
            </div>
            <div class="inline-flex items-center rounded-full bg-zinc-100 px-3 py-1.5 text-xs font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                {{ __('Latest first') }}
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <select
                    wire:model.live="statusFilter"
                    class="rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2.5 text-sm text-zinc-700 transition focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300"
                >
                    <option value="all">{{ __('All statuses') }}</option>
                    <option value="draft">{{ __('Draft') }}</option>
                    <option value="submitted">{{ __('Submitted') }}</option>
                    <option value="identity_review_required">{{ __('Identity review required') }}</option>
                    <option value="identity_verified">{{ __('Identity verified') }}</option>
                    <option value="location_review_required">{{ __('Location review required') }}</option>
                    <option value="location_verified">{{ __('Location verified') }}</option>
                    <option value="session_scheduled">{{ __('Session scheduled') }}</option>
                    <option value="session_in_progress">{{ __('Session in progress') }}</option>
                    <option value="session_completed">{{ __('Session completed') }}</option>
                    <option value="attorney_signing">{{ __('Attorney signing') }}</option>
                    <option value="attorney_approved">{{ __('Attorney reviewed') }}</option>
                    <option value="digitalized">{{ __('Digitalized') }}</option>
                    <option value="notarized">{{ __('Notarized') }}</option>
                    <option value="rejected">{{ __('Rejected') }}</option>
                    <option value="failed">{{ __('Failed') }}</option>
                    <option value="cancelled">{{ __('Cancelled') }}</option>
                </select>
                <select
                    wire:model.live="queueFilter"
                    class="rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2.5 text-sm text-zinc-700 transition focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300"
                >
                    <option value="all">{{ __('All queues') }}</option>
                    <option value="blocked">{{ __('Blocked') }}</option>
                    <option value="ready_to_send">{{ __('Ready to send') }}</option>
                    <option value="awaiting_signatures">{{ __('Awaiting signatures') }}</option>
                    <option value="completed_documents">{{ __('Completed') }}</option>
                    <option value="empty">{{ __('No documents') }}</option>
                </select>
                <select
                    wire:model.live="trustFilter"
                    class="rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2.5 text-sm text-zinc-700 transition focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300"
                >
                    <option value="all">{{ __('All trust') }}</option>
                    <option value="trust_ready">{{ __('Trust ready') }}</option>
                    <option value="missing_certificate">{{ __('Missing certificate') }}</option>
                    <option value="missing_hash">{{ __('Missing hash') }}</option>
                    <option value="missing_blockchain">{{ __('Missing blockchain') }}</option>
                    <option value="not_applicable">{{ __('N/A') }}</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Request list --}}
    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200/80 dark:divide-zinc-800">
                <thead class="bg-zinc-50/80 dark:bg-zinc-800/50">
                    <tr>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Notarization') }}</th>
                        <th class="hidden px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 md:table-cell">{{ __('Status') }}</th>
                        <th class="hidden px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 lg:table-cell">{{ __('Participants') }}</th>
                        <th class="hidden px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 xl:table-cell">{{ __('Documents') }}</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800/80">
                    @forelse ($requests as $request)
                        @php
                            $showRoute = $isNotaryView ? route('notary.requests.show', $request) : route('notary-requests.show', $request);
                            $summary = $requestSummaries[$request->id] ?? null;

                            $statusColor = match ($request->status->value) {
                                'draft' => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
                                'submitted' => 'bg-sky-100 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300',
                                'identity_review_required', 'location_review_required' => 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
                                'identity_verified', 'location_verified' => 'bg-teal-100 text-teal-700 dark:bg-teal-950/40 dark:text-teal-300',
                                'session_scheduled', 'session_in_progress', 'session_completed' => 'bg-violet-100 text-violet-700 dark:bg-violet-950/40 dark:text-violet-300',
                                'attorney_signing' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300',
                                'attorney_approved' => 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300',
                                'digitalized' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-950/40 dark:text-cyan-300',
                                'notarized' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
                                'rejected' => 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-300',
                                'failed' => 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300',
                                default => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
                            };
                            $queueLabel = is_array($summary) ? (string) $summary['queue_label'] : __('No document yet');
                            $queueDescription = is_array($summary) ? (string) $summary['queue_description'] : __('Upload and prepare the case document.');
                            $queueColor = is_array($summary) ? (string) $summary['queue_color'] : 'bg-zinc-100 text-zinc-700 ring-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:ring-zinc-700';
                        @endphp
                        <tr class="group transition hover:bg-zinc-50/80 dark:hover:bg-zinc-800/30">
                            <td class="px-5 py-4">
                                <a href="{{ $showRoute }}" wire:navigate class="block">
                                    <div class="text-sm font-semibold text-zinc-900 group-hover:text-teal-700 dark:text-zinc-100 dark:group-hover:text-teal-300">{{ $request->title }}</div>
                                    <div class="mt-1 flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                        <span class="capitalize">{{ str_replace('_', ' ', $request->request_type) }}</span>
                                        <span class="text-zinc-300 dark:text-zinc-600">·</span>
                                        <span>{{ $request->created_at?->diffForHumans() ?? '-' }}</span>
                                    </div>
                                    <div class="mt-2 flex flex-wrap items-center gap-2">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 {{ $queueColor }}">
                                            {{ $queueLabel }}
                                        </span>
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $queueDescription }}</span>
                                    </div>
                                </a>
                                {{-- Mobile-only status badge --}}
                                <div class="mt-2 md:hidden">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-medium capitalize {{ $statusColor }}">{{ str_replace('_', ' ', $request->status->value) }}</span>
                                </div>
                            </td>
                            <td class="hidden px-5 py-4 md:table-cell">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-medium capitalize {{ $statusColor }}">{{ str_replace('_', ' ', $request->status->value) }}</span>
                            </td>
                            <td class="hidden px-5 py-4 lg:table-cell">
                                @if ($isNotaryAdmin)
                                    <div class="text-xs text-zinc-400 dark:text-zinc-500">{{ $request->organization?->name ?? '—' }}</div>
                                @endif
                                <div class="text-sm text-zinc-700 dark:text-zinc-300">{{ $request->requester?->name ?? '-' }}</div>
                                <div class="mt-0.5 text-xs text-zinc-400 dark:text-zinc-500">
                                    {{ __('Notary:') }} {{ $request->notary?->name ?? __('Unassigned') }}
                                </div>
                            </td>
                            <td class="hidden px-5 py-4 xl:table-cell">
                                @if (is_array($summary) && $summary['document_count'] > 0)
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300">{{ $summary['document_count'] }}</span>
                                        @if ($summary['completed_count'] > 0)
                                            <span class="inline-flex h-5 items-center rounded-full bg-emerald-100 px-2 text-[10px] font-medium text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">{{ $summary['completed_count'] }} {{ __('done') }}</span>
                                        @endif
                                        @if ($summary['awaiting_signatures_count'] > 0)
                                            <span class="inline-flex h-5 items-center rounded-full bg-violet-100 px-2 text-[10px] font-medium text-violet-700 dark:bg-violet-950/40 dark:text-violet-300">{{ $summary['awaiting_signatures_count'] }} {{ __('signing') }}</span>
                                        @endif
                                        @if ($summary['blocked_count'] > 0)
                                            <span class="inline-flex h-5 items-center rounded-full bg-amber-100 px-2 text-[10px] font-medium text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">{{ $summary['blocked_count'] }} {{ __('blocked') }}</span>
                                        @endif
                                        @if ($summary['trust_state'] === 'trust_ready')
                                            <span class="inline-flex h-5 items-center rounded-full bg-emerald-100 px-2 text-[10px] font-medium text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                                                <svg class="mr-0.5 h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                                {{ __('trust') }}
                                            </span>
                                        @elseif ($summary['missing_certificate_count'] > 0 || $summary['missing_blockchain_count'] > 0)
                                            <span class="inline-flex h-5 items-center rounded-full bg-rose-100 px-2 text-[10px] font-medium text-rose-700 dark:bg-rose-950/40 dark:text-rose-300">
                                                <svg class="mr-0.5 h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                                                {{ __('incomplete') }}
                                            </span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('No documents') }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right">
                                <a href="{{ $showRoute }}" wire:navigate class="inline-flex items-center gap-1 rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-xs font-medium text-zinc-700 shadow-sm transition hover:border-teal-300 hover:bg-teal-50 hover:text-teal-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:border-teal-700 dark:hover:bg-teal-900/20 dark:hover:text-teal-300">
                                    {{ __('Open') }}
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-16 text-center">
                                <div class="mx-auto flex max-w-sm flex-col items-center">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                                        <svg class="h-6 w-6 text-zinc-400 dark:text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m6.75 12H9.75m3 0H9.75m0 0H9m.75 0H9m12-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                    </div>
                                    <p class="mt-3 text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('No notarizations found') }}</p>
                                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Try adjusting your filters or start a new notarization.') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($requests->hasPages())
        <div>{{ $requests->links() }}</div>
    @endif
</div>
