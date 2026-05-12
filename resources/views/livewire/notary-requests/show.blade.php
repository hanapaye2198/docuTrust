<?php

use App\Enums\NotaryRequestStatus;
use App\Enums\DocumentSignerStatus;
use App\Enums\SigningMethod;
use App\Enums\TemplateRoleType;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\NotaryRequest;
use App\Models\User;
use App\Services\CompletedDocumentArtifactService;
use App\Services\CompletedDocumentSealingService;
use App\Services\IdentityVerificationService;
use App\Services\LocationVerificationService;
use App\Services\NotaryRequestWorkflowService;
use App\Services\NotarySchedulingService;
use App\Services\SendDocumentForSignatureService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.app')] class extends Component {
    use WithFileUploads;

    public NotaryRequest $notaryRequest;
    public string $scheduleAt = '';
    public string $meetingUrl = '';
    public string $providerName = 'manual';
    public string $approvalSummary = '';
    public string $rejectionReason = '';
    public string $attachDocumentId = '';
    public string $newDocumentTitle = '';
    public $newDocumentFile = null;

    public function mount(NotaryRequest $notaryRequest): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        $notaryRequest->load(['requester', 'notary', 'documents', 'sessions', 'journals.notary', 'registerEntries']);

        $canView = $user->organization_id === $notaryRequest->organization_id || $notaryRequest->notary_user_id === $user->id;
        abort_unless($canView, 403);

        $this->notaryRequest = $notaryRequest;
    }

    public function with(): array
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        $readiness = app(NotaryRequestWorkflowService::class)->finalizationReadiness($this->notaryRequest);
        $requestDocuments = $this->notaryRequest->documents->loadMissing(['documentSigners', 'signatureFields']);

        return [
            'isNotary' => $user->role->value === 'notary',
            'canManageLifecycle' => $user->role->value !== 'notary',
            'canScheduleSession' => $this->canScheduleSession(),
            'canVerifyIdentity' => $this->canVerifyIdentity(),
            'canVerifyLocation' => $this->canVerifyLocation(),
            'canCreateRegisterEntry' => $this->canCreateRegisterEntry(),
            'canReviewNotary' => $this->canReviewNotary(),
            'workflowSteps' => $this->workflowSteps($readiness, $requestDocuments),
            'requestDocuments' => $requestDocuments,
            'recentSessions' => $this->notaryRequest->sessions,
            'journalEntries' => $this->notaryRequest->journals,
            'finalizationReadiness' => $readiness,
            'documentWorkflowStates' => $requestDocuments
                ->mapWithKeys(fn (Document $document) => [
                    $document->id => [
                        'can_prepare' => $document->canPrepareForSigning(),
                        'can_send' => $document->canSendForSigning(),
                        'signer_count' => $document->documentSigners->count(),
                        'field_count' => $document->signatureFields->count(),
                        'missing_signers' => $document->signersMissingFields()
                            ->pluck('name')
                            ->filter(fn ($name) => is_string($name) && $name !== '')
                            ->values()
                            ->all(),
                        'participant_counts' => [
                            'signers' => $document->documentSigners->where('role_type', TemplateRoleType::Signer)->count(),
                            'approvers' => $document->documentSigners->where('role_type', TemplateRoleType::Approver)->count(),
                            'recipients' => $document->documentSigners->where('role_type', TemplateRoleType::Recipient)->count(),
                            'pending' => $document->documentSigners->where('status', 'pending')->count(),
                            'signed' => $document->documentSigners->where('status', DocumentSignerStatus::Signed)->count(),
                            'approved' => $document->documentSigners->where('status', DocumentSignerStatus::Approved)->count(),
                            'notified' => $document->documentSigners->where('status', DocumentSignerStatus::Notified)->count(),
                        ],
                        'account_link_blockers' => $document->documentSigners
                            ->filter(fn (DocumentSigner $signer) => $signer->signing_method === SigningMethod::AccountVerified && $signer->user_id === null && $signer->requiresAction())
                            ->pluck('name')
                            ->filter(fn ($name) => is_string($name) && $name !== '')
                            ->values()
                            ->all(),
                        'blocking_reason' => match (true) {
                            ! $document->hasActionableParticipants() => __('Add at least one signer or approver.'),
                            ! $document->hasSignatureFields() => __('Add at least one signature field.'),
                            $document->signersMissingFields()->isNotEmpty() => __('Every signer needs at least one assigned field.'),
                            ! $document->workflowConfigurationIsValid() => __('Sequential signing order is incomplete or invalid.'),
                            default => null,
                        },
                    ],
                ])
                ->all(),
            'nextDocumentAction' => $this->nextDocumentAction($requestDocuments),
            'attachableDocuments' => Document::query()
                ->where('organization_id', $this->notaryRequest->organization_id)
                ->where(function (Builder $builder): void {
                    $builder
                        ->whereNull('notary_request_id')
                        ->orWhere('notary_request_id', $this->notaryRequest->id);
                })
                ->when(! $user->isOrganizationAdmin(), function (Builder $builder) use ($user): void {
                    $builder->where('user_id', $user->id);
                })
                ->orderByDesc('created_at')
                ->get(['id', 'title', 'status', 'notary_request_id']),
        ];
    }

    public function submitRequest(): void
    {
        try {
            app(NotaryRequestWorkflowService::class)->submit($this->notaryRequest);
            $this->refreshRequest();
            session()->flash('status', __('Notary request submitted.'));
        } catch (\RuntimeException $exception) {
            $this->addError('submitRequest', $exception->getMessage());
        }
    }

    public function markLocationVerified(): void
    {
        try {
            app(LocationVerificationService::class)->markVerified($this->notaryRequest, [
                'source' => 'manual_review',
            ]);
            $this->refreshRequest();
            session()->flash('status', __('Location verification recorded.'));
        } catch (\RuntimeException $exception) {
            $this->addError('markLocationVerified', $exception->getMessage());
        }
    }

    public function scheduleSession(): void
    {
        $validated = $this->validate([
            'scheduleAt' => ['required', 'date'],
            'meetingUrl' => ['nullable', 'url', 'max:1000'],
            'providerName' => ['required', 'string', 'max:64'],
        ]);

        try {
            app(NotarySchedulingService::class)->schedule(
                $this->notaryRequest,
                new DateTimeImmutable($validated['scheduleAt']),
                trim($validated['providerName']),
                trim((string) $validated['meetingUrl']) !== '' ? trim((string) $validated['meetingUrl']) : null,
            );

            $this->refreshRequest();
            session()->flash('status', __('Session scheduled.'));
        } catch (\RuntimeException $exception) {
            $this->addError('scheduleSession', $exception->getMessage());
        }
    }

    public function startSession(int $sessionId): void
    {
        try {
            $session = $this->notaryRequest->sessions()->whereKey($sessionId)->firstOrFail();

            app(NotarySchedulingService::class)->start($session);

            $this->refreshRequest();
            session()->flash('status', __('Video session started.'));
        } catch (\RuntimeException $exception) {
            $this->addError('startSession', $exception->getMessage());
        }
    }

    public function completeSession(int $sessionId): void
    {
        try {
            $session = $this->notaryRequest->sessions()->whereKey($sessionId)->firstOrFail();

            app(NotarySchedulingService::class)->complete($session, [
                'face_matches_id' => true,
                'id_valid_not_expired' => true,
                'signer_conscious_aware' => true,
                'signer_agrees_voluntarily' => true,
                'signer_in_philippines' => true,
                'session_recorded' => true,
            ]);

            $this->refreshRequest();
            session()->flash('status', __('Video session completed. All verification checks passed.'));
        } catch (\RuntimeException $exception) {
            $this->addError('completeSession', $exception->getMessage());
        }
    }

    public function markIdentityVerified(): void
    {
        try {
            app(IdentityVerificationService::class)->verify($this->notaryRequest, [
                'id_document_type' => $this->notaryRequest->id_document_type ?? 'manual_review',
                'id_document_number' => $this->notaryRequest->id_document_number ?? 'verified_manually',
                'id_document_path' => $this->notaryRequest->id_document_path ?? '',
                'otp_verified' => true,
            ]);

            $this->refreshRequest();
            session()->flash('status', __('Identity verification recorded.'));
        } catch (\RuntimeException $exception) {
            $this->addError('markIdentityVerified', $exception->getMessage());
        }
    }

    public function approveRequest(): void
    {
        try {
            app(NotaryRequestWorkflowService::class)->approve($this->notaryRequest, [
                'identity_matched' => true,
                'voluntary_consent' => true,
                'jurisdiction_valid' => true,
            ], $this->approvalSummary !== '' ? trim($this->approvalSummary) : null);

            $this->refreshRequest();
            $this->approvalSummary = '';
            session()->flash('status', __('Notary approval recorded.'));
        } catch (\RuntimeException $exception) {
            $this->addError('approveRequest', $exception->getMessage());
        }
    }

    public function rejectRequest(): void
    {
        $validated = $this->validate([
            'rejectionReason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            app(NotaryRequestWorkflowService::class)->reject($this->notaryRequest, trim($validated['rejectionReason']));

            $this->refreshRequest();
            $this->rejectionReason = '';
            session()->flash('status', __('Request rejected.'));
        } catch (\RuntimeException $exception) {
            $this->addError('rejectRequest', $exception->getMessage());
        }
    }

    public function digitalizeRequest(): void
    {
        try {
            app(\App\Services\NotaryDigitalizationService::class)->digitalize($this->notaryRequest);
            $this->refreshRequest();
            session()->flash('status', __('Digital notarization completed. Seal applied, certificates generated.'));
        } catch (\RuntimeException $exception) {
            $this->addError('digitalizeRequest', $exception->getMessage());
        }
    }

    public function finalizeRequest(): void
    {
        try {
            app(NotaryRequestWorkflowService::class)->finalize($this->notaryRequest);
            $this->refreshRequest();
            session()->flash('status', __('Notary request finalized.'));
        } catch (\RuntimeException $exception) {
            $this->addError('finalizeRequest', $exception->getMessage());
        }
    }

    public function attachDocument(): void
    {
        $validated = $this->validate([
            'attachDocumentId' => ['required', 'integer', 'exists:documents,id'],
        ]);

        $document = Document::query()->findOrFail((int) $validated['attachDocumentId']);

        app(NotaryRequestWorkflowService::class)->attachDocument($this->notaryRequest, $document);

        $this->attachDocumentId = '';
        $this->refreshRequest();
        session()->flash('status', __('Document linked to notary request.'));
    }

    public function detachDocument(int $documentId): void
    {
        $document = Document::query()->findOrFail($documentId);

        app(NotaryRequestWorkflowService::class)->detachDocument($this->notaryRequest, $document);

        $this->refreshRequest();
        session()->flash('status', __('Document removed from notary request.'));
    }

    public function createDocument(): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        $validated = $this->validate([
            'newDocumentTitle' => ['required', 'string', 'max:255'],
            'newDocumentFile' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'extensions:pdf'],
        ]);

        try {
            $path = $this->newDocumentFile->store('documents', (string) config('filesystems.docutrust_disk', 'local'));

            $document = $user->documents()->create([
                'notary_request_id' => $this->notaryRequest->id,
                'title' => trim((string) $validated['newDocumentTitle']),
                'file_path' => $path,
                'status' => \App\Enums\DocumentStatus::Draft,
            ]);

            app(NotaryRequestWorkflowService::class)->attachDocument($this->notaryRequest, $document);

            $this->newDocumentTitle = '';
            $this->newDocumentFile = null;
            $this->resetValidation(['newDocumentTitle', 'newDocumentFile']);
            $this->refreshRequest();
            session()->flash('status', __('Document uploaded and linked to this notary request.'));
        } catch (\Throwable $throwable) {
            Log::channel('errors')->error('Notary request document upload failed', [
                'notary_request_id' => $this->notaryRequest->id,
                'user_id' => $user->id,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);

            $this->addError('newDocumentFile', __('Unable to upload document right now. Please try again.'));
        }
    }

    public function sendLinkedDocument(int $documentId): void
    {
        $document = Document::query()
            ->whereKey($documentId)
            ->where('notary_request_id', $this->notaryRequest->id)
            ->firstOrFail();

        try {
            app(SendDocumentForSignatureService::class)->send($document);
            $this->refreshRequest();
            session()->flash('status', __('Document sent for signature.'));
        } catch (\RuntimeException $exception) {
            $this->addError('sendDocument'.$documentId, $exception->getMessage());
        }
    }

    public function generateDocumentCertificate(int $documentId): void
    {
        $document = $this->resolveCompletedLinkedDocument($documentId);

        app(CompletedDocumentArtifactService::class)->ensureReady($document);
        $this->refreshRequest();

        $updatedDocument = $this->notaryRequest->documents->firstWhere('id', $documentId);
        if (! $updatedDocument instanceof Document || ! is_string($updatedDocument->certificate_path) || $updatedDocument->certificate_path === '') {
            $this->addError('artifactDocument'.$documentId, __('Unable to generate a completion certificate for this document right now.'));

            return;
        }

        session()->flash('status', __('Completion certificate generated.'));
    }

    public function refreshBlockchainProof(int $documentId): void
    {
        $document = $this->resolveCompletedLinkedDocument($documentId);

        app(CompletedDocumentSealingService::class)->seal($document);
        $this->refreshRequest();

        $updatedDocument = $this->notaryRequest->documents->firstWhere('id', $documentId);
        if (
            ! $updatedDocument instanceof Document
            || $updatedDocument->documentHash === null
            || ! is_string($updatedDocument->documentHash->hash)
            || $updatedDocument->documentHash->hash === ''
        ) {
            $this->addError('artifactDocument'.$documentId, __('Unable to generate a document hash for this document right now.'));

            return;
        }

        if (! is_string($updatedDocument->documentHash->transaction_id) || $updatedDocument->documentHash->transaction_id === '') {
            $this->addError('artifactDocument'.$documentId, __('Blockchain proof could not be refreshed. The document hash was stored, but no blockchain transaction was returned.'));

            return;
        }

        session()->flash('status', __('Blockchain proof refreshed.'));
    }

    private function refreshRequest(): void
    {
        $this->notaryRequest->refresh()->load(['requester', 'notary', 'documents.documentSigners', 'documents.signatureFields', 'documents.documentHash', 'sessions', 'journals.notary', 'registerEntries']);
    }

    private function resolveCompletedLinkedDocument(int $documentId): Document
    {
        $document = Document::query()
            ->whereKey($documentId)
            ->where('notary_request_id', $this->notaryRequest->id)
            ->firstOrFail();

        abort_unless($document->status->value === 'completed', 422);

        return $document;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Document>  $documents
     * @return array{label: string, description: string, href: string}|null
     */
    private function nextDocumentAction(\Illuminate\Support\Collection $documents): ?array
    {
        foreach ($documents as $document) {
            if ($document->status->value !== 'draft') {
                continue;
            }

            if (! $document->hasActionableParticipants()) {
                return [
                    'label' => __('Manage participants'),
                    'description' => __('Add signers or approvers to ":title".', ['title' => $document->title]),
                    'href' => route('documents.show', $document),
                ];
            }

            if (! $document->hasSignatureFields() || $document->signersMissingFields()->isNotEmpty() || ! $document->workflowConfigurationIsValid()) {
                return [
                    'label' => __('Prepare document'),
                    'description' => __('Finish assigning signature fields for ":title".', ['title' => $document->title]),
                    'href' => route('documents.prepare', $document),
                ];
            }

            if ($document->canSendForSigning()) {
                return [
                    'label' => __('Review ready document'),
                    'description' => __('":title" is ready to send for signature.', ['title' => $document->title]),
                    'href' => route('documents.show', $document),
                ];
            }
        }

        foreach ($documents as $document) {
            if ($document->status->value === 'pending') {
                return [
                    'label' => __('Monitor signing'),
                    'description' => __('Track participant progress for ":title".', ['title' => $document->title]),
                    'href' => route('documents.show', $document),
                ];
            }
        }

        return null;
    }

    private function canVerifyIdentity(): bool
    {
        return $this->notaryRequest->status === NotaryRequestStatus::Submitted;
    }

    private function canVerifyLocation(): bool
    {
        return in_array($this->notaryRequest->status, [
            NotaryRequestStatus::Submitted,
            NotaryRequestStatus::IdentityVerified,
        ], true);
    }

    private function canScheduleSession(): bool
    {
        return in_array($this->notaryRequest->status, [
            NotaryRequestStatus::Submitted,
            NotaryRequestStatus::IdentityVerified,
            NotaryRequestStatus::LocationVerified,
        ], true);
    }

    private function canReviewNotary(): bool
    {
        return in_array($this->notaryRequest->status, [
            NotaryRequestStatus::SessionScheduled,
            NotaryRequestStatus::SessionInProgress,
            NotaryRequestStatus::LocationVerified,
            NotaryRequestStatus::IdentityVerified,
        ], true);
    }

    private function canCreateRegisterEntry(): bool
    {
        return in_array($this->notaryRequest->status, [
            NotaryRequestStatus::AttorneyApproved,
            NotaryRequestStatus::Notarized,
        ], true);
    }

    /**
     * @param  array{ready: bool, issues: list<string>, documents: array<int, array<string, mixed>>}  $readiness
     * @param  \Illuminate\Support\Collection<int, Document>  $documents
     * @return list<array{label: string, description: string, state: string}>
     */
    private function workflowSteps(array $readiness, \Illuminate\Support\Collection $documents): array
    {
        $hasSubmitted = $this->notaryRequest->submitted_at !== null || $this->notaryRequest->status !== NotaryRequestStatus::Draft;
        $hasDocuments = $documents->isNotEmpty();
        $documentsReady = $hasDocuments
            && $documents->every(fn (Document $document) => $document->status->value === 'completed');
        $hasActiveSession = $this->notaryRequest->sessions->isNotEmpty();
        $hasRegisterEntries = $this->notaryRequest->registerEntries->isNotEmpty();
        $isNotarized = $this->notaryRequest->status === NotaryRequestStatus::Notarized;

        return [
            [
                'label' => __('Request submitted'),
                'description' => __('Open the case and assign the operational owner.'),
                'state' => $hasSubmitted ? 'complete' : 'current',
            ],
            [
                'label' => __('Identity and location'),
                'description' => __('Record identity proof and Philippines-only verification evidence.'),
                'state' => match (true) {
                    in_array($this->notaryRequest->status, [
                        NotaryRequestStatus::LocationVerified,
                        NotaryRequestStatus::SessionScheduled,
                        NotaryRequestStatus::SessionInProgress,
                        NotaryRequestStatus::AttorneyApproved,
                        NotaryRequestStatus::Notarized,
                    ], true) => 'complete',
                    in_array($this->notaryRequest->status, [
                        NotaryRequestStatus::Submitted,
                        NotaryRequestStatus::IdentityVerified,
                    ], true) => 'current',
                    default => 'upcoming',
                },
            ],
            [
                'label' => __('Session and review'),
                'description' => __('Schedule the meeting, complete live verification, and record notary review.'),
                'state' => match (true) {
                    in_array($this->notaryRequest->status, [
                        NotaryRequestStatus::AttorneyApproved,
                        NotaryRequestStatus::Notarized,
                    ], true) => 'complete',
                    in_array($this->notaryRequest->status, [
                        NotaryRequestStatus::SessionScheduled,
                        NotaryRequestStatus::SessionInProgress,
                    ], true) || $hasActiveSession => 'current',
                    default => 'upcoming',
                },
            ],
            [
                'label' => __('Documents and register'),
                'description' => __('Complete signer workflows and create the notarial register entry.'),
                'state' => match (true) {
                    $documentsReady && $hasRegisterEntries => 'complete',
                    $hasDocuments || $hasRegisterEntries => 'current',
                    default => 'upcoming',
                },
            ],
            [
                'label' => __('Finalize and anchor'),
                'description' => __('Generate final artifacts, notarize the request, and confirm immutable storage readiness.'),
                'state' => match (true) {
                    $isNotarized => 'complete',
                    $readiness['ready'] => 'current',
                    default => 'upcoming',
                },
            ],
        ];
    }
}; ?>

