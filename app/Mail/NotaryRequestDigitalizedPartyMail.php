<?php

namespace App\Mail;

use App\Models\DocumentSigner;
use App\Models\NotaryRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotaryRequestDigitalizedPartyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly NotaryRequest $notaryRequest,
        public readonly DocumentSigner $signer,
    ) {
        $this->onQueue((string) config('docutrust.queues.notifications'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your digitally notarized copy: :title', [
                'title' => $this->notaryRequest->title,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.notary.request-digitalized-party',
            with: [
                'notaryRequest' => $this->notaryRequest,
                'signer' => $this->signer,
                'notaryName' => $this->notaryRequest->notary?->buildFullName()
                    ?: $this->notaryRequest->notary?->name
                    ?: config('app.name'),
                'digitalizedAt' => $this->notaryRequest->updated_at?->timezone(
                    config('docutrust.notary.timezone', 'Asia/Manila')
                )->format('M j, Y g:i A').' (PHT)',
            ],
        );
    }
}
