<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\NotaryRequestStatus;
use App\Enums\PaymentStatus;
use App\Enums\TemplateRoleType;
use App\Events\NotaryRequestApproved;
use App\Events\NotaryRequestDigitalized;
use App\Events\NotaryRequestNotarized;
use App\Events\NotaryRequestSubmitted;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\NotaryCredential;
use App\Models\NotaryJournal;
use App\Models\NotaryRequest;
use App\Models\NotarySigner;
use App\Services\Notary\NotarySealProfileService;
use RuntimeException;

class NotaryRequestWorkflowService
{
    public function maxDocumentsPerRequest(): int
    {
        return max(1, (int) config('docutrust.notary.max_documents_per_request', 1));
    }

    public function canAttachAnotherDocument(NotaryRequest $request, ?Document $documentBeingAttached = null): bool
    {
        $request->loadCount('documents');

        if (
            $documentBeingAttached !== null
            && (int) $documentBeingAttached->notary_request_id === (int) $request->id
        ) {
            return true;
        }

        return $request->documents_count < $this->maxDocumentsPerRequest();
    }

    public function assertCanAttachDocument(NotaryRequest $request, ?Document $documentBeingAttached = null): void
    {
        if ($this->canAttachAnotherDocument($request, $documentBeingAttached)) {
            return;
        }

        throw new RuntimeException(__('This case allows only one document. Replace the existing PDF while it is still in draft, or continue with the current instrument.'));
    }

    public function documentForRequest(NotaryRequest $request): ?Document
    {
        return $request->documents()->orderBy('id')->first();
    }

    public function canVerifyIdentity(NotaryRequest $request): bool
    {
        return $request->identity_verified_at === null
            && in_array($request->status, [
                NotaryRequestStatus::Submitted,
                NotaryRequestStatus::IdentityReviewRequired,
                NotaryRequestStatus::LocationReviewRequired,
                NotaryRequestStatus::LocationVerified,
                NotaryRequestStatus::SessionScheduled,
                NotaryRequestStatus::SessionInProgress,
                NotaryRequestStatus::SessionCompleted,
                NotaryRequestStatus::AttorneySigning,
            ], true);
    }

    public function canVerifyLocation(NotaryRequest $request): bool
    {
        return $request->location_verified_at === null
            && in_array($request->status, [
                NotaryRequestStatus::Submitted,
                NotaryRequestStatus::IdentityReviewRequired,
                NotaryRequestStatus::IdentityVerified,
                NotaryRequestStatus::LocationReviewRequired,
            ], true);
    }

    public function canScheduleSession(NotaryRequest $request): bool
    {
        return in_array($request->status, [
            NotaryRequestStatus::Submitted,
            NotaryRequestStatus::IdentityReviewRequired,
            NotaryRequestStatus::IdentityVerified,
            NotaryRequestStatus::LocationReviewRequired,
            NotaryRequestStatus::LocationVerified,
        ], true)
            && $this->documentsReadyForSessionState($request);
    }

    public function documentsReadyForSession(NotaryRequest $request): bool
    {
        return $this->documentsReadyForSessionState($request);
    }