<div class="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-8">
    @if (session('status'))
        <div class="rounded-2xl border border-emerald-200/90 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-100">
            {{ session('status') }}
        </div>
    @endif

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
        <div class="space-y-3">
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50 sm:text-3xl">{{ $notaryRequest->title }}</h1>
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">{{ str_replace('_', ' ', $notaryRequest->status->value) }}</span>
                <span class="rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ str_replace('_', ' ', $notaryRequest->request_type) }}</span>
            </div>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Requester') }}: {{ $notaryRequest->requester?->name ?? '-' }} • {{ __('Assigned notary') }}: {{ $notaryRequest->notary?->name ?? __('Unassigned') }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($canManageLifecycle && $notaryRequest->status === NotaryRequestStatus::Draft)
                <flux:button variant="primary" wire:click="submitRequest">{{ __('Submit request') }}</flux:button>
            @endif
            @if ($canManageLifecycle && $notaryRequest->status === NotaryRequestStatus::AttorneyApproved)
                <flux:button variant="outline" wire:click="digitalizeRequest">{{ __('Apply digital seal') }}</flux:button>
                <flux:button variant="primary" wire:click="finalizeRequest" :disabled="! $finalizationReadiness['ready']">{{ __('Finalize notarization') }}</flux:button>
            @endif
            <flux:button variant="ghost" :href="Auth::user()?->role->value === 'notary' ? route('notary.requests.index') : route('notary-requests.index')" wire:navigate>{{ __('Back') }}</flux:button>
        </div>
        @if ($errors->has('submitRequest') || $errors->has('digitalizeRequest') || $errors->has('finalizeRequest'))
            <div class="mt-2">
                <flux:error name="submitRequest" />
                <flux:error name="digitalizeRequest" />
                <flux:error name="finalizeRequest" />
            </div>
        @endif
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="ui-panel p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Workflow guide') }}</h2>
                        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ __('This request moves from intake to verification, review, register entry, and immutable output.') }}</p>
                    </div>
                    <span class="rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ __('5 stages') }}</span>
                </div>
                <div class="mt-5 grid gap-3 lg:grid-cols-5">
                    @foreach ($workflowSteps as $index => $step)
                        @php
                            $stepStyles = match ($step['state']) {
                                'complete' => 'border-emerald-200 bg-emerald-50 dark:border-emerald-900/40 dark:bg-emerald-950/30',
                                'current' => 'border-sky-200 bg-sky-50 dark:border-sky-900/40 dark:bg-sky-950/30',
                                default => 'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900',
                            };
                            $badgeStyles = match ($step['state']) {
                                'complete' => 'bg-emerald-600 text-white dark:bg-emerald-500',
                                'current' => 'bg-sky-600 text-white dark:bg-sky-500',
                                default => 'bg-zinc-200 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-100',
                            };
                            $stateLabel = match ($step['state']) {
                                'complete' => __('Complete'),
                                'current' => __('Current'),
                                default => __('Upcoming'),
                            };
                        @endphp
                        <div class="rounded-2xl border p-4 {{ $stepStyles }}">
                            <div class="flex items-center justify-between gap-3">
                                <span class="inline-flex size-7 items-center justify-center rounded-full text-xs font-semibold {{ $badgeStyles }}">{{ $index + 1 }}</span>
                                <span class="text-[11px] font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ $stateLabel }}</span>
                            </div>
                            <div class="mt-4 text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $step['label'] }}</div>
                            <div class="mt-2 text-xs leading-5 text-zinc-600 dark:text-zinc-400">{{ $step['description'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="ui-panel p-6">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Case summary') }}</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="text-xs uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Submitted') }}</div>
                        <div class="mt-2 text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $notaryRequest->submitted_at?->toDateTimeString() ?? __('Not yet') }}</div>
                    </div>
                    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="text-xs uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Documents linked') }}</div>
                        <div class="mt-2 text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $requestDocuments->count() }}</div>
                    </div>
                </div>
                @if (($notaryRequest->metadata['notes'] ?? '') !== '')
                    <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300">
                        {{ $notaryRequest->metadata['notes'] }}
                    </div>
                @endif

                @if ($nextDocumentAction !== null)
                    <div class="mt-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-4 dark:border-sky-900/40 dark:bg-sky-950/30">
                        <div class="text-xs font-semibold uppercase tracking-wider text-sky-700 dark:text-sky-300">{{ __('Next recommended action') }}</div>
                        <div class="mt-2 text-sm font-medium text-sky-900 dark:text-sky-100">{{ $nextDocumentAction['description'] }}</div>
                        <div class="mt-3">
                            <flux:button variant="outline" :href="$nextDocumentAction['href']" wire:navigate>{{ $nextDocumentAction['label'] }}</flux:button>
                        </div>
                    </div>
                @endif
            </div>

            <div class="ui-panel p-6">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Documents') }}</h2>
                @if ($canManageLifecycle)
                    <div class="mt-4 flex flex-col gap-4 rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900/30">
                        <div class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Upload a new document for this request') }}</div>
                        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] lg:items-end">
                            <flux:field>
                                <flux:label>{{ __('Document title') }}</flux:label>
                                <flux:input wire:model="newDocumentTitle" type="text" placeholder="{{ __('e.g. Affidavit of support') }}" />
                                <flux:error name="newDocumentTitle" />
                            </flux:field>
                            <flux:field>
                                <flux:label>{{ __('PDF file') }}</flux:label>
                                <input
                                    type="file"
                                    wire:model="newDocumentFile"
                                    accept="application/pdf,.pdf"
                                    class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                                />
                                <div wire:loading wire:target="newDocumentFile" class="mt-2 text-xs text-teal-600 dark:text-teal-400">{{ __('Uploading...') }}</div>
                                <flux:error name="newDocumentFile" />
                            </flux:field>
                            <flux:button variant="primary" type="button" wire:click="createDocument">{{ __('Upload document') }}</flux:button>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-col gap-3 rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900/30">
                        <div class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Link an existing document') }}</div>
                        <div class="flex flex-col gap-3 sm:flex-row">
                            <select wire:model="attachDocumentId" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 sm:flex-1">
                                <option value="">{{ __('Select a document') }}</option>
                                @foreach ($attachableDocuments as $documentOption)
                                    @continue($documentOption->notary_request_id === $notaryRequest->id)
                                    <option value="{{ $documentOption->id }}">{{ $documentOption->title }} ({{ $documentOption->status->value }})</option>
                                @endforeach
                            </select>
                            <flux:button variant="outline" type="button" wire:click="attachDocument">{{ __('Attach document') }}</flux:button>
                        </div>
                        <flux:error name="attachDocumentId" />
                    </div>
                @endif
                <div class="mt-4 space-y-3">
                    @forelse ($requestDocuments as $document)
                        @php
                            $artifactState = collect($finalizationReadiness['documents'])->firstWhere('document_id', $document->id);
                            $workflowState = $documentWorkflowStates[$document->id] ?? null;
                        @endphp
                        <div class="rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="flex items-center justify-between gap-3">
                                <a href="{{ route('documents.show', $document) }}" wire:navigate class="min-w-0 flex-1">
                                    <div class="truncate font-medium text-zinc-800 dark:text-zinc-200">{{ $document->title }}</div>
                                    <div class="mt-1 text-zinc-500 dark:text-zinc-400">{{ $document->status->value }}</div>
                                    @if (is_array($workflowState))
                                        <div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ trans_choice(':count signer|:count signers', $workflowState['participant_counts']['signers'], ['count' => $workflowState['participant_counts']['signers']]) }}
                                            <span class="mx-1 text-zinc-300 dark:text-zinc-600">•</span>
                                            {{ trans_choice(':count approver|:count approvers', $workflowState['participant_counts']['approvers'], ['count' => $workflowState['participant_counts']['approvers']]) }}
                                            <span class="mx-1 text-zinc-300 dark:text-zinc-600">•</span>
                                            {{ trans_choice(':count recipient|:count recipients', $workflowState['participant_counts']['recipients'], ['count' => $workflowState['participant_counts']['recipients']]) }}
                                            <span class="mx-1 text-zinc-300 dark:text-zinc-600">•</span>
                                            {{ trans_choice(':count field|:count fields', $workflowState['field_count'], ['count' => $workflowState['field_count']]) }}
                                        </div>
                                        <div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ trans_choice(':count pending|:count pending', $workflowState['participant_counts']['pending'], ['count' => $workflowState['participant_counts']['pending']]) }}
                                            <span class="mx-1 text-zinc-300 dark:text-zinc-600">•</span>
                                            {{ trans_choice(':count signed|:count signed', $workflowState['participant_counts']['signed'], ['count' => $workflowState['participant_counts']['signed']]) }}
                                            <span class="mx-1 text-zinc-300 dark:text-zinc-600">•</span>
                                            {{ trans_choice(':count approved|:count approved', $workflowState['participant_counts']['approved'], ['count' => $workflowState['participant_counts']['approved']]) }}
                                        </div>
                                    @endif
                                    @if (is_array($artifactState))
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <span class="rounded-full px-2.5 py-1 text-[11px] font-medium {{ $artifactState['has_final_pdf'] ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' }}">{{ __('Final PDF') }}</span>
                                            <span class="rounded-full px-2.5 py-1 text-[11px] font-medium {{ $artifactState['has_certificate'] ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' }}">{{ __('Certificate') }}</span>
                                            <span class="rounded-full px-2.5 py-1 text-[11px] font-medium {{ $artifactState['has_document_hash'] ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' }}">{{ __('Hash') }}</span>
                                            <span class="rounded-full px-2.5 py-1 text-[11px] font-medium {{ $artifactState['has_blockchain_transaction'] ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' }}">{{ __('Blockchain') }}</span>
                                        </div>
                                        @if ($artifactState['issues'] !== [])
                                            <div class="mt-2 text-xs text-amber-700 dark:text-amber-300">
                                                {{ implode(' ', $artifactState['issues']) }}
                                            </div>
                                        @endif
                                    @endif
                                </a>
                                @if ($canManageLifecycle)
                                    <div class="flex flex-wrap items-center gap-2">
                                        <flux:button variant="ghost" :href="route('documents.show', $document)" wire:navigate>{{ __('Manage participants') }}</flux:button>
                                        @if ($document->status->value === 'draft')
                                            <flux:button variant="outline" :href="route('documents.prepare', $document)" wire:navigate :disabled="! ($workflowState['can_prepare'] ?? false)">{{ __('Prepare') }}</flux:button>
                                            <flux:button variant="outline" type="button" wire:click="sendLinkedDocument({{ $document->id }})" :disabled="! ($workflowState['can_send'] ?? false)">{{ __('Send') }}</flux:button>
                                        @elseif ($document->status->value === 'completed')
                                            @if (! ($artifactState['has_certificate'] ?? false))
                                                <flux:button variant="outline" type="button" wire:click="generateDocumentCertificate({{ $document->id }})">{{ __('Generate certificate') }}</flux:button>
                                            @endif
                                            @if (! ($artifactState['has_blockchain_transaction'] ?? false))
                                                <flux:button variant="outline" type="button" wire:click="refreshBlockchainProof({{ $document->id }})">{{ __('Refresh blockchain') }}</flux:button>
                                            @endif
                                        @endif
                                        <flux:button variant="ghost" type="button" wire:click="detachDocument({{ $document->id }})">{{ __('Remove') }}</flux:button>
                                    </div>
                                @endif
                            </div>
                            @if ($canManageLifecycle)
                                @error('sendDocument'.$document->id)
                                    <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-300">
                                        {{ $message }}
                                    </div>
                                @enderror
                            @endif
                            @if ($canManageLifecycle)
                                @error('artifactDocument'.$document->id)
                                    <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-300">
                                        {{ $message }}
                                    </div>
                                @enderror
                            @endif
                            @if (is_array($workflowState) && $document->status->value === 'draft' && $workflowState['missing_signers'] !== [])
                                <div class="mt-3 text-xs text-amber-700 dark:text-amber-300">
                                    {{ __('Missing fields for: :signers', ['signers' => implode(', ', $workflowState['missing_signers'])]) }}
                                </div>
                            @endif
                            @if (is_array($workflowState) && is_string($workflowState['blocking_reason']) && $workflowState['blocking_reason'] !== '' && $document->status->value === 'draft')
                                <div class="mt-2 text-xs text-amber-700 dark:text-amber-300">
                                    {{ $workflowState['blocking_reason'] }}
                                </div>
                            @endif
                            @if (is_array($workflowState) && $workflowState['account_link_blockers'] !== [])
                                <div class="mt-2 text-xs text-amber-700 dark:text-amber-300">
                                    {{ __('Account-linked signer setup is incomplete for: :signers', ['signers' => implode(', ', $workflowState['account_link_blockers'])]) }}
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-zinc-300 px-4 py-6 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">{{ __('No documents linked yet.') }}</div>
                    @endforelse
                </div>
            </div>

            <div class="ui-panel p-6">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Finalization readiness') }}</h2>
                @if ($finalizationReadiness['ready'])
                    <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-100">
                        {{ __('All linked documents have the required notarization artifacts.') }}
                    </div>
                @else
                    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                        <div class="font-medium">{{ __('This request is not ready to finalize yet.') }}</div>
                        <ul class="mt-2 list-disc pl-5">
                            @foreach ($finalizationReadiness['issues'] as $issue)
                                <li>{{ $issue }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="ui-panel p-6">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Journal') }}</h2>
                <div class="mt-4 space-y-4">
                    @forelse ($journalEntries as $entry)
                        <div class="rounded-xl border border-zinc-200 bg-white p-4 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="flex items-center justify-between gap-4">
                                <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ str_replace('_', ' ', $entry->entry_type) }}</div>
                                <div class="text-xs text-zinc-400 dark:text-zinc-500">{{ $entry->recorded_at?->toDateTimeString() ?? '-' }}</div>
                            </div>
                            <div class="mt-2 text-zinc-600 dark:text-zinc-300">{{ $entry->summary }}</div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-zinc-300 px-4 py-6 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">{{ __('No journal entries yet.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="ui-panel p-6">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Session scheduling') }}</h2>
                @if ($canScheduleSession)
                    <div class="mt-4 space-y-4">
                        <flux:field>
                            <flux:label>{{ __('Scheduled for') }}</flux:label>
                            <flux:input wire:model="scheduleAt" type="datetime-local" />
                            <flux:error name="scheduleAt" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('Provider') }}</flux:label>
                            <flux:input wire:model="providerName" type="text" />
                            <flux:error name="providerName" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('Meeting URL') }}</flux:label>
                            <flux:input wire:model="meetingUrl" type="url" placeholder="https://..." />
                            <flux:error name="meetingUrl" />
                        </flux:field>
                        <flux:button variant="outline" type="button" wire:click="scheduleSession">{{ __('Schedule session') }}</flux:button>
                        <flux:error name="scheduleSession" />
                    </div>
                @else
                    <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300">
                        {{ __('Session scheduling becomes available after identity or location verification.') }}
                    </div>
                @endif
                @if ($recentSessions->isNotEmpty())
                    <div class="mt-4 space-y-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                        @foreach ($recentSessions as $session)
                            <div class="rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-medium text-zinc-800 dark:text-zinc-200">{{ $session->provider_name }}</div>
                                        <div class="text-zinc-500 dark:text-zinc-400">{{ $session->scheduled_for?->toDateTimeString() ?? '-' }}</div>
                                    </div>
                                    <span class="rounded-full bg-zinc-100 px-2.5 py-1 text-[11px] font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $session->status }}</span>
                                </div>
                                @if ($session->status === 'scheduled')
                                    <div class="mt-2">
                                        <flux:button variant="primary" size="sm" type="button" wire:click="startSession({{ $session->id }})">{{ __('Start session') }}</flux:button>
                                        <flux:error name="startSession" />
                                    </div>
                                @elseif ($session->status === 'in_progress')
                                    <div class="mt-2">
                                        <flux:button variant="outline" size="sm" type="button" wire:click="completeSession({{ $session->id }})">{{ __('Complete session') }}</flux:button>
                                        <flux:error name="completeSession" />
                                    </div>
                                @endif
                                @if ($session->meeting_url)
                                    <div class="mt-2">
                                        <a href="{{ $session->meeting_url }}" target="_blank" class="text-xs text-teal-600 hover:text-teal-700 dark:text-teal-400">{{ __('Join meeting') }} →</a>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="ui-panel p-6">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Verification steps') }}</h2>
                <div class="mt-4 space-y-3">
                    @if ($canVerifyIdentity)
                        <flux:button variant="outline" type="button" wire:click="markIdentityVerified">{{ __('Mark identity verified') }}</flux:button>
                        <flux:error name="markIdentityVerified" />
                    @endif
                    @if ($canVerifyLocation)
                        <flux:button variant="outline" type="button" wire:click="markLocationVerified">{{ __('Mark location verified') }}</flux:button>
                        <flux:error name="markLocationVerified" />
                    @endif
                    @if (! $canVerifyIdentity && ! $canVerifyLocation)
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300">
                            {{ __('Verification actions appear after the request is submitted.') }}
                        </div>
                    @endif
                </div>
            </div>

            @if ($isNotary)
                <div class="ui-panel p-6">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Notarial register') }}</h2>
                    @if ($canCreateRegisterEntry)
                        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Create the official notarial register entry with all 9 required fields.') }}</p>
                        <div class="mt-4">
                            <flux:button variant="primary" :href="route('notary.register-entry', $notaryRequest)" wire:navigate>{{ __('Create register entry') }}</flux:button>
                        </div>
                    @else
                        <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300">
                            {{ __('Register entry creation becomes available after attorney approval.') }}
                        </div>
                    @endif
                    @if ($notaryRequest->registerEntries->isNotEmpty())
                        <div class="mt-4 space-y-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                            @foreach ($notaryRequest->registerEntries as $entry)
                                <div class="rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                                    <div class="font-medium text-zinc-800 dark:text-zinc-200">{{ __('Entry') }} {{ str_pad($entry->entry_number, 3, '0', STR_PAD_LEFT) }} — {{ ucfirst(str_replace('_', ' ', $entry->notarial_act_type)) }}</div>
                                    <div class="text-zinc-500 dark:text-zinc-400">{{ $entry->document_title }}</div>
                                    <div class="text-xs text-zinc-400 dark:text-zinc-500">{{ $entry->notarized_at?->timezone('Asia/Manila')->format('M j, Y g:i:s A') }} (PHT)</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="ui-panel p-6">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Notary review') }}</h2>
                    @if ($canReviewNotary)
                        <div class="mt-4 space-y-4">
                            <flux:field>
                                <flux:label>{{ __('Approval summary') }}</flux:label>
                                <flux:textarea wire:model="approvalSummary" rows="4" placeholder="{{ __('Observed signer awareness, reviewed identity, and validated voluntary signing.') }}" />
                            </flux:field>
                            <flux:button variant="primary" type="button" wire:click="approveRequest">{{ __('Approve request') }}</flux:button>
                            <flux:error name="approveRequest" />

                            <div class="border-t border-zinc-200 pt-4 dark:border-zinc-700">
                                <flux:field>
                                    <flux:label>{{ __('Rejection reason') }}</flux:label>
                                    <flux:textarea wire:model="rejectionReason" rows="4" placeholder="{{ __('Explain why this request cannot proceed.') }}" />
                                    <flux:error name="rejectionReason" />
                                </flux:field>
                                <div class="mt-3">
                                    <flux:button variant="outline" type="button" wire:click="rejectRequest">{{ __('Reject request') }}</flux:button>
                                    <flux:error name="rejectRequest" />
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300">
                            {{ __('Notary review is available only after identity, location, or session verification has started.') }}
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
