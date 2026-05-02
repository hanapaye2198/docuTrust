<?php

namespace App\Jobs;

use App\Mail\PendingSignatureReminderMail;
use App\Models\Document;
use App\Models\DocumentSigner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendReminderJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $documentId,
        public int $signerId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $document = Document::query()->find($this->documentId);
            $signer = DocumentSigner::query()->find($this->signerId);
            if ($document === null || $signer === null) {
                return;
            }

            $token = $signer->access_token;
            if ($token === null || $token === '') {
                return;
            }

            Mail::to($signer->email)->send(
                new PendingSignatureReminderMail(
                    $document,
                    $signer,
                    route('sign.show', $token),
                )
            );
        } catch (Throwable $throwable) {
            Log::channel('errors')->error('Queued reminder email failed', [
                'document_id' => $this->documentId,
                'signer_id' => $this->signerId,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);
        }
    }
}
