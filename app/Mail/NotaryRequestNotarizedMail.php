<?php

namespace App\Mail;

use App\Models\NotaryRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotaryRequestNotarizedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly NotaryRequest $notaryRequest,
    ) {
        $this->onQueue((string) config('docutrust.queues.notifications'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Document notarized: :title', ['title' => $this->notaryRequest->title]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.notary.request-notarized',
            with: [
                'notaryRequest' => $this->notaryRequest,
                'completedAt' => $this->notaryRequest->completed_at?->timezone('Asia/Manila')->format('M j, Y g:i A').' (PHT)',
            ],
        );
    }
}
