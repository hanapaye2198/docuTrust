<?php

namespace App\Jobs;

use App\Mail\DocumentCompletedMail;
use App\Mail\DocumentSignedMail;
use App\Mail\SignerInvitationMail;
use App\Models\Document;
use App\Models\DocumentSigner;
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

    public function __construct(
        public int $documentId,
        public ?int $signerId,
        public string $recipientEmail,
        public string $type,
        public ?string $signUrl = null,
    ) {}

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
                Mail::to($this->recipientEmail)->queue(new DocumentCompletedMail($document));
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
