<?php

namespace App\Mail;

use App\Models\NotaryRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotaryRequestApprovedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly NotaryRequest $notaryRequest,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Notary request approved: :title', ['title' => $this->notaryRequest->title]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.notary.request-approved',
            with: [
                'notaryRequest' => $this->notaryRequest,
                'notaryName' => $this->notaryRequest->notary?->name ?? 'Notary Public',
                'approvedAt' => $this->notaryRequest->approved_at?->timezone('Asia/Manila')->format('M j, Y g:i A') . ' (PHT)',
            ],
        );
    }
}
