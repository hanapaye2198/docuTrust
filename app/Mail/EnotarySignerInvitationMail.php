<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EnotarySignerInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $signerName,
        public string $attorneyName,
        public string $caseTitle,
        public string $acceptUrl,
        public string $expiresAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('e-Notary invitation: :title', ['title' => $this->caseTitle]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.enotary-signer-invitation',
        );
    }
}
