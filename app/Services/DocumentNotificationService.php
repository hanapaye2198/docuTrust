<?php

namespace App\Services;

use App\Enums\DocumentSignerStatus;
use App\Enums\SigningMethod;
use App\Events\DocumentCompleted;
use App\Events\DocumentSent;
use App\Events\DocumentSignerCompleted;
use App\Jobs\SendDocumentEmailJob;
use App\Jobs\SendReminderJob;
use App\Models\AppNotification;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\NotaryRequest;
use RuntimeException;

class DocumentNotificationService
{
    public function __construct(
        private readonly SigningMethodService $signingMethodService,
        private readonly DocumentSigningWorkflowService $documentSigningWorkflowService,
        private readonly NotarySignerVideoInvitationService $notarySignerVideoInvitationService,
    ) {}

    public function handleDocumentSent(DocumentSent $event): void
    {
        $document = $event->document->loadMissing('documentSigners', 'user');

        foreach ($document->documentSigners as $signer) {
            $this->sendSignerInvitationIfEligible($document, $signer);

            // For AccountVerified signers who have a linked user account,
            // create an in-app notification so they see a prompt on login.
            if ($signer->signingMethod() === SigningMethod::AccountVerified
                && $signer->user_id !== null
                && $signer->requiresAction()
            ) {
                $this->createInAppNotification(
                    $signer->user_id,
                    'document.sign_requested',
                    __(':sender requests your signature on ":title". Open your documents to sign.', [
                        'sender' => $document->user?->name ?? __('Someone'),
                        'title'  => $document->title,
                    ])
                );
            }
        }

        $this->createInAppNotification(
            $document->user_id,
            'document.sent',
            __('Document ":title" was sent to :count participant(s).', [
                'title' => $document->title,
                'count' => $document->documentSigners->filter(fn (DocumentSigner $participant) => $participant->requiresAction())->count(),
            ])
        );
    }

    public function handleSignerCompleted(DocumentSignerCompleted $event): void
    {
        $document = $event->document->loadMissing('documentSigners', 'user');
        $signer = $event->signer;

        if ($signer->isSigner()) {
            SendDocumentEmailJob::dispatch(
                documentId: $document->id,
                signerId: $signer->id,
                recipientEmail: $signer->email,
                type: SendDocumentEmailJob::TYPE_SIGNED,
            );
        }

        SendDocumentEmailJob::dispatch(
            documentId: $document->id,
            signerId: $signer->id,
            recipientEmail: $document->user->email,
            type: SendDocumentEmailJob::TYPE_SIGNED,
        );

        foreach ($document->documentSigners as $nextSigner) {
            if ($nextSigner->id === $signer->id) {
                continue;
            }

            $this->sendSignerInvitationIfEligible($document, $nextSigner);
        }

        $this->createInAppNotification(
            $document->user_id,
            'document.signed',
            __($signer->isApprover() ? ':name approved ":title".' : ':name signed ":title".', [
                'name' => $signer->name,
                'title' => $document->title,
            ])
        );

        $this->syncVideoInvitationsWhenAllPartiesSigned($document);
    }

    private function syncVideoInvitationsWhenAllPartiesSigned(Document $document): void
    {
        if ($document->notary_request_id === null || ! $this->notarySignerVideoInvitationService->shouldAutoInviteAfterSigning()) {
            return;
        }

        $request = NotaryRequest::query()
            ->with(['documents.documentSigners', 'signers', 'sessions', 'notary'])
            ->find($document->notary_request_id);

        if ($request === null) {
            return;
        }

        if (! app(NotaryRequestWorkflowService::class)->documentsReadyForSession($request)) {
            return;
        }

        try {
            $this->notarySignerVideoInvitationService->inviteAllSignersWhenReady(
                $request->fresh(['signers', 'sessions', 'notary', 'documents.documentSigners']),
            );
        } catch (RuntimeException) {
            // Parties are not ready for video invitations yet.
        }
    }

    public function sendSignerInvitation(Document $document, DocumentSigner $signer): void
    {
        SendDocumentEmailJob::dispatch(
            documentId: $document->id,
            signerId: $signer->id,
            recipientEmail: $signer->email,
            type: SendDocumentEmailJob::TYPE_SENT_TO_SIGNER,
            signUrl: $this->signingMethodService->signerEntryUrl($signer),
        );
    }

