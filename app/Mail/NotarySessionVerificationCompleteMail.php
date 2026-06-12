<?php

namespace App\Mail;

use App\Models\NotaryRequest;
use App\Models\NotarySession;
use App\Models\NotarySigner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotarySessionVerificationCompleteMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly NotaryRequest $notaryRequest,
        public readonly NotarySession $session,
        public readonly NotarySigner $notarySigner,
    ) {
        $this->onQueue((string) config('docutrust.queues.notifications'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Identity verified on video: :title', [
                'title' => $this->notaryRequest->title,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.notary.session-verification-complete',
            with: [
                'notaryRequest' => $this->notaryRequest,
                'signer' => $this->notarySigner,
                'verifiedAt' => $this->session->ended_at?->timezone(
                    config('docutrust.notary.timezone', 'Asia/Manila')
                )->format('M j, Y g:i A').' (PHT)',
                'attorneyName' => $this->notaryRequest->notary?->buildFullName()
                    ?: $this->notaryRequest->notary?->name
                    ?: config('app.name'),
            ],
        );
    }
}
