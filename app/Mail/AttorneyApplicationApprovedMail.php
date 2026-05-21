<?php

namespace App\Mail;

use App\Models\NotaryCredential;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AttorneyApplicationApprovedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly NotaryCredential $credential,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Attorney access approved'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.attorney.application-approved',
            with: [
                'credential' => $this->credential,
                'dashboardUrl' => route('notary.dashboard'),
                'credentialsUrl' => route('notary.credentials'),
            ],
        );
    }
}
