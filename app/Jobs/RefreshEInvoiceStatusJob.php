<?php

namespace App\Jobs;

use App\Enums\EInvoiceStatus;
use App\Models\EInvoice;
use App\Services\EInvoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class RefreshEInvoiceStatusJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 10;

    public function __construct(public int $einvoiceId)
    {
        $this->onQueue((string) config('docutrust.queues.einvoices'));
    }

    public function handle(EInvoiceService $eInvoiceService): void
    {
        try {
            $invoice = EInvoice::query()->find($this->einvoiceId);
            if (! $invoice instanceof EInvoice || $invoice->status->isTerminal()) {
                return;
            }

            $invoice = $eInvoiceService->refreshSubmittedInvoice($invoice);

            if (in_array($invoice->status, [EInvoiceStatus::Submitted, EInvoiceStatus::Processing], true)) {
                self::dispatch($invoice->id)->delay(now()->addMinutes(2));
            }
        } catch (Throwable $throwable) {
            Log::channel('errors')->error('Queued EIS invoice inquiry failed', [
                'einvoice_id' => $this->einvoiceId,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);
        }
    }
}
