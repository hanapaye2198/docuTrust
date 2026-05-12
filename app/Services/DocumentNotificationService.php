<?php

namespace App\Services;

use App\Enums\DocumentSignerStatus;
use App\Events\DocumentCompleted;
use App\Events\DocumentSent;
use App\Events\DocumentSignerCompleted;
use App\Jobs\SendDocumentEmailJob;
use App\Jobs\SendReminderJob;
use App\Models\AppNotification;
use App\Models\Document;
use App\Models\DocumentSigner;

class DocumentNotificationService
{
    public function __construct(
        private readonly SigningMethodService $signingMethodService,
    ) {}

    public function handleDocumentSent(DocumentSent $event): void
    {
        $document = $event->document->loadMissing('documentSigners', 'user');

        foreach ($document->documentSigners as $signer) {
            if (! $signer->requiresAction()) {
                continue;
            }

            SendDocumentEmailJob::dispatch(
                documentId: $document->id,
                signerId: $signer->id,
                recipientEmail: $signer->email,
                type: SendDocumentEmailJob::TYPE_SENT_TO_SIGNER,
                signUrl: $this->signingMethodService->signerEntryUrl($signer),
            );
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
        $document = $event->document->loadMissing('user');
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

        $this->createInAppNotification(
            $document->user_id,
            'document.signed',
            __($signer->isApprover() ? ':name approved ":title".' : ':name signed ":title".', [
                'name' => $signer->name,
                'title' => $document->title,
            ])
        );
    }

    public function handleDocumentCompleted(DocumentCompleted $event): void
    {
        $document = $event->document->loadMissing('user');

        SendDocumentEmailJob::dispatch(
            documentId: $document->id,
            signerId: null,
            recipientEmail: $document->user->email,
            type: SendDocumentEmailJob::TYPE_COMPLETED,
        );

        foreach ($document->loadMissing('documentSigners')->documentSigners as $participant) {
            if (! $participant->isRecipient()) {
                continue;
            }

            $participant->update([
                'status' => DocumentSignerStatus::Notified,
                'signed_at' => now(),
            ]);

            SendDocumentEmailJob::dispatch(
                documentId: $document->id,
                signerId: null,
                recipientEmail: $participant->email,
                type: SendDocumentEmailJob::TYPE_COMPLETED,
            );
        }

        $this->createInAppNotification(
            $document->user_id,
            'document.completed',
            __('Document ":title" is fully completed.', ['title' => $document->title])
        );
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
