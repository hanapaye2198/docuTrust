<?php

namespace App\Mail;

use App\Enums\TemplateRoleType;
use App\Models\Document;
use App\Models\DocumentSigner;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PendingSignatureReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Document $document,
        public DocumentSigner $signer,
        public string $signUrl,
        public bool $requiresDocumentPassword = false,
        public ?string $documentPasswordHint = null,
        public ?string $customSubject = null,
        public ?string $customMessage = null,
        public string $participantRoleType = 'signer',
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->resolvedSubject(),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.pending-signature-reminder',
        );
    }

    private function resolvedSubject(): string
    {
        $subject = trim((string) $this->customSubject);

        if ($subject !== '') {
            return __('Reminder: :subject', ['subject' => $subject]);
        }

        if ($this->participantRoleType === TemplateRoleType::Approver->value) {
            return __('Reminder: pending approval for :title', ['title' => $this->document->title]);
        }

        return __('Reminder: pending signature for :title', ['title' => $this->document->title]);
    }
}
