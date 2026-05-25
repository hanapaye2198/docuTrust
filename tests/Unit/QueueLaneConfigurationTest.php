<?php

namespace Tests\Unit;

use App\Jobs\GenerateCertificateJob;
use App\Jobs\GenerateDocumentPdfJob;
use App\Jobs\RefreshEInvoiceStatusJob;
use App\Jobs\SendDocumentEmailJob;
use App\Jobs\SendReminderJob;
use App\Jobs\SubmitEInvoiceJob;
use App\Mail\NotaryPaymentReadyMail;
use App\Mail\SendOtpMail;
use App\Mail\SignerInvitationMail;
use App\Models\NotaryRequest;
use App\Models\Payment;
use Tests\TestCase;

class QueueLaneConfigurationTest extends TestCase
{
    public function test_document_jobs_use_document_and_notification_queues(): void
    {
        $this->assertSame('documents', (new GenerateDocumentPdfJob(1, 'final'))->queue);
        $this->assertSame('documents', (new GenerateCertificateJob(1))->queue);
        $this->assertSame('notifications', (new SendDocumentEmailJob(1, 2, 'signer@example.test', SendDocumentEmailJob::TYPE_SENT_TO_SIGNER, 'https://example.test/sign'))->queue);
        $this->assertSame('notifications', (new SendReminderJob(1, 2))->queue);
    }

    public function test_einvoice_jobs_use_einvoice_queue(): void
    {
        $this->assertSame('einvoices', (new SubmitEInvoiceJob(1))->queue);
        $this->assertSame('einvoices', (new RefreshEInvoiceStatusJob(1))->queue);
    }

    public function test_queued_mailables_use_notifications_queue(): void
    {
        $notaryRequest = new NotaryRequest([
            'title' => 'Queue test',
        ]);
        $payment = new Payment([
            'amount' => 1,
            'currency' => 'PHP',
        ]);

        $this->assertSame('notifications', (new SignerInvitationMail('Doc', 'Owner', 'https://example.test/sign'))->queue);
        $this->assertSame('notifications', (new SendOtpMail('123456'))->queue);
        $this->assertSame('notifications', (new NotaryPaymentReadyMail($notaryRequest, $payment))->queue);
    }
}
