<?php

namespace App\Jobs;

use App\Enums\SigningMethod;
use App\Mail\DocumentCompletedMail;
use App\Mail\DocumentSignedMail;
use App\Mail\NotaryDocumentSignerSignedMail;
use App\Mail\SignerInvitationMail;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\NotaryRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendDocumentEmailJob implements ShouldQueue
{
    use Queueable;

    public const TYPE_SENT_TO_SIGNER = 'sent_to_signer';

    public const TYPE_SIGNED = 'signed';

    public const TYPE_COMPLETED = 'completed';

    public const TYPE_NOTARY_SIGNING_RECORDED = 'notary_signing_recorded';

    public function __construct(
        public int $documentId,
        public ?int $signerId,
        public string $recipientEmail,
        public string $type,
        public ?string $signUrl = null,
    ) {
        $this->onQueue((string) config('docutrust.queues.notifications'));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $document = Document::query()->find($this->documentId);
            if ($document === null) {
                return;
            }

            if ($this->type === self::TYPE_SENT_TO_SIGNER) {
                $signer = $this->signerId !== null ? DocumentSigner::query()->find($this->signerId) : null;
                if ($signer === null || $this->signUrl === null) {
                    return;
                }

                Mail::to($this->recipientEmail)->queue(
                    new SignerInvitationMail(
                        documentTitle: $document->title,
                        senderName: $document->user?->name ?? config('app.name'),
                        signUrl: $this->signUrl,
                        expiresAt: $signer->expires_at?->toDateTimeString(),
                        requiresDocumentPassword: $document->hasAccessPassword(),
                        documentPasswordHint: $document->access_password_hint,
                        customSubject: $document->email_subject,
                        customMessage: $document->email_message,
                        participantRoleType: $signer->roleType()->value,
                        isAccountVerified: $signer->signingMethod() === SigningMethod::AccountVerified,
                    )
                );

                return;
            }

            if ($this->type === self::TYPE_SIGNED) {
                $signer = $this->signerId !== null ? DocumentSigner::query()->find($this->signerId) : null;
                if ($signer === null) {
                    return;
                }

                Mail::to($this->recipientEmail)->queue(new DocumentSignedMail($document, $signer));

                return;
            }

            if ($this->type === self::TYPE_COMPLETED) {
                Mail::to($this->recipientEmail)->queue(new DocumentCompletedMail(
                    $document,
                    null,
                    $this->signUrl,
                ));

                return;
            }

            if ($this->type === self::TYPE_NOTARY_SIGNING_RECORDED) {
                $signer = $this->signerId !== null ? DocumentSigner::query()->find($this->signerId) : null;
                if ($signer === null || $document->notary_request_id === null) {
                    return;
                }

                $notaryRequest = NotaryRequest::query()->find($document->notary_request_id);
                if ($notaryRequest === null) {
                    return;
                }

                Mail::to($this->recipientEmail)->queue(new NotaryDocumentSignerSignedMail(
                    $notaryRequest,
                    $document,
                    $signer,
                ));
            }
        } catch (Throwable $throwable) {
            Log::channel('errors')->error('Queued document email failed', [
                'document_id' => $this->documentId,
                'signer_id' => $this->signerId,
                'type' => $this->type,
                'recipient_email' => $this->recipientEmail,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);
        }
    }
}
