<?php

namespace App\Mail;

use App\Enums\TemplateRoleType;
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
        public bool $requiresDocumentPassword = false,
        public ?string $documentPasswordHint = null,
        public ?string $customSubject = null,
        public ?string $customMessage = null,
        public string $participantRoleType = 'signer',
    ) {
        $this->onQueue((string) config('docutrust.queues.notifications'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->resolvedSubject(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.signer-invitation',
        );
    }

    private function resolvedSubject(): string
    {
        $subject = trim((string) $this->customSubject);

        if ($subject !== '') {
            return $subject;
        }

        if ($this->participantRoleType === TemplateRoleType::Approver->value) {
            return __('Approval request: :title', ['title' => $this->documentTitle]);
        }

        return __('Signature request: :title', ['title' => $this->documentTitle]);
    }
}
