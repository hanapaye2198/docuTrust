<?php

namespace App\Jobs;

use App\Models\EInvoice;
use App\Services\EInvoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SubmitEInvoiceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $einvoiceId)
    {
        $this->onQueue((string) config('docutrust.queues.einvoices'));
    }

    public function handle(EInvoiceService $eInvoiceService): void
    {
        try {
            $invoice = EInvoice::query()->find($this->einvoiceId);
            if (! $invoice instanceof EInvoice) {
                return;
            }

            $eInvoiceService->submitQueuedInvoice($invoice);
        } catch (Throwable $throwable) {
            Log::channel('errors')->error('Queued EIS invoice submission failed', [
                'einvoice_id' => $this->einvoiceId,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);
        }
    }
}