    /**
     * @return list<array{label: string, description: string, state: string}>
     */
    public function workflowSteps(NotaryRequest $request): array
    {
        $request->loadMissing(['documents.documentSigners', 'sessions', 'registerEntries', 'payments', 'eInvoices', 'attorneyNotarialRegistry', 'notary']);

        $hasSubmitted = $request->submitted_at !== null || $request->status !== NotaryRequestStatus::Draft;
        $hasDocuments = $request->documents->isNotEmpty();
        $allSignersSigned = $this->documentsReadyForSession($request);
        $hasCompletedSession = $this->hasCompletedSession($request);
        $attorneyHasSigned = $this->hasAttorneySignedAllDocuments($request);
        $hasRegisterEntry = $request->registerEntries->isNotEmpty();
        $hasFeeConfigured = $this->hasSettlementFeeConfigured($request);
        $hasPreparedDraft = $this->hasPreparedRegistryDraft($request);
        $canAccessRegistry = $this->canAccessAttorneyRegistry($request);
        $hasAttorneySeal = $this->hasAttorneySealOnFile($request);
        $paymentRequired = $this->paymentRequired($request);
        $hasSettledPayment = $this->hasSettledPayment($request);
        $isNotarized = $request->status === NotaryRequestStatus::Notarized;
        $isAttorneyApproved = $request->status === NotaryRequestStatus::AttorneyApproved;
        $isDigitalized = $request->status === NotaryRequestStatus::Digitalized;
        $canBeginAttorneySigning = $this->canBeginAttorneySigning($request);
        $canDigitalize = $this->canDigitalize($request);
        $canReview = $this->canApprove($request);
        $isReviewComplete = $request->status === NotaryRequestStatus::AttorneyApproved
            || in_array($request->status, [NotaryRequestStatus::Digitalized, NotaryRequestStatus::Notarized], true);
        $canCreateRegister = $this->canCreateRegisterEntry($request);

        $resolveState = function (bool $complete, bool $current, bool $blocked = false): string {
            if ($complete) {
                return 'complete';
            }

            if ($blocked) {
                return 'blocked';
            }

            return $current ? 'current' : 'upcoming';
        };

        $feeComplete = $hasFeeConfigured;
        $feeCurrent = $attorneyHasSigned && ! $hasFeeConfigured;
        $paymentComplete = ! $paymentRequired || $hasSettledPayment;
        $paymentCurrent = $paymentRequired && $hasFeeConfigured && ! $hasSettledPayment && $attorneyHasSigned;
        $registryDraftComplete = $hasPreparedDraft;
        $registryDraftCurrent = $canAccessRegistry && ! $hasPreparedDraft;
        $sealComplete = $hasAttorneySeal;
        $sealCurrent = $attorneyHasSigned && ! $hasAttorneySeal && ($hasSettledPayment || ! $paymentRequired) && $hasPreparedDraft;
        $registerComplete = $hasRegisterEntry;
        $registerCurrent = $canCreateRegister && ! $hasRegisterEntry;
        $reviewComplete = $isReviewComplete;
        $reviewCurrent = $canReview && ! $isReviewComplete;
        $digitalComplete = $isDigitalized || $isNotarized;
        $digitalCurrent = $canDigitalize && ! $isDigitalized && ! $isNotarized;

        return [
            [
                'label' => __('Prepare the document'),
                'description' => __('Upload the PDF, add signers, and send it out for signing.'),
                'state' => match (true) {
                    $allSignersSigned || $hasCompletedSession || $attorneyHasSigned || $isNotarized => 'complete',
                    $hasDocuments => 'current',
                    $request->status === NotaryRequestStatus::IdentityReviewRequired => 'current',
                    $request->status === NotaryRequestStatus::LocationReviewRequired => 'current',
                    default => $hasSubmitted ? 'current' : 'upcoming',
                },
            ],
            [
                'label' => __('Wait for signers'),
                'description' => __('Each signer completes their signature on the document.'),
                'state' => match (true) {
                    $allSignersSigned || $hasCompletedSession || $attorneyHasSigned || $isNotarized => 'complete',
                    $hasDocuments && $request->documents->contains(fn (Document $document) => $document->status->value === 'pending') => 'current',
                    default => 'upcoming',
                },
            ],
            [
                'label' => __('Verify client on video'),
                'description' => __('Meet the signer on a live video call and confirm their identity.'),
                'state' => match (true) {
                    $hasCompletedSession || $attorneyHasSigned || $isNotarized => 'complete',
                    in_array($request->status, [
                        NotaryRequestStatus::SessionScheduled,
                        NotaryRequestStatus::SessionInProgress,
                        NotaryRequestStatus::SessionCompleted,
                    ], true) => 'current',
                    $allSignersSigned => 'current',
                    default => 'upcoming',
                },
            ],
            [
                'label' => __('Sign as attorney'),
                'description' => __('After video verification, add your signature to the document.'),
                'state' => match (true) {
                    $attorneyHasSigned || $isNotarized => 'complete',
                    in_array($request->status, [
                        NotaryRequestStatus::SessionCompleted,
                        NotaryRequestStatus::AttorneySigning,
                    ], true) => 'current',
                    $canBeginAttorneySigning => 'current',
                    default => 'upcoming',
                },
            ],
            [
                'label' => __('Set the fee amount'),
                'description' => __('Enter how much the client should pay before you finish the register.'),
                'state' => $resolveState($feeComplete, $feeCurrent, ! $attorneyHasSigned),
            ],
            [
                'label' => __('Client pays the fee'),
                'description' => __('The client pays using the amount you set.'),
                'state' => $resolveState($paymentComplete, $paymentCurrent, ! $hasFeeConfigured || ! $paymentRequired),
            ],
            [
                'label' => __('Fill notarial book'),
                'description' => __('Complete the 9-field register row and O.R. number after payment.'),
                'state' => $resolveState($registryDraftComplete, $registryDraftCurrent, ! $canAccessRegistry),
            ],
            [
                'label' => __('Upload your notary seal'),
                'description' => __('Add your personal seal in your trust profile.'),
                'state' => $resolveState($sealComplete, $sealCurrent, ! $attorneyHasSigned),
            ],
            [
                'label' => __('Complete notarial book'),
                'description' => __('Create the final notarial book entry from your saved draft.'),
                'state' => $resolveState($registerComplete, $registerCurrent, ! $attorneyHasSigned),
            ],
            [
                'label' => __('Review and approve'),
                'description' => __('Confirm identity, consent, and jurisdiction before finishing.'),
                'state' => $resolveState($reviewComplete, $reviewCurrent, ! $hasRegisterEntry),
            ],
            [
                'label' => __('Apply seal and certificate'),
                'description' => __('Add your seal, QR code, certificate, and timestamp to finish.'),
                'state' => $resolveState($digitalComplete, $digitalCurrent, ! $hasRegisterEntry),
            ],
        ];
    }

