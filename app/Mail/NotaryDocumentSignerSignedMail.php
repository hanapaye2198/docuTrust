<?php

namespace App\Mail;

use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\NotaryRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotaryDocumentSignerSignedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly NotaryRequest $notaryRequest,
        public readonly Document $document,
        public readonly DocumentSigner $signer,
    ) {
        $this->onQueue((string) config('docutrust.queues.notifications'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('You signed ":title" — video verification is next', [
                'title' => $this->document->title,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.notary.document-signer-signed',
            with: [
                'notaryRequest' => $this->notaryRequest,
                'document' => $this->document,
                'signer' => $this->signer,
                'attorneyName' => $this->notaryRequest->notary?->buildFullName()
                    ?: $this->notaryRequest->notary?->name
                    ?: config('app.name'),
            ],
        );
    }
}
