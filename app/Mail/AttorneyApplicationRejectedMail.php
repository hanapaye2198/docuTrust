<?php

namespace App\Mail;

use App\Models\NotaryCredential;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AttorneyApplicationRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly NotaryCredential $credential,
        public readonly string $reason,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Attorney application update'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.attorney.application-rejected',
            with: [
                'credential' => $this->credential,
                'reason' => $this->reason,
                'reapplyUrl' => route('settings.attorney-application'),
            ],
        );
    }
}