    public function sendSignerInvitationIfEligible(Document $document, DocumentSigner $signer): void
    {
        if (! $signer->requiresAction()) {
            return;
        }

        // Send invitation for EmailLink and AccountVerified signers.
        // AccountVerified signers receive an email with a link to their DocuTrust
        // account login page (sign.account.show) rather than a public token URL.
        $method = $signer->signingMethod();
        if (! in_array($method, [SigningMethod::EmailLink, SigningMethod::AccountVerified], true)) {
            return;
        }

        if ($signer->status !== DocumentSignerStatus::Pending) {
            return;
        }

        if ($this->documentSigningWorkflowService->canSignerModifyFields($document, $signer) !== null) {
            return;
        }

        $this->sendSignerInvitation($document, $signer);
    }

    public function handleDocumentCompleted(DocumentCompleted $event): void
    {
        $document = $event->document->loadMissing(['user', 'documentSigners']);

        if ($document->notary_request_id !== null) {
            $this->handleNotaryDocumentSigningCompleted($document);

            return;
        }

        $this->handleStandardDocumentCompleted($document);

        $this->notarySignerVideoInvitationService->handleDocumentCompleted($document);
    }

    private function handleStandardDocumentCompleted(Document $document): void
    {
        $emailedAddresses = [];

        if (is_string($document->user?->email) && $document->user->email !== '') {
            SendDocumentEmailJob::dispatch(
                documentId: $document->id,
                signerId: null,
                recipientEmail: $document->user->email,
                type: SendDocumentEmailJob::TYPE_COMPLETED,
                signUrl: route('documents.show', $document),
            );
            $emailedAddresses[] = strtolower($document->user->email);
        }

        foreach ($document->documentSigners as $participant) {
            $recipientEmail = trim((string) $participant->email);
            if ($recipientEmail === '' || in_array(strtolower($recipientEmail), $emailedAddresses, true)) {
                continue;
            }

            if ($participant->isRecipient()) {
                $participant->update([
                    'status' => DocumentSignerStatus::Notified,
                    'signed_at' => now(),
                ]);

                SendDocumentEmailJob::dispatch(
                    documentId: $document->id,
                    signerId: $participant->id,
                    recipientEmail: $recipientEmail,
                    type: SendDocumentEmailJob::TYPE_COMPLETED,
                    signUrl: $this->signingMethodService->signerCompletedDocumentUrl($participant),
                );
                $emailedAddresses[] = strtolower($recipientEmail);

                continue;
            }

            if (! $participant->requiresAction() || ! $participant->status->isCompleted()) {
                continue;
            }

            SendDocumentEmailJob::dispatch(
                documentId: $document->id,
                signerId: $participant->id,
                recipientEmail: $recipientEmail,
                type: SendDocumentEmailJob::TYPE_COMPLETED,
                signUrl: $this->signingMethodService->signerCompletedDocumentUrl($participant),
            );
            $emailedAddresses[] = strtolower($recipientEmail);
        }

        $this->createInAppNotification(
            $document->user_id,
            'document.completed',
            __('Document ":title" is fully completed.', ['title' => $document->title])
        );
    }

    private function handleNotaryDocumentSigningCompleted(Document $document): void
    {
        $emailedAddresses = [];

        foreach ($document->documentSigners as $participant) {
            if (! $participant->requiresAction() || ! $participant->status->isCompleted()) {
                continue;
            }

            $recipientEmail = trim((string) $participant->email);
            if ($recipientEmail === '' || in_array(strtolower($recipientEmail), $emailedAddresses, true)) {
                continue;
            }

            SendDocumentEmailJob::dispatch(
                documentId: $document->id,
                signerId: $participant->id,
                recipientEmail: $recipientEmail,
                type: SendDocumentEmailJob::TYPE_NOTARY_SIGNING_RECORDED,
            );
            $emailedAddresses[] = strtolower($recipientEmail);
        }

        $this->createInAppNotification(
            $document->user_id,
            'document.notary_signing_complete',
            __('All parties signed ":title". Send video verification links to continue.', [
                'title' => $document->title,
            ])
        );

        $this->notarySignerVideoInvitationService->handleDocumentCompleted($document);
    }

    public function sendReminder(Document $document, DocumentSigner $signer): void
    {
        SendReminderJob::dispatch(
            documentId: $document->id,
            signerId: $signer->id,
        );
    }

    public function createInAppNotification(int $userId, string $type, string $message): void
    {
        AppNotification::query()->create([
            'user_id' => $userId,
            'type' => $type,
            'message' => $message,
            'read_at' => null,
            'created_at' => now(),
        ]);
    }
}
