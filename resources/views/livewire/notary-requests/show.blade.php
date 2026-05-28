<?php

use App\Enums\DocumentStatus;
use App\Enums\NotaryIdentityVerificationStatus;
use App\Enums\NotaryRequestStatus;
use App\Enums\EInvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\DocumentSignerStatus;
use App\Enums\SigningMethod;
use App\Enums\TemplateRoleType;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\EInvoice;
use App\Models\NotaryIdentityVerification;
use App\Models\NotaryRequest;
use App\Models\NotarySigner;
use App\Models\Payment;
use App\Models\User;
use App\Services\CompletedDocumentArtifactService;
use App\Services\CompletedDocumentSealingService;
use App\Services\IdentityVerificationService;
use App\Services\LocationVerificationService;
use App\Services\EInvoiceService;
use App\Services\NotaryPaymentService;
use App\Services\NotaryNotificationService;
use App\Services\NotaryRequestWorkflowService;
use App\Services\NotarySchedulingService;
use App\Services\EnotaryInvitationService;
use App\Services\NotaryParticipantSyncService;
use App\Services\OtpService;
use App\Enums\UserWorkspace;
use App\Models\EnotaryInvitation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.app')] class extends Component {
    use WithFileUploads;

    public NotaryRequest $notaryRequest;
    public string $scheduleAt = '';
    public string $meetingUrl = '';
    public string $providerName = 'jitsi';
    public string $approvalSummary = '';
    public string $rejectionReason = '';
    public string $attachDocumentId = '';
    public string $newDocumentTitle = '';
    public $newDocumentFile = null;
    public $replaceDocumentFile = null;
    public ?int $replaceDocumentId = null;

    // Add signer form (attorney only)
    public string $newSignerName = '';
    public string $newSignerEmail = '';
    public string $newSignerPhone = '';
    public string $newSignerAddress = '';
    public string $newSignerRole = 'signer';

    public string $geoLatitude = '';

    public string $geoLongitude = '';

    public ?int $geoSignerId = null;

    public string $identityOtpCode = '';

    public ?int $identityTargetSignerId = null;

    public string $pendingIdType = 'passport';

    public string $pendingIdNumber = '';

    public $idImageFile = null;

    public $selfieImageFile = null;

    public string $identityRejectReason = '';

    public ?int $identityRejectId = null;

    public string $paymentGateway = '';

    /**
     * @var list<array{code: string, name: string}>
     */
    public array $enabledPaymentGateways = [];

    /**
     * @var array<string, bool>
     */
    public array $sessionChecklist = [];

    #[Url(as: 'tab')]
    public string $activeTab = 'documents';

    public bool $showAuditPanel = false;

    public function mount(NotaryRequest $notaryRequest): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        $notaryRequest->load(['requester', 'notary', 'documents', 'sessions', 'journals.notary', 'registerEntries', 'payments', 'eInvoices', 'attorneyNotarialRegistry', 'signers', 'identityVerifications.signer', 'identityVerifications.verifier', 'geoLogs.signer']);

        Gate::authorize('view', $notaryRequest);

        $this->notaryRequest = $notaryRequest;
        $this->loadPaymentGateways();

        $keys = config('docutrust.notary.verification_checklist', []);
        $this->sessionChecklist = array_fill_keys($keys, false);

        if ($user->role === \App\Enums\UserRole::Notary) {
            $this->activeTab = $this->resolveDefaultAttorneyTab();

            if ($this->activeTab === 'session') {
                $this->syncVideoPartiesIfReady(notify: false);
            }
        }

        $this->ensureActiveTabIsAvailable();
    }

    public function setActiveTab(string $tab): void
    {
        if (! in_array($tab, $this->availableTabs(), true)) {
            $this->ensureActiveTabIsAvailable();
            return;
        }

        $this->activeTab = $tab;

        if ($tab === 'session') {
            $this->syncVideoPartiesIfReady(notify: false);
        }
    }

    public function openVideoSessionWorkspace(): void
    {
        $this->activeTab = 'session';
        $this->syncVideoPartiesIfReady(forceResend: false, notify: true, deliverSynchronously: true);
    }

    public function openPaymentSection(): void
    {
        if (! in_array('closing', $this->availableTabs(), true)) {
            return;
        }

        $this->activeTab = 'closing';
        $this->dispatch('scroll-to-section', id: 'section-payment');
    }

    /**
     * @return list<string>
     */
    public function availableTabs(): array
    {
        $tabs = ['documents', 'parties'];

        if ($this->panelVisibility()['session']) {
            $tabs[] = 'session';
        }

        if ($this->panelVisibility()['closing']) {
            $tabs[] = 'closing';
        }

        if ($this->panelVisibility()['audit']) {
            $tabs[] = 'audit';
        }

        return $tabs;
    }

    /**
     * @return array{session: bool, closing: bool, audit: bool, identity: bool}
     */
    public function panelVisibility(): array
    {
        $request = $this->notaryRequest;
        $workflow = app(NotaryRequestWorkflowService::class);
        $documents = $request->documents;

        $hasDocuments = $documents->isNotEmpty();
        $allSignersSigned = $workflow->documentsReadyForSession($request);
        $hasCompletedSession = $workflow->hasCompletedSession($request);
        $terminal = in_array($request->status->value, ['notarized', 'digitalized', 'cancelled', 'rejected', 'failed'], true);

        $nonTerminal = ! in_array($request->status->value, ['notarized', 'cancelled', 'rejected', 'failed'], true);

        return [
            'session' => $hasDocuments && ($allSignersSigned || $hasCompletedSession || $this->canScheduleSession() || $request->sessions->isNotEmpty()),
            'closing' => $nonTerminal,
            'audit' => $terminal || $request->journals->isNotEmpty() || ! $workflow->finalizationReadiness($request)['ready'],
            'identity' => $request->signers->isNotEmpty()
                && ($request->identityVerifications->isNotEmpty()
                    || in_array($request->status, [NotaryRequestStatus::Submitted, NotaryRequestStatus::IdentityReviewRequired], true)),
        ];
    }

    protected function resolveDefaultAttorneyTab(): string
    {
        $action = $this->primaryCaseAction();

        if (is_array($action) && isset($action['tab'])) {
            return (string) $action['tab'];
        }

        return 'documents';
    }

    private function ensureActiveTabIsAvailable(): void
    {
        $availableTabs = $this->availableTabs();
        if (in_array($this->activeTab, $availableTabs, true)) {
            return;
        }

        $preferredTab = 'documents';
        $user = Auth::user();
        if ($user?->role === \App\Enums\UserRole::Notary) {
            $preferredTab = $this->resolveDefaultAttorneyTab();
        }

        $this->activeTab = in_array($preferredTab, $availableTabs, true)
            ? $preferredTab
            : ($availableTabs[0] ?? 'documents');
    }

    public function with(): array
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);
        $this->ensureActiveTabIsAvailable();

        $workflow = app(NotaryRequestWorkflowService::class);
        $readiness = $workflow->finalizationReadiness($this->notaryRequest);
        $requestDocuments = $this->notaryRequest->documents->loadMissing(['documentSigners', 'signatureFields']);

        return [
            'isNotary' => $user->role->value === 'notary',
            'canManageLifecycle' => $user->canManageNotaryRequestPortal(),
            'isRequester' => $this->notaryRequest->user_id === $user->id,
            'isEnotaryPortalSigner' => $user->isEnotaryPortalSigner() && $user->isNotarySignerOn($this->notaryRequest),
            'canScheduleSession' => $this->canScheduleSession(),
            'canVerifyIdentity' => $this->canVerifyIdentity(),
            'canVerifyLocation' => $this->canVerifyLocation(),
            'canCreateRegisterEntry' => $this->canCreateRegisterEntry(),
            'hasAttorneySealOnFile' => $workflow->hasAttorneySealOnFile($this->notaryRequest),
            'canReviewNotary' => $this->canReviewNotary(),
            'canAttorneySign' => $this->canAttorneySign(),
            'attorneyHasSigned' => $workflow->hasAttorneySignedAllDocuments($this->notaryRequest),
            'canDigitalizeRequest' => $workflow->canDigitalize($this->notaryRequest),
            'paymentRequired' => $workflow->paymentRequired($this->notaryRequest),
            'hasSettledPayment' => $workflow->hasSettledPayment($this->notaryRequest),
            'workflowSteps' => $workflow->workflowSteps($this->notaryRequest),
            'requestDocuments' => $requestDocuments,
            'recentSessions' => $this->notaryRequest->sessions,
            'partiesForVideo' => (bool) config('docutrust.notary.require_video_session', true)
                ? app(\App\Services\NotarySignerVideoInvitationService::class)->partiesForVideoVerification($this->notaryRequest)
                : [],
            'journalEntries' => $this->notaryRequest->journals,
            'latestPayment' => Payment::query()
                ->where('notary_request_id', $this->notaryRequest->id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first(),
            'paymentHistory' => Payment::query()
                ->where('notary_request_id', $this->notaryRequest->id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get(),
            'latestEInvoice' => $this->notaryRequest->eInvoices->sortByDesc('created_at')->first(),
            'attorneyRegistryDraft' => $this->notaryRequest->attorneyNotarialRegistry,
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
            'signingProgress' => app(\App\Services\NotarySigningProgressService::class)->summarize(
                $this->notaryRequest,
                $user->role->value === 'notary' ? $user->id : null,
            ),
            'canUploadAnotherDocument' => $workflow->canAttachAnotherDocument($this->notaryRequest),
            'usesPerSignerVideo' => (bool) config('docutrust.notary.auto_invite_signers_to_video', true),
            'signerVideoSessions' => $this->notaryRequest->sessions
                ->loadMissing('notarySigner')
                ->filter(fn ($session) => $session->notary_signer_id !== null)
                ->values(),
            'requestSigners' => $this->notaryRequest->signers,
            'signerInvitations' => app(EnotaryInvitationService::class)->latestInvitationsForRequest($this->notaryRequest),
            'enotaryPortalEmails' => User::query()
                ->whereIn('email', $this->notaryRequest->signers->pluck('email')->map(fn (string $email): string => strtolower(trim($email)))->all())
                ->where(function ($query): void {
                    $query->where('workspace', UserWorkspace::Enotary->value)
                        ->orWhereNull('workspace');
                })
                ->pluck('email')
                ->map(fn (string $email): string => strtolower($email))
                ->flip(),
            'pendingIdentityReviews' => $this->notaryRequest->identityVerifications()
                ->with('signer')
                ->where('verification_status', NotaryIdentityVerificationStatus::Pending)
                ->latest()
                ->get(),
            'identityHistory' => $this->notaryRequest->identityVerifications()
                ->with('signer', 'verifier')
                ->latest()
                ->limit(25)
                ->get(),
            'geoHistory' => $this->notaryRequest->geoLogs()->with('signer')->latest()->limit(25)->get(),
        ];
    }

    public function submitRequest(): void
    {
        if ($this->notaryRequest->document_path !== null && $this->notaryRequest->signers()->doesntExist()) {
            $this->addError('submitRequest', __('Add at least one signer before submitting this eNOTARY request.'));

            return;
        }

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
            app(LocationVerificationService::class)->markVerified($this->notaryRequest->fresh(), [
                'source' => 'manual_review',
            ], null);
            $this->refreshRequest();
            session()->flash('status', __('Location verification recorded.'));
        } catch (\RuntimeException $exception) {
            $this->addError('markLocationVerified', $exception->getMessage());
        }
    }

    public function runBrowserGeoVerification(): void
    {
        $this->validate([
            'geoLatitude' => ['nullable', 'numeric'],
            'geoLongitude' => ['nullable', 'numeric'],
            'geoSignerId' => [
                'nullable',
                'integer',
                Rule::exists('notary_signers', 'id')->where('notary_request_id', $this->notaryRequest->id),
            ],
        ]);

        try {
            $result = app(LocationVerificationService::class)->evaluateBrowserLocation(
                $this->notaryRequest->fresh(),
                $this->geoSignerId,
                [
                    'latitude' => $this->geoLatitude !== '' ? (float) $this->geoLatitude : null,
                    'longitude' => $this->geoLongitude !== '' ? (float) $this->geoLongitude : null,
                ],
            );

            $this->refreshRequest();

            if ($result['success']) {
                session()->flash('status', __('Philippines location verification recorded from this browser session.'));
            } else {
                $this->addError('runBrowserGeoVerification', (string) ($result['message'] ?? __('Location verification failed.')));
            }
        } catch (\RuntimeException $exception) {
            $this->addError('runBrowserGeoVerification', $exception->getMessage());
        }
    }

    public function sendSignerIdentityOtp(int $signerId): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        $signer = NotarySigner::query()
            ->where('notary_request_id', $this->notaryRequest->id)
            ->whereKey($signerId)
            ->firstOrFail();

        $result = app(OtpService::class)->generateOtp(
            user: $user,
            email: $signer->email,
            mobileNumber: null,
            purpose: 'notary_identity',
            channel: 'email',
        );

        if (! $result['success']) {
            $this->addError('identityOtp', (string) ($result['message'] ?? __('Unable to send OTP.')));

            return;
        }

        session()->flash('status', __('We sent a verification code to :email.', ['email' => $signer->email]));
    }

    public function verifySignerIdentityOtp(int $signerId): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        $validated = $this->validate([
            'identityOtpCode' => ['required', 'string', 'min:4', 'max:12'],
        ]);

        $signer = NotarySigner::query()
            ->where('notary_request_id', $this->notaryRequest->id)
            ->whereKey($signerId)
            ->firstOrFail();

        $result = app(OtpService::class)->verifyOtp(
            inputOtp: trim($validated['identityOtpCode']),
            user: $user,
            email: $signer->email,
            mobileNumber: null,
        );

        if (! $result['success']) {
            $this->addError('identityOtpCode', (string) ($result['message'] ?? __('Invalid OTP.')));

            return;
        }

        session()->flash('status', __('Email OTP verified for :name.', ['name' => $signer->full_name]));
        $this->identityOtpCode = '';
    }

    public function saveSignerIdentityDocuments(int $signerId): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        $signer = NotarySigner::query()
            ->where('notary_request_id', $this->notaryRequest->id)
            ->whereKey($signerId)
            ->firstOrFail();

        $validated = $this->validate([
            'pendingIdType' => ['required', 'string', 'max:64'],
            'pendingIdNumber' => ['required', 'string', 'max:128'],
            'idImageFile' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'selfieImageFile' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:10240'],
        ]);

        $idPath = $this->idImageFile->store('notary/identity', (string) config('filesystems.docutrust_disk', 'local'));
        $selfiePath = null;
        if ($this->selfieImageFile !== null) {
            $selfiePath = $this->selfieImageFile->store('notary/identity', (string) config('filesystems.docutrust_disk', 'local'));
        }

        app(IdentityVerificationService::class)->submitPendingForSigner($signer, [
            'id_type' => trim($validated['pendingIdType']),
            'id_number' => trim($validated['pendingIdNumber']),
            'id_image_path' => $idPath,
            'selfie_image_path' => $selfiePath,
        ]);

        $this->idImageFile = null;
        $this->selfieImageFile = null;
        $this->pendingIdNumber = '';
        $this->resetValidation(['idImageFile', 'selfieImageFile', 'pendingIdType', 'pendingIdNumber']);
        $this->refreshRequest();
        session()->flash('status', __('Identity documents submitted for review.'));
    }

    public function approveIdentityRecord(int $verificationId): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        $record = NotaryIdentityVerification::query()
            ->where('notary_request_id', $this->notaryRequest->id)
            ->whereKey($verificationId)
            ->firstOrFail();

        $this->authorize('review', $record);

        try {
            app(IdentityVerificationService::class)->approvePendingRecord($user, $record);
            $this->refreshRequest();
            session()->flash('status', __('Identity verification approved.'));
        } catch (\RuntimeException $exception) {
            $this->addError('approveIdentity', $exception->getMessage());
        }
    }

    public function rejectIdentityRecord(): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        $validated = $this->validate([
            'identityRejectId' => ['required', 'integer', Rule::exists('notary_identity_verifications', 'id')->where('notary_request_id', $this->notaryRequest->id)],
            'identityRejectReason' => ['required', 'string', 'max:1000'],
        ]);

        $record = NotaryIdentityVerification::query()->whereKey($validated['identityRejectId'])->firstOrFail();

        $this->authorize('review', $record);

        try {
            app(IdentityVerificationService::class)->rejectPendingRecord($user, $record, trim($validated['identityRejectReason']));
            $this->identityRejectId = null;
            $this->identityRejectReason = '';
            $this->refreshRequest();
            session()->flash('status', __('Identity verification rejected.'));
        } catch (\RuntimeException $exception) {
            $this->addError('rejectIdentity', $exception->getMessage());
        }
    }

    public function cancelNotaryRequest(): void
    {
        $this->authorize('cancel', $this->notaryRequest);

        try {
            app(NotaryRequestWorkflowService::class)->cancel($this->notaryRequest->fresh(), __('Cancelled by user from the request workspace.'));
            $this->refreshRequest();
            session()->flash('status', __('This notary request was cancelled.'));
        } catch (\RuntimeException $exception) {
            $this->addError('cancelNotaryRequest', $exception->getMessage());
        }
    }

    public function scheduleSession(): void
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->role->value === 'notary', 403);

        $validated = $this->validate([
            'scheduleAt' => ['required', 'date'],
            'meetingUrl' => ['nullable', 'url', 'max:1000'],
            'providerName' => ['required', 'string', 'max:64'],
        ]);

        try {
            $user = Auth::user();
            abort_unless($user !== null, 401);

            $attorney = $user->role->value === 'notary' ? $user : null;

            app(NotarySchedulingService::class)->schedule(
                $this->notaryRequest,
                new DateTimeImmutable($validated['scheduleAt']),
                trim($validated['providerName']),
                trim((string) $validated['meetingUrl']) !== '' ? trim((string) $validated['meetingUrl']) : null,
                null,
                $attorney,
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

            $required = config('docutrust.notary.verification_checklist', []);
            foreach ($required as $key) {
                if (! ($this->sessionChecklist[$key] ?? false)) {
                    $this->addError('sessionChecklist', __('Complete every item on the attorney checklist before finishing the session.'));

                    return;
                }
            }

            app(NotarySchedulingService::class)->complete($session, $this->sessionChecklist);

            $this->refreshRequest();

            $workflow = app(NotaryRequestWorkflowService::class);
            $request = $this->notaryRequest->fresh();

            if (
                $workflow->hasCompletedSession($request)
                && $workflow->canBeginAttorneySigning($request)
                && ! $workflow->hasAttorneySignedAllDocuments($request)
            ) {
                session()->flash('status', __('All video verifications are complete. Sign the contract as attorney on the Documents tab, then the final PDF and certificate will be generated.'));
            } else {
                session()->flash('status', __('Video session completed with verification evidence.'));
            }
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
        $user = Auth::user();
        if ($user !== null && $user->role->value === 'notary') {
            $eligibility = app(\App\Services\AttorneyApplicationService::class)->practiceEligibility($user);
            if (! $eligibility['allowed']) {
                $this->addError('digitalizeRequest', $eligibility['message'] ?? __('Attorney practice is not enabled.'));

                return;
            }
        }

        try {
            app(NotaryRequestWorkflowService::class)->digitalize($this->notaryRequest);
            $this->refreshRequest();
            session()->flash('status', __('Digital notarization completed: notary seal applied, QR code attached, certificate generated, and document timestamped. The request is now ready for Notary Admin finalization.'));
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

    /**
     * Add a signer to the notary request (attorney only).
     */
    public function addSigner(): void
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->role->value === 'notary', 403);

        $validated = $this->validate([
            'newSignerName' => ['required', 'string', 'max:255'],
            'newSignerEmail' => ['required', 'email', 'max:255'],
            'newSignerPhone' => ['nullable', 'string', 'max:64'],
            'newSignerAddress' => ['nullable', 'string', 'max:500'],
            'newSignerRole' => ['required', 'string', 'max:64'],
        ]);

        $signer = NotarySigner::query()->create([
            'notary_request_id' => $this->notaryRequest->id,
            'full_name' => trim($validated['newSignerName']),
            'email' => strtolower(trim($validated['newSignerEmail'])),
            'phone' => trim((string) ($validated['newSignerPhone'] ?? '')) !== '' ? trim((string) $validated['newSignerPhone']) : null,
            'address' => trim((string) ($validated['newSignerAddress'] ?? '')) !== '' ? trim((string) $validated['newSignerAddress']) : null,
            'role' => trim($validated['newSignerRole']),
        ]);

        app(NotaryParticipantSyncService::class)->syncRequestSignersToDocuments($this->notaryRequest->fresh());

        try {
            $invitation = app(EnotaryInvitationService::class)->inviteSignerFromAttorney(
                $user,
                $this->notaryRequest,
                $signer,
            );

            $statusMessage = $invitation === null
                ? __('Signer added. They already have e-Notary portal access for this case.')
                : __('Signer added and portal invitation sent to :email.', ['email' => $signer->email]);
        } catch (\RuntimeException $exception) {
            $statusMessage = __('Signer added, but invitation could not be sent: :message', ['message' => $exception->getMessage()]);
        }

        $this->newSignerName = '';
        $this->newSignerEmail = '';
        $this->newSignerPhone = '';
        $this->newSignerAddress = '';
        $this->newSignerRole = 'signer';
        $this->resetValidation(['newSignerName', 'newSignerEmail', 'newSignerPhone', 'newSignerAddress', 'newSignerRole']);
        $this->refreshRequest();
        session()->flash('status', $statusMessage);
    }

    public function resendSignerPortalInvite(int $signerId): void
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->role->value === 'notary', 403);

        $signer = NotarySigner::query()
            ->where('notary_request_id', $this->notaryRequest->id)
            ->whereKey($signerId)
            ->firstOrFail();

        try {
            app(EnotaryInvitationService::class)->resendInvitation($user, $signer);
            $this->refreshRequest();
            session()->flash('status', __('Portal invitation resent to :email.', ['email' => $signer->email]));
        } catch (\RuntimeException $exception) {
            $this->addError('resendInvite', $exception->getMessage());
        }
    }

    /**
     * Remove a signer from the notary request (attorney only).
     */
    public function removeSigner(int $signerId): void
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->role->value === 'notary', 403);

        $signer = NotarySigner::query()
            ->where('notary_request_id', $this->notaryRequest->id)
            ->whereKey($signerId)
            ->firstOrFail();

        try {
            app(NotaryParticipantSyncService::class)->removeRequestSigner($signer);
            $this->refreshRequest();
            session()->flash('status', __('Signer removed.'));
        } catch (\RuntimeException $exception) {
            $this->addError('removeSigner', $exception->getMessage());
        }
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
            $workflow = app(NotaryRequestWorkflowService::class);
            $workflow->assertCanAttachDocument($this->notaryRequest);

            $path = $this->newDocumentFile->store('documents', (string) config('filesystems.docutrust_disk', 'local'));

            $document = $user->documents()->create([
                'title' => trim((string) $validated['newDocumentTitle']),
                'file_path' => $path,
                'status' => \App\Enums\DocumentStatus::Draft,
            ]);

            $workflow->attachDocument($this->notaryRequest, $document);

            $this->newDocumentTitle = '';
            $this->newDocumentFile = null;
            $this->resetValidation(['newDocumentTitle', 'newDocumentFile']);
            $this->refreshRequest();
            session()->flash('status', __('Document uploaded and linked to this notary request.'));
        } catch (\RuntimeException $exception) {
            $this->addError('newDocumentFile', $exception->getMessage());
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

    public function sendSignerVideoInvitations(bool $forceResend = true): void
    {
        $this->syncVideoPartiesIfReady(forceResend: $forceResend, notify: true, deliverSynchronously: true);
    }

    public function syncVideoPartiesIfReady(
        bool $forceResend = false,
        bool $notify = false,
        bool $deliverSynchronously = false,
    ): void {
        $user = Auth::user();
        abort_unless($user !== null && $user->role->value === 'notary', 403);
        abort_unless((int) $this->notaryRequest->notary_user_id === (int) $user->id, 403);

        if (! config('docutrust.notary.require_video_session', true)) {
            return;
        }

        try {
            $invited = app(\App\Services\NotarySignerVideoInvitationService::class)
                ->inviteAllSignersWhenReady(
                    $this->notaryRequest->fresh(['signers', 'sessions', 'notary', 'documents.documentSigners']),
                    $forceResend,
                    $deliverSynchronously,
                );

            $this->refreshRequest();

            if ($notify) {
                session()->flash('status', $invited > 0
                    ? __('Video invitations sent to :count party(ies). Each signer received their own personal link.', ['count' => $invited])
                    : __('Video sessions are ready. Personal links for each signed party are listed below.'));
            }
        } catch (\RuntimeException $exception) {
            if ($notify) {
                $this->addError('sendSignerVideoInvitations', $exception->getMessage());
            }
        }
    }

    public function sendLinkedDocument(int $documentId): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        // Only the assigned attorney can send eNOTARY documents to signers
        if ($user->role->value !== 'notary' || (int) $this->notaryRequest->notary_user_id !== (int) $user->id) {
            $this->addError('sendDocument'.$documentId, __('Only the assigned attorney can send eNOTARY documents for signing.'));
            return;
        }

        $document = Document::query()
            ->whereKey($documentId)
            ->where('notary_request_id', $this->notaryRequest->id)
            ->firstOrFail();

        try {
            app(\App\Services\SendDocumentForSignatureService::class)->send($document);

            $workflow = app(NotaryRequestWorkflowService::class);
            if ($this->notaryRequest->fresh()->status === NotaryRequestStatus::Draft) {
                try {
                    $workflow->submit($this->notaryRequest->fresh());
                } catch (\RuntimeException) {
                    // Submit may require additional case setup; signing can still proceed.
                }
            }

            $this->refreshRequest();
            session()->flash('status', __('Document sent to signers for signing.'));
        } catch (\RuntimeException $exception) {
            $this->addError('sendDocument'.$documentId, $exception->getMessage());
        }
    }

    /**
     * Prepare attorney signature fields on a completed document.
     * Adds the attorney as a signer and redirects to the prepare page
     * so they can place their signature fields on the already-signed document.
     */
    public function signAsAttorney(int $documentId): void
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->role->value === 'notary', 403);
        abort_unless((int) $this->notaryRequest->notary_user_id === (int) $user->id, 403);
        try {
            app(NotaryRequestWorkflowService::class)->beginAttorneySigning($this->notaryRequest);
        } catch (\RuntimeException $exception) {
            $this->addError('signAsAttorney', $exception->getMessage());

            return;
        }

        $document = Document::query()
            ->whereKey($documentId)
            ->where('notary_request_id', $this->notaryRequest->id)
            ->firstOrFail();

        // Ensure the attorney is added as a DocumentSigner
        $attorneySigner = $document->documentSigners()
            ->where('user_id', $user->id)
            ->first();

        if ($attorneySigner === null) {
            $attorneySigner = $document->documentSigners()->create([
                'name' => $user->name,
                'email' => $user->email,
                'user_id' => $user->id,
                'role_type' => \App\Enums\TemplateRoleType::Signer,
                'signing_method' => SigningMethod::AccountVerified,
                'status' => 'pending',
                'signing_order' => 999,
            ]);
        }

        // Transition document to pending so the prepare page allows field placement
        if ($document->status === \App\Enums\DocumentStatus::Completed) {
            $document->update(['status' => \App\Enums\DocumentStatus::Pending]);
        }

        // Redirect to prepare page so attorney can place their signature fields
        $this->redirect(route('notary.documents.prepare', $document), navigate: true);
    }

    /**
     * Check if the attorney can sign (video session completed).
     */
    public function canAttorneySign(): bool
    {
        $user = Auth::user();
        if ($user === null || $user->role->value !== 'notary') {
            return false;
        }

        if ((int) $this->notaryRequest->notary_user_id !== (int) $user->id) {
            return false;
        }

        return app(NotaryRequestWorkflowService::class)->canBeginAttorneySigning($this->notaryRequest);
    }

    /**
     * Resend signing invitation email to a specific signer on a linked document.
     */
    public function resendSignerEmail(int $documentId, int $signerId): void
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->role->value === 'notary', 403);

        $document = Document::query()
            ->whereKey($documentId)
            ->where('notary_request_id', $this->notaryRequest->id)
            ->firstOrFail();

        $signer = $document->documentSigners()->whereKey($signerId)->firstOrFail();

        if (! $signer->requiresAction()) {
            $this->addError('resendEmail', __('This signer has already completed their action.'));
            return;
        }

        if ($document->status !== DocumentStatus::Pending) {
            $this->addError('resendEmail', __('Invitations can only be resent while the document is awaiting signatures.'));

            return;
        }

        app(\App\Services\DocumentNotificationService::class)->sendSignerInvitation($document, $signer);

        session()->flash('status', __('Signing invitation resent to :name.', ['name' => $signer->name]));
    }

    public function sendSignerReminder(int $documentId, int $signerId): void
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->role->value === 'notary', 403);

        $document = Document::query()
            ->whereKey($documentId)
            ->where('notary_request_id', $this->notaryRequest->id)
            ->firstOrFail();

        abort_unless($document->status === DocumentStatus::Pending, 422);

        $signer = $document->documentSigners()->whereKey($signerId)->firstOrFail();

        if (! $signer->requiresAction() || $signer->status !== DocumentSignerStatus::Pending) {
            $this->addError('resendEmail', __('This signer has already completed their action.'));

            return;
        }

        app(\App\Services\DocumentNotificationService::class)->sendReminder($document, $signer);

        session()->flash('status', __('Reminder sent to :name.', ['name' => $signer->name]));
    }

    public function replaceDocument(int $documentId): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        // Only allow replacement while request is in Draft status
        if ($this->notaryRequest->status !== NotaryRequestStatus::Draft) {
            $this->addError('replaceDocumentFile', __('Documents can only be replaced while the request is in draft status.'));
            return;
        }

        $validated = $this->validate([
            'replaceDocumentFile' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'extensions:pdf'],
        ]);

        $document = Document::query()
            ->whereKey($documentId)
            ->where('notary_request_id', $this->notaryRequest->id)
            ->firstOrFail();

        try {
            $path = $this->replaceDocumentFile->store('documents', (string) config('filesystems.docutrust_disk', 'local'));

            $document->update([
                'file_path' => $path,
                'prepared_pdf_path' => null,
                'final_pdf_path' => null,
            ]);

            // Also update the notary request's document_path
            $this->notaryRequest->update(['document_path' => $path]);

            // Clear any existing signature fields since the document changed
            $document->signatureFields()->delete();

            $this->replaceDocumentFile = null;
            $this->replaceDocumentId = null;
            $this->resetValidation(['replaceDocumentFile']);
            $this->refreshRequest();
            session()->flash('status', __('Document replaced successfully.'));
        } catch (\Throwable $throwable) {
            Log::channel('errors')->error('Notary request document replacement failed', [
                'notary_request_id' => $this->notaryRequest->id,
                'document_id' => $documentId,
                'user_id' => $user->id,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);

            $this->addError('replaceDocumentFile', __('Unable to replace document right now. Please try again.'));
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

    public function createGatewayPayment(): void
    {
        $validated = $this->validate([
            'paymentGateway' => ['required', Rule::in(collect($this->enabledPaymentGateways)->pluck('code')->all())],
        ]);

        try {
            $payment = app(NotaryPaymentService::class)->createGatewayPayment(
                $this->notaryRequest->fresh(['registerEntries', 'payments', 'attorneyNotarialRegistry']),
                $validated['paymentGateway'],
                Auth::id(),
            );

            app(NotaryNotificationService::class)->notifyPaymentReady(
                $this->notaryRequest->fresh(['requester', 'notary']),
                $payment,
            );

            $this->refreshRequest();

            session()->flash('status', $payment->wasRecentlyCreated
                ? __('Payment created. Display the QR code or checkout link to the customer.')
                : __('An active pending payment already exists. Payment email was sent again to the customer.'));
        } catch (\RuntimeException $exception) {
            $this->addError('createGatewayPayment', $exception->getMessage());
        }
    }

    public function refreshPaymentStatus(int $paymentId): void
    {
        $payment = Payment::query()
            ->whereKey($paymentId)
            ->where('notary_request_id', $this->notaryRequest->id)
            ->firstOrFail();

        try {
            app(NotaryPaymentService::class)->refreshGatewayPayment($payment);
            $this->refreshRequest();
            session()->flash('status', __('Payment status verified from GatewayHub.'));
        } catch (\RuntimeException $exception) {
            $this->addError('refreshPaymentStatus', $exception->getMessage());
        }
    }

    public function queueLatestEInvoice(): void
    {
        $invoice = EInvoice::query()
            ->where('notary_request_id', $this->notaryRequest->id)
            ->latest('id')
            ->first();

        if (! $invoice instanceof EInvoice) {
            $this->addError('queueLatestEInvoice', __('No e-invoice record exists for this request yet.'));

            return;
        }

        try {
            app(EInvoiceService::class)->queueForBackgroundSubmission($invoice);
            $this->refreshRequest();
            session()->flash('status', __('E-invoice queued for background EIS submission.'));
        } catch (\RuntimeException $exception) {
            $this->refreshRequest();
            $this->addError('queueLatestEInvoice', $exception->getMessage());
        }
    }

    public function submitLatestEInvoice(): void
    {
        $invoice = EInvoice::query()
            ->where('notary_request_id', $this->notaryRequest->id)
            ->latest('id')
            ->first();

        if (! $invoice instanceof EInvoice) {
            $this->addError('submitLatestEInvoice', __('No e-invoice record exists for this request yet.'));

            return;
        }

        try {
            app(EInvoiceService::class)->submitQueuedInvoice($invoice);
            $this->refreshRequest();
            session()->flash('status', __('E-invoice submitted to EIS.'));
        } catch (\RuntimeException $exception) {
            $this->refreshRequest();
            $this->addError('submitLatestEInvoice', $exception->getMessage());
        }
    }

    public function refreshLatestEInvoiceStatus(): void
    {
        $invoice = EInvoice::query()
            ->where('notary_request_id', $this->notaryRequest->id)
            ->latest('id')
            ->first();

        if (! $invoice instanceof EInvoice) {
            $this->addError('refreshLatestEInvoiceStatus', __('No e-invoice record exists for this request yet.'));

            return;
        }

        try {
            app(EInvoiceService::class)->refreshSubmittedInvoice($invoice);
            $this->refreshRequest();
            session()->flash('status', __('E-invoice status refreshed from EIS.'));
        } catch (\RuntimeException $exception) {
            $this->refreshRequest();
            $this->addError('refreshLatestEInvoiceStatus', $exception->getMessage());
        }
    }

    private function refreshRequest(): void
    {
        $this->notaryRequest->refresh()->load(['requester', 'notary', 'documents.documentSigners', 'documents.signatureFields', 'documents.documentHash', 'sessions.notarySigner', 'journals.notary', 'registerEntries', 'payments', 'eInvoices', 'attorneyNotarialRegistry', 'signers', 'identityVerifications.signer', 'identityVerifications.verifier', 'geoLogs.signer']);
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
        // Field preparation is handled exclusively in Step 7 (Digital Notarization)
        // No "next action" recommendations needed for eNOTARY documents
        return null;
    }

    private function canVerifyIdentity(): bool
    {
        return app(NotaryRequestWorkflowService::class)->canVerifyIdentity($this->notaryRequest);
    }

    private function canVerifyLocation(): bool
    {
        return app(NotaryRequestWorkflowService::class)->canVerifyLocation($this->notaryRequest);
    }

    private function canScheduleSession(): bool
    {
        $user = Auth::user();
        if ($user === null || $user->role->value !== 'notary') {
            return false;
        }

        $workflowAllows = app(NotaryRequestWorkflowService::class)->canScheduleSession($this->notaryRequest);
        if (! $workflowAllows) {
            return false;
        }

        $progress = app(\App\Services\NotarySigningProgressService::class)->summarize($this->notaryRequest, (int) $user->id);

        return ! (bool) ($progress['video_verification_complete'] ?? false);
    }

    private function canReviewNotary(): bool
    {
        return app(NotaryRequestWorkflowService::class)->canApprove($this->notaryRequest);
    }

    private function canCreateRegisterEntry(): bool
    {
        return app(NotaryRequestWorkflowService::class)->canCreateRegisterEntry($this->notaryRequest);
    }

    private function loadPaymentGateways(): void
    {
        try {
            $this->enabledPaymentGateways = app(\App\Services\GatewayHubService::class)->enabledGateways();
            $this->paymentGateway = $this->enabledPaymentGateways[0]['code'] ?? '';
        } catch (\Throwable $exception) {
            $this->enabledPaymentGateways = [];
            $this->paymentGateway = '';
            report($exception);
        }
    }

    /**
     * @return array{
     *   type: 'link'|'wire'|'tab',
     *   label: string,
     *   description: string,
     *   variant: string,
     *   href?: string,
     *   action?: string,
     *   params?: list<int|string>,
     *   tab?: string,
     *   confirm?: string
     * }|null
     */
    #[Computed]
    public function primaryCaseAction(): ?array
    {
        $user = Auth::user();
        if ($user === null || $user->role->value !== 'notary') {
            return null;
        }

        if ((int) $this->notaryRequest->notary_user_id !== (int) $user->id) {
            return null;
        }

        $request = $this->notaryRequest;
        $workflow = app(NotaryRequestWorkflowService::class);
        $documents = $request->documents->loadMissing(['documentSigners', 'signatureFields']);

        if ($request->status === NotaryRequestStatus::Notarized) {
            $document = $documents->first();
            if ($document !== null) {
                return [
                    'type' => 'link',
                    'label' => __('Download notarized PDF'),
                    'description' => __('This case is complete.'),
                    'variant' => 'primary',
                    'href' => route('documents.download', $document),
                    'tab' => 'documents',
                ];
            }

            return null;
        }

        if (in_array($request->status->value, ['cancelled', 'rejected', 'failed'], true)) {
            return null;
        }

        if ($workflow->canDigitalize($request)) {
            return [
                'type' => 'wire',
                'label' => __('Apply digital notarization'),
                'description' => __('Seal the notarized instrument after register entry and payment.'),
                'variant' => 'primary',
                'action' => 'digitalizeRequest',
                'tab' => 'closing',
            ];
        }

        $hasAttorneyRegistryDraft = $request->attorneyNotarialRegistry !== null;
        $hasAttorneySealOnFile = $workflow->hasAttorneySealOnFile($request);
        $paymentRequired = $workflow->paymentRequired($request);
        $hasSettledPayment = $workflow->hasSettledPayment($request);

        if ($workflow->hasAttorneySignedAllDocuments($request) && ! $hasAttorneyRegistryDraft) {
            return [
                'type' => 'link',
                'label' => __('Prepare attorney registry'),
                'description' => __('Capture entry no., parties, identity evidence, fees, and notarial act details before payment.'),
                'variant' => 'primary',
                'href' => route('notary.attorney-registry', $request),
                'tab' => 'closing',
            ];
        }

        if ($paymentRequired && ! $hasSettledPayment && $hasAttorneyRegistryDraft) {
            return [
                'type' => 'tab',
                'label' => __('Collect payment'),
                'description' => __('Generate or share the payment link based on the attorney registry fee amount.'),
                'variant' => 'primary',
                'tab' => 'closing',
            ];
        }

        if (($hasSettledPayment || ! $paymentRequired) && ! $hasAttorneySealOnFile && $workflow->hasAttorneySignedAllDocuments($request)) {
            return [
                'type' => 'link',
                'label' => __('Upload attorney seal'),
                'description' => __('Add your personal seal in credentials before creating the final registry entry.'),
                'variant' => 'primary',
                'href' => route('notary.credentials'),
                'tab' => 'closing',
            ];
        }

        if ($workflow->canCreateRegisterEntry($request)) {
            return [
                'type' => 'link',
                'label' => __('Create register entry'),
                'description' => __('Finalize the register entry after payment and attorney seal completion.'),
                'variant' => 'primary',
                'href' => route('notary.register-entry', $request),
                'tab' => 'closing',
            ];
        }

        if ($this->canAttorneySign()) {
            $unsignedDocument = $documents->first(
                fn (Document $document): bool => ! $document->documentSigners->contains(
                    fn (DocumentSigner $signer): bool => (int) $signer->user_id === (int) $user->id
                        && $signer->status === DocumentSignerStatus::Signed
                )
            );

            if ($unsignedDocument !== null) {
                return [
                    'type' => 'wire',
                    'label' => __('Sign as attorney'),
                    'description' => __('Video verification is complete. Place your signature on the instrument.'),
                    'variant' => 'primary',
                    'action' => 'signAsAttorney',
                    'params' => [$unsignedDocument->id],
                    'tab' => 'documents',
                ];
            }
        }

        $joinableSession = $request->sessions
            ->filter(fn ($session): bool => $session->notary_signer_id !== null
                && in_array($session->status, ['scheduled', 'in_progress'], true))
            ->sortBy(fn ($session): int => $session->status === 'in_progress' ? 0 : 1)
            ->first();

        if ($joinableSession !== null) {
            $partyName = $joinableSession->notarySigner?->full_name;

            return [
                'type' => 'link',
                'label' => __('Join video call'),
                'description' => $partyName !== null && $partyName !== ''
                    ? __('Enter the live verification room with :name.', ['name' => $partyName])
                    : __('Enter the live verification room with the signer.'),
                'variant' => 'primary',
                'href' => route('notary.requests.session.live', [$request, $joinableSession]),
                'tab' => 'session',
            ];
        }

        if ($this->canScheduleSession()) {
            return [
                'type' => 'wire',
                'label' => __('Send video links to signers'),
                'description' => __('All signers have signed. Email each party their personal video link.'),
                'variant' => 'primary',
                'action' => 'openVideoSessionWorkspace',
                'tab' => 'session',
            ];
        }

        $draftDocument = $documents->first(fn (Document $document): bool => $document->status->value === 'draft');
        if ($draftDocument !== null) {
            if ($draftDocument->canSendForSigning()) {
                return [
                    'type' => 'wire',
                    'label' => __('Send to signers'),
                    'description' => __('Invite parties to sign the prepared document.'),
                    'variant' => 'primary',
                    'action' => 'sendLinkedDocument',
                    'params' => [$draftDocument->id],
                    'confirm' => __('Send this document to signers for signing?'),
                    'tab' => 'documents',
                ];
            }

            if ($draftDocument->canPrepareForSigning()) {
                return [
                    'type' => 'link',
                    'label' => __('Prepare signature fields'),
                    'description' => __('Place signer and attorney fields on the PDF.'),
                    'variant' => 'primary',
                    'href' => route('notary.documents.prepare', $draftDocument),
                    'tab' => 'documents',
                ];
            }
        }

        if ($documents->isEmpty()) {
            return [
                'type' => 'tab',
                'label' => __('Upload document'),
                'description' => __('Add the PDF instrument for this case.'),
                'variant' => 'primary',
                'tab' => 'documents',
            ];
        }

        $signingProgress = app(\App\Services\NotarySigningProgressService::class)->summarize(
            $request,
            (int) $user->id,
        );

        if ($signingProgress['phase'] === 'awaiting_video') {
            return [
                'type' => 'wire',
                'label' => __('Open video workspace'),
                'description' => $signingProgress['summary'],
                'variant' => 'primary',
                'action' => 'openVideoSessionWorkspace',
                'tab' => 'session',
            ];
        }

        if ($signingProgress['phase'] === 'finalizing') {
            return [
                'type' => 'tab',
                'label' => __('Finalize instrument'),
                'description' => $signingProgress['summary'],
                'variant' => 'primary',
                'tab' => 'documents',
            ];
        }

        $pendingDocument = $documents->first(fn (Document $document): bool => $document->status->value === 'pending');
        if ($pendingDocument !== null) {
            if ($signingProgress['all_client_signatures_complete'] && ! ($signingProgress['video_verification_complete'] ?? false)) {
                return [
                    'type' => 'wire',
                    'label' => __('Open video workspace'),
                    'description' => $signingProgress['summary'],
                    'variant' => 'primary',
                    'action' => 'openVideoSessionWorkspace',
                    'tab' => 'session',
                ];
            }

            return [
                'type' => 'tab',
                'label' => __('Signing progress'),
                'description' => $signingProgress['summary'],
                'variant' => 'outline',
                'tab' => 'documents',
            ];
        }

        if ($this->canReviewNotary()) {
            return [
                'type' => 'tab',
                'label' => __('Complete attorney review'),
                'description' => __('Finalize your review after payment and register entry.'),
                'variant' => 'primary',
                'tab' => 'closing',
            ];
        }

        return null;
    }

    /**
     * @return array{label: string, description: string}|null
     */
    #[Computed]
    public function currentWorkflowStep(): ?array
    {
        $step = collect(app(NotaryRequestWorkflowService::class)->workflowSteps($this->notaryRequest))
            ->first(fn (array $workflowStep): bool => ($workflowStep['state'] ?? '') === 'current');

        if (! is_array($step)) {
            return null;
        }

        return [
            'label' => (string) ($step['label'] ?? ''),
            'description' => (string) ($step['description'] ?? ''),
        ];
    }

}; ?>

@php
    $renderTaskFocusedLayout = Auth::user()?->role === \App\Enums\UserRole::Notary;
@endphp
@if ($renderTaskFocusedLayout)
    @include('livewire.notary-requests.show.task-focused')
@else
    @include('livewire.notary-requests.show.legacy-layout')
@endif
