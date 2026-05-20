<?php

namespace App\Mail;

use App\Models\NotaryRequest;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotaryPaymentReadyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly NotaryRequest $notaryRequest,
        public readonly Payment $payment,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Payment required for your notarization request: :title', ['title' => $this->notaryRequest->title]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.notary.payment-ready',
            with: [
                'notaryRequest' => $this->notaryRequest,
                'payment' => $this->payment,
                'notaryName' => $this->notaryRequest->notary?->name ?? 'Notary Public',
                'amount' => number_format((float) $this->payment->amount, 2),
                'expiresAt' => $this->payment->expires_at?->timezone('Asia/Manila')->format('M j, Y g:i A').' (PHT)',
            ],
        );
    }
}