    public function settlementPendingCount(NotaryRequest $request, bool $forAttorney): int
    {
        return collect($this->settlementSteps($request))
            ->filter(function (array $step) use ($forAttorney): bool {
                if (($step['state'] ?? '') !== 'current') {
                    return false;
                }

                $actor = $step['actor'] ?? '';

                return $forAttorney
                    ? $actor === 'attorney'
                    : $actor === 'client';
            })
            ->count();
    }

    public function currentSettlementSectionId(NotaryRequest $request): ?string
    {
        $step = collect($this->settlementSteps($request))
            ->first(fn (array $settlementStep): bool => ($settlementStep['state'] ?? '') === 'current');

        $sectionId = $step['section_id'] ?? null;

        return is_string($sectionId) && $sectionId !== '' ? $sectionId : null;
    }

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     state: 'complete'|'current'|'upcoming'|'blocked'
     * }>
     */
    public function clientPortalTimeline(NotaryRequest $request): array
    {
        $request->loadMissing(['sessions', 'payments', 'attorneyNotarialRegistry']);

        $isNotarized = $request->status === NotaryRequestStatus::Notarized;
        $hasCompletedSession = $this->hasCompletedSession($request);
        $paymentRequired = $this->paymentRequired($request);
        $hasSettledPayment = $this->hasSettledPayment($request);
        $hasFeeConfigured = $this->hasSettlementFeeConfigured($request);

        $steps = [];

        if ((bool) config('docutrust.notary.require_video_session', true)) {
            $sessionState = match (true) {
                $isNotarized, $hasCompletedSession => 'complete',
                in_array($request->status, [
                    NotaryRequestStatus::SessionScheduled,
                    NotaryRequestStatus::SessionInProgress,
                ], true) => 'current',
                default => 'upcoming',
            };

            $steps[] = [
                'key' => 'session',
                'label' => __('Video verification'),
                'description' => __('Join the scheduled notary session and complete identity verification.'),
                'state' => $sessionState,
            ];
        }

        if ($paymentRequired) {
            $paymentState = match (true) {
                $isNotarized, $hasSettledPayment => 'complete',
                $hasFeeConfigured => 'current',
                default => 'upcoming',
            };

            $steps[] = [
                'key' => 'payment',
                'label' => __('Pay notarial fee'),
                'description' => __('Complete checkout when your attorney sets the fee amount.'),
                'state' => $paymentState,
            ];
        }

        if ($isNotarized) {
            $steps[] = [
                'key' => 'complete',
                'label' => __('Download your documents'),
                'description' => __('Your notarized PDF and certificate are ready.'),
                'state' => 'complete',
            ];
        } else {
            $attorneyClosingState = match (true) {
                ($hasSettledPayment || ! $paymentRequired) && $hasCompletedSession => 'current',
                default => 'upcoming',
            };

            $steps[] = [
                'key' => 'attorney_closing',
                'label' => __('Attorney finalizes'),
                'description' => __('Your attorney completes the register entry and digital notarization.'),
                'state' => $attorneyClosingState,
            ];
        }

        return $steps;
    }

    public function hasCompletedSession(NotaryRequest $request): bool
    {
        $request->loadMissing(['sessions', 'signers', 'documents.documentSigners']);

        $signerScopedSessions = $request->sessions->filter(
            fn ($session): bool => $session->notary_signer_id !== null
        );

        if ($signerScopedSessions->isNotEmpty()) {
            $signedParties = app(NotarySignerVideoInvitationService::class)->signedPartiesForVideo($request);

            if ($signedParties === []) {
                return false;
            }

            return collect($signedParties)->every(function (NotarySigner $signer) use ($signerScopedSessions): bool {
                return $signerScopedSessions->contains(
                    fn ($session): bool => (int) $session->notary_signer_id === (int) $signer->id
                        && $session->status === 'completed'
                );
            });
        }

        return $request->sessions->contains(fn ($session): bool => $session->status === 'completed');
    }

    public function canBeginAttorneySigning(NotaryRequest $request): bool
    {
        if (! $this->hasCompletedSession($request)) {
            return false;
        }

        return $this->documentsReadyForSessionState($request);
    }

    public function hasAttorneySignedAllDocuments(NotaryRequest $request): bool
    {
        $request->loadMissing('documents.documentSigners');

        if ($request->documents->isEmpty() || $request->notary_user_id === null) {
            return false;
        }

        return $request->documents->every(function (Document $document) use ($request): bool {
            return $document->documentSigners->contains(
                fn (DocumentSigner $signer): bool => (int) $signer->user_id === (int) $request->notary_user_id
                    && $signer->role_type === TemplateRoleType::Signer
                    && $signer->status->isCompleted()
            );
        });
    }

    public function documentHasCoreArtifacts(Document $document): bool
    {
        $document->loadMissing('documentHash');

        $hasFinalPdf = is_string($document->final_pdf_path) && $document->final_pdf_path !== '';
        $hasCertificate = is_string($document->certificate_path) && $document->certificate_path !== '';
        $hasDocumentHash = $document->documentHash !== null
            && is_string($document->documentHash->hash)
            && $document->documentHash->hash !== '';

        return $hasFinalPdf && $hasCertificate && $hasDocumentHash;
    }

    public function requestHasCoreArtifacts(NotaryRequest $request): bool
    {
        $request->loadMissing('documents');

        if ($request->documents->isEmpty()) {
            return false;
        }

        return $request->documents->every(
            fn (Document $document): bool => $this->documentHasCoreArtifacts($document)
        );
    }

    public function canCreateRegisterEntry(NotaryRequest $request): bool
    {
        if (in_array($request->status, [
            NotaryRequestStatus::Digitalized,
            NotaryRequestStatus::Notarized,
        ], true)) {
            return false;
        }

        if (! $this->hasAttorneySignedAllDocuments($request)) {
            return false;
        }

        if (! $this->hasAttorneySealOnFile($request)) {
            return false;
        }

        if ($this->paymentRequired($request) && ! $this->hasSettledPayment($request)) {
            return false;
        }

        $request->loadMissing('registerEntries');

        if ($request->registerEntries->isNotEmpty()) {
            return false;
        }

        if (! $this->hasPreparedRegistryDraft($request)) {
            return false;
        }

        return true;
    }

    public function settlementClosingPrerequisitesMet(NotaryRequest $request): bool
    {
        if (! $this->hasAttorneySignedAllDocuments($request)) {
            return false;
        }

        if (! $this->hasAttorneySealOnFile($request)) {
            return false;
        }

        if ($this->paymentRequired($request) && ! $this->hasSettledPayment($request)) {
            return false;
        }

        $request->loadMissing(['registerEntries', 'attorneyNotarialRegistry']);

        return $request->registerEntries->isNotEmpty()
            || $this->hasPreparedRegistryDraft($request);
    }

    public function hasSettlementFeeConfigured(NotaryRequest $request): bool
    {
        $request->loadMissing('attorneyNotarialRegistry');

        return $request->attorneyNotarialRegistry !== null
            && (float) $request->attorneyNotarialRegistry->fees > 0;
    }

    public function hasPreparedRegistryDraft(NotaryRequest $request): bool
    {
        $request->loadMissing('attorneyNotarialRegistry');

        return $request->attorneyNotarialRegistry?->registry_fields_completed_at !== null;
    }

    public function canAccessAttorneyRegistry(NotaryRequest $request): bool
    {
        if (! $this->hasAttorneySignedAllDocuments($request)) {
            return false;
        }

        if ($this->paymentRequired($request) && ! $this->hasSettledPayment($request)) {
            return false;
        }

        return true;
    }

    public function settlementDueAmount(NotaryRequest $request): float
    {
        $request->loadMissing(['registerEntries', 'attorneyNotarialRegistry']);

        $latestRegisterEntry = $request->registerEntries->sortByDesc('created_at')->first();
        if ($latestRegisterEntry !== null && (float) $latestRegisterEntry->fees > 0) {
            return (float) $latestRegisterEntry->fees;
        }

        return (float) ($request->attorneyNotarialRegistry?->fees ?? 0);
    }

    /**
     * @return list<array{
     *   key: string,
     *   label: string,
     *   description: string,
     *   state: 'complete'|'current'|'upcoming'|'blocked',
     *   actor: 'attorney'|'client'|'system',
     *   section_id: ?string,
     *   href: ?string,
     *   waiting_on: ?('attorney'|'client')
     * }>
     */
    public function settlementSteps(NotaryRequest $request): array
    {
        $request->loadMissing(['attorneyNotarialRegistry', 'registerEntries', 'payments', 'notary']);

        $attorneyHasSigned = $this->hasAttorneySignedAllDocuments($request);
        $hasFeeConfigured = $this->hasSettlementFeeConfigured($request);
        $hasPreparedDraft = $this->hasPreparedRegistryDraft($request);
        $canAccessRegistry = $this->canAccessAttorneyRegistry($request);
        $hasSeal = $this->hasAttorneySealOnFile($request);
        $paymentRequired = $this->paymentRequired($request);
        $hasSettledPayment = $this->hasSettledPayment($request);
        $hasRegisterEntry = $request->registerEntries->isNotEmpty();
        $canReview = $this->canApprove($request);
        $isReviewComplete = $request->status === NotaryRequestStatus::AttorneyApproved
            || in_array($request->status, [NotaryRequestStatus::Digitalized, NotaryRequestStatus::Notarized], true);
        $canDigitalize = $this->canDigitalize($request);
        $isDigitalized = in_array($request->status, [NotaryRequestStatus::Digitalized, NotaryRequestStatus::Notarized], true);

        $resolveState = function (bool $complete, bool $current, bool $blocked = false): string {
            if ($complete) {
                return 'complete';
            }

            if ($blocked) {
                return 'blocked';
            }

            return $current ? 'current' : 'upcoming';
        };

        $feeComplete = $hasFeeConfigured;
        $feeCurrent = $attorneyHasSigned && ! $hasFeeConfigured;
        $paymentComplete = ! $paymentRequired || $hasSettledPayment;
        $paymentCurrent = $paymentRequired && $hasFeeConfigured && ! $hasSettledPayment && $attorneyHasSigned;
        $registryDraftComplete = $hasPreparedDraft;
        $registryDraftCurrent = $canAccessRegistry && ! $hasPreparedDraft;
        $sealComplete = $hasSeal;
        $sealCurrent = $attorneyHasSigned && ! $hasSeal && ($hasSettledPayment || ! $paymentRequired) && $hasPreparedDraft;
        $registerComplete = $hasRegisterEntry;
        $registerCurrent = $this->canCreateRegisterEntry($request) && ! $hasRegisterEntry;
        $reviewComplete = $isReviewComplete;
        $reviewCurrent = $canReview && ! $isReviewComplete;
        $digitalComplete = $isDigitalized;
        $digitalCurrent = $canDigitalize && ! $isDigitalized;

        $feeState = $resolveState($feeComplete, $feeCurrent, ! $attorneyHasSigned);
        $paymentState = $resolveState($paymentComplete, $paymentCurrent, ! $hasFeeConfigured || ! $paymentRequired);
        $registryDraftState = $resolveState($registryDraftComplete, $registryDraftCurrent, ! $canAccessRegistry);
        $sealState = $resolveState($sealComplete, $sealCurrent, ! $attorneyHasSigned);
        $registerState = $resolveState($registerComplete, $registerCurrent, ! $attorneyHasSigned);
        $reviewState = $resolveState($reviewComplete, $reviewCurrent, ! $hasRegisterEntry);
        $digitalState = $resolveState($digitalComplete, $digitalCurrent, ! $hasRegisterEntry);

        return [
            [
                'key' => 'settlement_fee',
                'label' => __('Set the fee amount'),
                'description' => __('Enter how much the client should pay before you finish the register.'),
                'state' => $feeState,
                'actor' => 'attorney',
                'section_id' => 'section-settlement-fee',
                'href' => null,
                'waiting_on' => $this->settlementStepWaitingOn($feeState, ! $attorneyHasSigned ? 'attorney' : null),
            ],
            [
                'key' => 'payment',
                'label' => __('Client pays the fee'),
                'description' => __('The client pays using the amount you set.'),
                'state' => $paymentState,
                'actor' => 'client',
                'section_id' => 'section-payment',
                'href' => null,
                'waiting_on' => $this->settlementStepWaitingOn(
                    $paymentState,
                    ! $paymentRequired ? null : (! $hasFeeConfigured || ! $attorneyHasSigned ? 'attorney' : null),
                ),
            ],
            [
                'key' => 'registry_draft',
                'label' => __('Fill notarial book'),
                'description' => __('Complete the 9-field register row and O.R. number after payment.'),
                'state' => $registryDraftState,
                'actor' => 'attorney',
                'section_id' => 'section-attorney-registry',
                'href' => $canAccessRegistry ? route('notary.attorney-registry', $request) : null,
                'waiting_on' => $this->settlementStepWaitingOn(
                    $registryDraftState,
                    ! $attorneyHasSigned ? 'attorney' : ($paymentRequired && ! $hasSettledPayment ? 'client' : null),
                ),
            ],
            [
                'key' => 'seal',
                'label' => __('Upload your notary seal'),
                'description' => __('Add your personal seal in your trust profile.'),
                'state' => $sealState,
                'actor' => 'attorney',
                'section_id' => 'section-attorney-seal',
                'href' => app(NotarySealProfileService::class)->trustProfileSealSectionUrl(),
                'waiting_on' => $this->settlementStepWaitingOn(
                    $sealState,
                    ! $attorneyHasSigned ? 'attorney' : (! $hasPreparedDraft ? 'attorney' : ($paymentRequired && ! $hasSettledPayment ? 'client' : null)),
                ),
            ],
            [
                'key' => 'register_entry',
                'label' => __('Complete notarial book'),
                'description' => __('Create the final notarial book entry from your saved draft.'),
                'state' => $registerState,
                'actor' => 'attorney',
                'section_id' => 'section-register',
                'href' => route('notary.register-entry', $request),
                'waiting_on' => $this->settlementStepWaitingOn($registerState, ! $attorneyHasSigned ? 'attorney' : null),
            ],
            [
                'key' => 'attorney_review',
                'label' => __('Review and approve'),
                'description' => __('Confirm identity, consent, and jurisdiction before finishing.'),
                'state' => $reviewState,
                'actor' => 'attorney',
                'section_id' => 'section-review',
                'href' => null,
                'waiting_on' => $this->settlementStepWaitingOn($reviewState, ! $hasRegisterEntry ? 'attorney' : null),
            ],
            [
                'key' => 'digital_notarization',
                'label' => __('Apply seal and certificate'),
                'description' => __('Add your seal, QR code, certificate, and timestamp to finish.'),
                'state' => $digitalState,
                'actor' => 'attorney',
                'section_id' => 'section-digital-notarization',
                'href' => null,
                'waiting_on' => $this->settlementStepWaitingOn($digitalState, ! $hasRegisterEntry ? 'attorney' : null),
            ],
        ];
    }

    /**
     * @param  'complete'|'current'|'upcoming'|'blocked'  $state
     * @param  'attorney'|'client'|null  $blockedBy
     */
    private function settlementStepWaitingOn(string $state, ?string $blockedBy): ?string
    {
        if (in_array($state, ['complete', 'current'], true)) {
            return null;
        }

        return $blockedBy;
    }

    public function canApprove(NotaryRequest $request): bool
    {
        if (! $this->hasCompletedSession($request)) {
            return false;
        }

        if (! $this->hasAttorneySignedAllDocuments($request)) {
            return false;
        }

        $request->loadMissing('registerEntries');

        if ($request->registerEntries->isEmpty()) {
            return false;
        }

        if ($this->paymentRequired($request) && ! $this->hasSettledPayment($request)) {
            return false;
        }

        return true;
    }

    public function canDigitalize(NotaryRequest $request): bool
    {
        if (in_array($request->status, [NotaryRequestStatus::Digitalized, NotaryRequestStatus::Notarized], true)) {
            return false;
        }

        if (! $this->hasAttorneySignedAllDocuments($request)) {
            return false;
        }

        if (! $this->settlementClosingPrerequisitesMet($request)) {
            return false;
        }

        $request->loadMissing('documents');

        return $request->documents->isNotEmpty()
            && $request->documents->every(fn (Document $document): bool => $document->status === DocumentStatus::Completed);
    }

    /**
     * @return list<array{key: string, label: string, complete: bool}>
     */
    public function digitalizePrerequisites(NotaryRequest $request): array
    {
        $request->loadMissing(['documents', 'registerEntries', 'attorneyNotarialRegistry', 'payments']);
        $paymentRequired = $this->paymentRequired($request);

        $prerequisites = [
            [
                'key' => 'attorney_signed',
                'label' => __('Attorney signed the instrument'),
                'complete' => $this->hasAttorneySignedAllDocuments($request),
            ],
            [
                'key' => 'fee',
                'label' => __('Notarial fee configured'),
                'complete' => $this->hasSettlementFeeConfigured($request),
            ],
        ];

        if ($paymentRequired) {
            $prerequisites[] = [
                'key' => 'payment',
                'label' => __('Client payment received'),
                'complete' => $this->hasSettledPayment($request),
            ];
        }

        $prerequisites[] = [
            'key' => 'registry_draft',
            'label' => __('Notarial register draft complete'),
            'complete' => $this->hasPreparedRegistryDraft($request),
        ];
        $prerequisites[] = [
            'key' => 'seal',
            'label' => __('Attorney seal uploaded'),
            'complete' => $this->hasAttorneySealOnFile($request),
        ];
        $prerequisites[] = [
            'key' => 'register_entry',
            'label' => __('Official register entry created'),
            'complete' => $request->registerEntries->isNotEmpty(),
        ];
        $prerequisites[] = [
            'key' => 'final_document',
            'label' => __('Final document artifacts ready'),
            'complete' => $request->documents->isNotEmpty()
                && $request->documents->every(
                    fn (Document $document): bool => $document->status === DocumentStatus::Completed
                ),
        ];

        return $prerequisites;
    }

    public function paymentRequired(NotaryRequest $request): bool
    {
        $request->loadMissing(['registerEntries', 'attorneyNotarialRegistry']);

        if ($request->registerEntries->contains(
            fn ($entry): bool => (float) $entry->fees > 0
        )) {
            return true;
        }

        return (float) ($request->attorneyNotarialRegistry?->fees ?? 0) > 0;
    }

    public function hasSettledPayment(NotaryRequest $request): bool
    {
        if (! $this->paymentRequired($request)) {
            return true;
        }

        $request->loadMissing('payments');

        return $request->payments->contains(
            fn ($payment): bool => $payment->status === PaymentStatus::Paid
        );
    }

    public function beginAttorneySigning(NotaryRequest $request): NotaryRequest
    {
        if (! $this->canBeginAttorneySigning($request)) {
            throw new RuntimeException(__('Attorney signing can begin only after signer completion and the completed verification session.'));
        }

        if ($request->status !== NotaryRequestStatus::AttorneySigning) {
            $request->markAttorneySigning();
        }

        return $request->fresh();
    }

    public function hasAttorneySealOnFile(NotaryRequest $request): bool
    {
        $request->loadMissing('notary');

        if ($request->notary === null) {
            return false;
        }

        $credential = NotaryCredential::query()
            ->where('user_id', $request->notary->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        return $credential !== null
            && is_string($credential->seal_image_path)
            && $credential->seal_image_path !== '';
    }

    private function documentsReadyForSessionState(NotaryRequest $request): bool
    {
        $request->loadMissing(['documents.documentSigners']);

        if ($request->documents->isEmpty()) {
            return false;
        }

        if ($request->documents->count() > $this->maxDocumentsPerRequest()) {
            return false;
        }

        return $request->documents->every(function (Document $document) use ($request): bool {
            if (! in_array($document->status, [DocumentStatus::Pending, DocumentStatus::Completed], true)) {
                return false;
            }

            return $document->documentSigners
                ->filter(function (DocumentSigner $signer) use ($request): bool {
                    if (! $signer->requiresAction()) {
                        return false;
                    }

                    return (int) $signer->user_id !== (int) $request->notary_user_id;
                })
                ->every(fn (DocumentSigner $signer): bool => $signer->status->isCompleted());
        });
    }

    /**
     * @return array{
     *   ready: bool,
     *   issues: list<string>,
     *   documents: array<int, array{
     *     document_id: int,
     *     title: string,
     *     completed: bool,
     *     has_final_pdf: bool,
     *     has_certificate: bool,
     *     has_document_hash: bool,
     *     has_blockchain_transaction: bool,
     *     issues: list<string>
     *   }>
     * }
     */
    public function finalizationReadiness(NotaryRequest $request): array
    {
        $request->loadMissing(['documents.documentHash', 'registerEntries', 'payments', 'eInvoices']);

        $issues = [];
        $documents = [];

        if ($request->documents->isEmpty()) {
            $issues[] = __('Attach at least one document before finalizing notarization.');
        }

        if ($request->registerEntries->isEmpty()) {
            $issues[] = __('Create at least one notarial register entry before finalizing.');
        }

        if ($this->paymentRequired($request) && ! $this->hasSettledPayment($request)) {
            $issues[] = __('Client payment must be completed before finalizing notarization.');
        }

        foreach ($request->documents as $document) {
            $documentIssues = [];
            $completed = $document->status === DocumentStatus::Completed;
            $hasFinalPdf = is_string($document->final_pdf_path) && $document->final_pdf_path !== '';
            $hasCertificate = is_string($document->certificate_path) && $document->certificate_path !== '';
            $hasDocumentHash = $document->documentHash !== null && is_string($document->documentHash->hash) && $document->documentHash->hash !== '';
            $hasBlockchainTransaction = $document->documentHash !== null
                && is_string($document->documentHash->transaction_id)
                && $document->documentHash->transaction_id !== '';

            if (! $completed) {
                $documentIssues[] = __('Document is not completed.');
            }

            if (! $hasFinalPdf) {
                $documentIssues[] = __('Final signed PDF has not been generated.');
            }

            if (! $hasCertificate) {
                $documentIssues[] = __('Completion certificate has not been generated.');
            }

            if (! $hasDocumentHash) {
                $documentIssues[] = __('Document hash has not been recorded.');
            }

            // Blockchain anchoring is optional — service may be unavailable
            // NotaryAdmin can retry blockchain anchoring later
            if (! $hasBlockchainTransaction) {
                // Not a blocking issue — just a warning
            }

            if ($documentIssues !== []) {
                $issues[] = __('Document ":title" is not ready for notarization finalization.', [
                    'title' => $document->title,
                ]);
            }

            $documents[] = [
                'document_id' => (int) $document->id,
                'title' => (string) $document->title,
                'completed' => $completed,
                'has_final_pdf' => $hasFinalPdf,
                'has_certificate' => $hasCertificate,
                'has_document_hash' => $hasDocumentHash,
                'has_blockchain_transaction' => $hasBlockchainTransaction,
                'issues' => $documentIssues,
            ];
        }

        return [
            'ready' => $issues === [],
            'issues' => $issues,
            'documents' => $documents,
        ];
    }

    public function submit(NotaryRequest $request): NotaryRequest
    {
        if ($request->status !== NotaryRequestStatus::Draft) {
            throw new RuntimeException(__('Only draft notarizations can be submitted.'));
        }

        $request->markSubmitted();

        event(new NotaryRequestSubmitted($request));

        return $request->fresh();
    }

    public function approve(NotaryRequest $request, array $legalAssertions = [], ?string $summary = null): NotaryRequest
    {
        if (! $this->canApprove($request)) {
            throw new RuntimeException(__('This notarization is not ready for attorney review completion. Client payment must be completed after the register entry is created.'));
        }

        $request->markApproved();

        NotaryJournal::query()->create([
            'notary_request_id' => $request->id,
            'notary_user_id' => $request->notary_user_id,
            'entry_type' => 'approval',
            'summary' => $summary ?: __('Attorney completed the final review for this notarization.'),
            'legal_assertions' => $legalAssertions,
            'recorded_at' => now(),
        ]);

        event(new NotaryRequestApproved($request));

        return $request->fresh();
    }

    public function reject(NotaryRequest $request, string $reason, array $legalAssertions = []): NotaryRequest
    {
        if ($reason === '') {
            throw new RuntimeException(__('A rejection reason is required.'));
        }

        if (in_array($request->status, [
            NotaryRequestStatus::Notarized,
            NotaryRequestStatus::Rejected,
            NotaryRequestStatus::Failed,
        ], true)) {
            throw new RuntimeException(__('This notarization cannot be rejected in its current state.'));
        }

        $request->markRejected($reason);

        NotaryJournal::query()->create([
            'notary_request_id' => $request->id,
            'notary_user_id' => $request->notary_user_id,
            'entry_type' => 'rejection',
            'summary' => $reason,
            'legal_assertions' => $legalAssertions,
            'recorded_at' => now(),
        ]);

        return $request->fresh();
    }

    public function finalize(NotaryRequest $request): NotaryRequest
    {
        if ($request->status !== NotaryRequestStatus::Digitalized) {
            throw new RuntimeException(__('Digital notarization is required before notarization can be finalized.'));
        }

        $readiness = $this->finalizationReadiness($request);
        if (! $readiness['ready']) {
            throw new RuntimeException($readiness['issues'][0] ?? __('This notarization is not ready for finalization.'));
        }

        $request->markNotarized();

        event(new NotaryRequestNotarized($request));

        return $request->fresh();
    }

    public function digitalize(NotaryRequest $request): NotaryRequest
    {
        if (! $this->canDigitalize($request)) {
            throw new RuntimeException($this->digitalizeBlockingMessage($request));
        }

        if ($request->status !== NotaryRequestStatus::AttorneyApproved) {
            $request = $this->approve($request->fresh(), [
                'identity_matched' => true,
                'voluntary_consent' => true,
                'jurisdiction_valid' => true,
                'digital_notarization_ready' => true,
            ], __('Attorney completed signing and review, and marked this notarization ready for digital notarization.'));
        }

        app(NotaryDigitalizationService::class)->digitalize($request->fresh());

        $request->fresh()->markDigitalized();

        event(new NotaryRequestDigitalized($request->fresh()));

        return $request->fresh();
    }

    public function attachDocument(NotaryRequest $request, Document $document): NotaryRequest
    {
        $this->assertCanAttachDocument($request, $document);

        if ($request->organization_id === null || $document->organization_id === null || $request->organization_id !== $document->organization_id) {
            throw new RuntimeException(__('The selected document does not belong to this organization.'));
        }

        if ($document->notary_request_id !== null && $document->notary_request_id !== $request->id) {
            throw new RuntimeException(__('The selected document is already linked to another notarization.'));
        }

        if ($request->status === NotaryRequestStatus::Notarized) {
            throw new RuntimeException(__('You cannot attach documents to a finalized notarization.'));
        }

        $document->update([
            'notary_request_id' => $request->id,
        ]);

        app(NotaryParticipantSyncService::class)->syncRequestSignersToDocument($document);

        NotaryJournal::query()->create([
            'notary_request_id' => $request->id,
            'notary_user_id' => $request->notary_user_id,
            'entry_type' => 'document_attached',
            'summary' => __('Linked document ":title" to this notarization.', ['title' => $document->title]),
            'legal_assertions' => [
                'document_id' => $document->id,
            ],
            'recorded_at' => now(),
        ]);

        return $request->fresh(['documents', 'journals']);
    }

    public function detachDocument(NotaryRequest $request, Document $document): NotaryRequest
    {
        if ($document->notary_request_id !== $request->id) {
            throw new RuntimeException(__('The selected document is not linked to this notarization.'));
        }

        if ($request->status === NotaryRequestStatus::Notarized) {
            throw new RuntimeException(__('You cannot detach documents from a finalized notarization.'));
        }

        $document->update([
            'notary_request_id' => null,
        ]);

        NotaryJournal::query()->create([
            'notary_request_id' => $request->id,
            'notary_user_id' => $request->notary_user_id,
            'entry_type' => 'document_detached',
            'summary' => __('Removed document ":title" from this notarization.', ['title' => $document->title]),
            'legal_assertions' => [
                'document_id' => $document->id,
            ],
            'recorded_at' => now(),
        ]);

        return $request->fresh(['documents', 'journals']);
    }

    public function cancel(NotaryRequest $request, string $reason = ''): NotaryRequest
    {
        if (in_array($request->status, [
            NotaryRequestStatus::Notarized,
            NotaryRequestStatus::Digitalized,
            NotaryRequestStatus::Rejected,
            NotaryRequestStatus::Failed,
            NotaryRequestStatus::Cancelled,
        ], true)) {
            throw new RuntimeException(__('This notarization cannot be cancelled in its current state.'));
        }

        $request->markCancelled();

        NotaryJournal::query()->create([
            'notary_request_id' => $request->id,
            'notary_user_id' => $request->notary_user_id,
            'entry_type' => 'request_cancelled',
            'summary' => $reason !== '' ? $reason : __('Notarization was cancelled.'),
            'legal_assertions' => [],
            'recorded_at' => now(),
        ]);

        return $request->fresh();
    }

    public function digitalizeBlockingMessage(NotaryRequest $request): string
    {
        if (! $this->hasAttorneySignedAllDocuments($request)) {
            return __('This notarization is not ready for digital notarization. The attorney must finish signing first.');
        }

        if (! $this->settlementClosingPrerequisitesMet($request)) {
            if ($this->paymentRequired($request) && ! $this->hasSettledPayment($request)) {
                return __('This notarization is not ready for digital notarization. Client payment must be completed first.');
            }

            return __('This notarization is not ready for digital notarization. Complete settlement, register entry, and seal first.');
        }

        $request->loadMissing('documents');

        if ($request->documents->isEmpty()
            || ! $request->documents->every(fn (Document $document): bool => $document->status === DocumentStatus::Completed)) {
            return __('This notarization is not ready for digital notarization. Final document artifacts are still being prepared.');
        }

        return __('This notarization is not ready for digital notarization yet.');
    }
}
