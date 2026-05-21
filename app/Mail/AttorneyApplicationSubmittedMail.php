<?php

namespace App\Mail;

use App\Models\NotaryCredential;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AttorneyApplicationSubmittedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly NotaryCredential $credential,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('New attorney application: :name', [
                'name' => $this->credential->user?->name ?? __('Applicant'),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.attorney.application-submitted',
            with: [
                'credential' => $this->credential,
                'applicantName' => $this->credential->user?->name ?? __('Unknown'),
                'reviewUrl' => route('admin.attorney-applications.show', $this->credential),
            ],
        );
    }
}
