<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SignerInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $documentTitle,
        public string $senderName,
        public string $signUrl,
        public ?string $expiresAt = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Signature request: :title', ['title' => $this->documentTitle]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.signer-invitation',
        );
    }
}
