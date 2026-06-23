<?php

namespace App\Services;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Enums\SigningMethod;
use App\Enums\TemplateRoleType;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\NotaryRequest;

class NotarySigningProgressService
{
    public function __construct(
        private readonly DocumentSigningWorkflowService $documentSigningWorkflowService,
        private readonly NotaryRequestWorkflowService $notaryRequestWorkflowService,
    ) {}

    /**
     * @return array{
     *   visible: bool,
     *   phase: string,
     *   is_sequential: bool,
     *   total: int,
     *   completed: int,
     *   percent: int,
     *   phase_label: string,
     *   summary: string,
     *   current_turn_name: string|null,
     *   all_client_signatures_complete: bool,
     *   video_verification_complete: bool,
     *   attorney_has_signed: bool,
     *   document_artifacts_ready: bool,
     *   tracker_steps: list<array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     state: string
     *   }>,
     *   documents: list<array{
     *     document_id: int,
     *     title: string,
     *     status: string,
     *     is_sequential: bool,
     *     total: int,
     *     completed: int,
     *     percent: int,
     *     signers: list<array{
     *       signer_id: int,
     *       name: string,
     *       email: string,
     *       role_label: string,
     *       status: string,
     *       status_label: string,
     *       signing_order: int|null,
     *       is_completed: bool,
     *       is_pending: bool,
     *       can_act_now: bool,
     *       can_resend: bool,
     *       can_remind: bool,
     *       waiting_label: string|null,
     *       completed_at: string|null
     *     }>
     *   }>
     * }
     */
    public function summarize(NotaryRequest $request, ?int $attorneyUserId = null): array
    {
        $request->loadMissing(['documents.documentSigners']);

        $documents = $request->documents
            ->filter(fn (Document $document): bool => in_array($document->status, [DocumentStatus::Pending, DocumentStatus::Completed], true))
            ->values();

        $documentSummaries = $documents
            ->map(fn (Document $document): array => $this->summarizeDocument($document, $attorneyUserId))
            ->filter(fn (array $summary): bool => $summary['total'] > 0)
            ->values()
            ->all();

        $total = (int) collect($documentSummaries)->sum('total');
        $completed = (int) collect($documentSummaries)->sum('completed');
        $percent = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

        $currentTurnName = null;
        foreach ($documentSummaries as $documentSummary) {
            foreach ($documentSummary['signers'] as $signer) {
                if ($signer['can_act_now'] && $signer['is_pending']) {
                    $currentTurnName = $signer['name'];
                    break 2;
                }
            }
        }

        $isSequential = collect($documentSummaries)->contains(fn (array $summary): bool => $summary['is_sequential']);
        $allClientSignaturesComplete = $this->notaryRequestWorkflowService->documentsReadyForSession($request);
        $requireVideo = (bool) config('docutrust.notary.require_video_session', true);
        $videoVerificationComplete = ! $requireVideo || $this->notaryRequestWorkflowService->hasCompletedSession($request);
        $attorneyHasSigned = $this->notaryRequestWorkflowService->hasAttorneySignedAllDocuments($request);
        $documentArtifactsReady = $this->notaryRequestWorkflowService->requestHasCoreArtifacts($request);

        $phase = match (true) {
            $total === 0 => 'idle',
            ! $allClientSignaturesComplete => 'awaiting_signatures',
            ! $videoVerificationComplete => 'awaiting_video',
            ! $attorneyHasSigned => 'awaiting_attorney_signature',
            ! $documentArtifactsReady => 'finalizing',
            default => 'document_ready',
        };

        return [
            'visible' => $total > 0 && in_array($phase, [
                'awaiting_signatures',
                'attorney_turn',
                'awaiting_video',
                'awaiting_attorney_signature',
                'finalizing',
                'document_ready',
            ], true),
            'phase' => $phase,
            'is_sequential' => $isSequential,
            'total' => $total,
            'completed' => $completed,
            'percent' => $percent,
            'phase_label' => $this->phaseLabel($phase),
            'summary' => $this->buildSummaryLabel($completed, $total, $currentTurnName, $phase),
            'current_turn_name' => $currentTurnName,
            'all_client_signatures_complete' => $allClientSignaturesComplete,
            'video_verification_complete' => $videoVerificationComplete,
            'attorney_has_signed' => $attorneyHasSigned,
            'document_artifacts_ready' => $documentArtifactsReady,
            'tracker_steps' => $this->buildTrackerSteps(
                phase: $phase,
                total: $total,
                allClientSignaturesComplete: $allClientSignaturesComplete,
                videoVerificationComplete: $videoVerificationComplete,
                attorneyHasSigned: $attorneyHasSigned,
                documentArtifactsReady: $documentArtifactsReady,
            ),
            'documents' => $documentSummaries,
        ];
    }

