<?php

namespace App\Mail;

use App\Models\NotaryRequest;
use App\Models\NotarySession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotarySessionScheduledMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly NotaryRequest $notaryRequest,
        public readonly NotarySession $notarySession,
    ) {
        $this->onQueue((string) config('docutrust.queues.notifications'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Notary session scheduled: :title', ['title' => $this->notaryRequest->title]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.notary.session-scheduled',
            with: [
                'notaryRequest' => $this->notaryRequest,
                'session' => $this->notarySession,
                'scheduledFor' => $this->notarySession->scheduled_for?->timezone('Asia/Manila')->format('M j, Y g:i A').' (PHT)',
                'meetingUrl' => $this->notarySession->meeting_url,
            ],
        );
    }
}
