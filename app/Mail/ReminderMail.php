<?php

namespace App\Mail;

use App\Enums\TemplateRoleType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $documentTitle,
        public string $signUrl,
        public bool $requiresDocumentPassword = false,
        public ?string $documentPasswordHint = null,
        public ?string $customSubject = null,
        public ?string $customMessage = null,
        public string $participantRoleType = 'signer',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->resolvedSubject(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reminder',
        );
    }

    private function resolvedSubject(): string
    {
        $subject = trim((string) $this->customSubject);

        if ($subject !== '') {
            return __('Reminder: :subject', ['subject' => $subject]);
        }

        if ($this->participantRoleType === TemplateRoleType::Approver->value) {
            return __('Reminder: pending approval for :title', ['title' => $this->documentTitle]);
        }

        return __('Reminder: pending signature for :title', ['title' => $this->documentTitle]);
    }
}