    /**
     * @return array{
     *   document_id: int,
     *   title: string,
     *   status: string,
     *   is_sequential: bool,
     *   total: int,
     *   completed: int,
     *   percent: int,
     *   signers: list<array{
     *     signer_id: int,
     *     name: string,
     *     email: string,
     *     role_label: string,
     *     status: string,
     *     status_label: string,
     *     signing_order: int|null,
     *     is_completed: bool,
     *     is_pending: bool,
     *     can_act_now: bool,
     *     can_resend: bool,
     *     can_remind: bool,
     *     waiting_label: string|null,
     *     completed_at: string|null
     *   }>
     * }
     */
    public function summarizeDocument(Document $document, ?int $attorneyUserId = null): array
    {
        $document->loadMissing('documentSigners');

        $signers = $document->documentSigners
            ->filter(function (DocumentSigner $signer) use ($attorneyUserId): bool {
                if (! $signer->requiresAction()) {
                    return false;
                }

                if ($attorneyUserId !== null && (int) $signer->user_id === $attorneyUserId) {
                    return false;
                }

                return true;
            })
            ->sortBy(fn (DocumentSigner $signer): int => $signer->signing_order ?? 999999)
            ->values()
            ->map(fn (DocumentSigner $signer): array => $this->summarizeSigner($document, $signer))
            ->all();

        $total = count($signers);
        $completed = collect($signers)->where('is_completed', true)->count();
        $percent = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

        return [
            'document_id' => $document->id,
            'title' => $document->title,
            'status' => $document->status->value,
            'is_sequential' => $document->usesSequentialSigningWorkflow(),
            'total' => $total,
            'completed' => $completed,
            'percent' => $percent,
            'signers' => $signers,
        ];
    }

    /**
     * @return array{
     *   signer_id: int,
     *   name: string,
     *   email: string,
     *   role_label: string,
     *   status: string,
     *   status_label: string,
     *   signing_order: int|null,
     *   is_completed: bool,
     *   is_pending: bool,
     *   can_act_now: bool,
     *   can_resend: bool,
     *   can_remind: bool,
     *   waiting_label: string|null,
     *   completed_at: string|null
     * }
     */
    private function summarizeSigner(Document $document, DocumentSigner $signer): array
    {
        $blockingMessage = $document->status === DocumentStatus::Pending
            ? $this->documentSigningWorkflowService->canSignerModifyFields($document, $signer)
            : __('This document is not available right now.');

        $canActNow = $document->status === DocumentStatus::Pending
            && $signer->status === DocumentSignerStatus::Pending
            && $blockingMessage === null;

        $isCompleted = $signer->status->isCompleted();
        $isPending = $signer->status === DocumentSignerStatus::Pending;

        $waitingLabel = null;
        if ($isPending && ! $canActNow) {
            $waitingLabel = $blockingMessage ?? __('Waiting to sign');
        }

        $canEmail = $signer->signingMethod() === SigningMethod::EmailLink
            && $document->status === DocumentStatus::Pending
            && $isPending;

        return [
            'signer_id' => $signer->id,
            'name' => $signer->name,
            'email' => $signer->email,
            'role_label' => $this->roleLabel($signer),
            'status' => $signer->status->value,
            'status_label' => $this->statusLabel($signer),
            'signing_order' => $signer->signing_order,
            'is_completed' => $isCompleted,
            'is_pending' => $isPending,
            'can_act_now' => $canActNow,
            'can_resend' => $canEmail,
            'can_remind' => $canEmail,
            'waiting_label' => $waitingLabel,
            'completed_at' => $signer->signed_at?->timezone(config('docutrust.notary.timezone', 'Asia/Manila'))->format('M j, Y g:i A'),
        ];
    }

