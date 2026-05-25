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

class NotarySignerVideoInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly NotaryRequest $notaryRequest,
        public readonly NotarySigner $notarySigner,
        public readonly NotarySession $notarySession,
        public readonly string $joinUrl,
    ) {
        $this->onQueue((string) config('docutrust.queues.notifications'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Video verification for :title', ['title' => $this->notaryRequest->title]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.notary.signer-video-invitation',
            with: [
                'notaryRequest' => $this->notaryRequest,
                'signer' => $this->notarySigner,
                'session' => $this->notarySession,
                'joinUrl' => $this->joinUrl,
                'scheduledFor' => $this->notarySession->scheduled_for?->timezone(
                    config('docutrust.notary.timezone', 'Asia/Manila')
                )->format('M j, Y g:i A').' (PHT)',
                'attorneyName' => $this->notaryRequest->notary?->buildFullName()
                    ?: $this->notaryRequest->notary?->name
                    ?: config('app.name'),
            ],
        );
    }
}
