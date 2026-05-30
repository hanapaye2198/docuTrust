<?php

namespace App\Mail;

use App\Models\NotaryRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotaryRequestSubmittedMail extends Mailable implements ShouldQueue
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
            subject: __('New notarization: :title', ['title' => $this->notaryRequest->title]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.notary.request-submitted',
            with: [
                'notaryRequest' => $this->notaryRequest,
                'requesterName' => $this->notaryRequest->requester?->name ?? 'Unknown',
                'requestType' => str_replace('_', ' ', $this->notaryRequest->request_type),
            ],
        );
    }
}