    private function roleLabel(DocumentSigner $signer): string
    {
        return match ($signer->roleType()) {
            TemplateRoleType::Approver => __('Approver'),
            TemplateRoleType::Recipient => __('Recipient'),
            default => __('Signer'),
        };
    }

    private function statusLabel(DocumentSigner $signer): string
    {
        return match ($signer->status) {
            DocumentSignerStatus::Signed => __('Signed'),
            DocumentSignerStatus::Approved => __('Approved'),
            DocumentSignerStatus::Notified => __('Notified'),
            default => __('Pending'),
        };
    }

    private function buildSummaryLabel(int $completed, int $total, ?string $currentTurnName, string $phase): string
    {
        if ($total === 0) {
            return __('No signers assigned yet.');
        }

        if ($phase === 'awaiting_video') {
            return __('All signers have signed. Complete live video verification with each party.');
        }

        if ($phase === 'awaiting_attorney_signature') {
            return __('Video verification is complete. Sign the contract as attorney to continue.');
        }

        if ($phase === 'finalizing') {
            return __('Attorney signature recorded. Generating final PDF, certificate, and document hash.');
        }

        if ($phase === 'document_ready') {
            return __('The instrument is ready. Continue with register entry and digital notarization.');
        }

        if ($phase === 'attorney_turn') {
            return __('All client signatures are complete. Attorney signing is next.');
        }

        $base = __(':completed of :total parties signed', [
            'completed' => $completed,
            'total' => $total,
        ]);

        if ($currentTurnName !== null && $currentTurnName !== '') {
            return $base.'. '.__('Waiting on :name.', ['name' => $currentTurnName]);
        }

        return $base.'. '.__('Waiting for remaining signatures.');
    }

    private function phaseLabel(string $phase): string
    {
        return match ($phase) {
            'awaiting_signatures' => __('Waiting for signatures'),
            'attorney_turn', 'awaiting_attorney_signature' => __('Your turn: sign the contract'),
            'awaiting_video' => __('Video verification required'),
            'finalizing' => __('Finalizing instrument'),
            'document_ready' => __('Instrument ready'),
            default => __('Document tracker'),
        };
    }

    /**
     * @return list<array{key: string, label: string, description: string, state: string}>
     */
    private function buildTrackerSteps(
        string $phase,
        int $total,
        bool $allClientSignaturesComplete,
        bool $videoVerificationComplete,
        bool $attorneyHasSigned,
        bool $documentArtifactsReady,
    ): array {
        return [
            [
                'key' => 'sent',
                'label' => __('Sent to signers'),
                'description' => __('The document has entered the signing workflow.'),
                'state' => $total > 0 ? 'complete' : 'upcoming',
            ],
            [
                'key' => 'signatures',
                'label' => __('Waiting for signatures'),
                'description' => __('Track each signer until their required action is complete.'),
                'state' => match (true) {
                    $allClientSignaturesComplete => 'complete',
                    $phase === 'awaiting_signatures' => 'current',
                    default => 'upcoming',
                },
            ],
            [
                'key' => 'video',
                'label' => __('Video verification'),
                'description' => __('Confirm signer identity on a live video call.'),
                'state' => match (true) {
                    $videoVerificationComplete => 'complete',
                    $phase === 'awaiting_video' => 'current',
                    default => 'upcoming',
                },
            ],
            [
                'key' => 'attorney',
                'label' => __('Attorney signing'),
                'description' => __('The attorney places and signs their notarial fields.'),
                'state' => match (true) {
                    $attorneyHasSigned => 'complete',
                    in_array($phase, ['attorney_turn', 'awaiting_attorney_signature'], true) => 'current',
                    default => 'upcoming',
                },
            ],
            [
                'key' => 'finalization',
                'label' => __('Finalization'),
                'description' => __('Generate the final PDF, certificate, hash, and seal artifacts.'),
                'state' => match (true) {
                    $documentArtifactsReady => 'complete',
                    $phase === 'finalizing' => 'current',
                    default => 'upcoming',
                },
            ],
        ];
    }
}
