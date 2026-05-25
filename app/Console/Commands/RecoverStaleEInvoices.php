<?php

namespace App\Console\Commands;

use App\Services\EInvoiceService;
use Illuminate\Console\Command;

class RecoverStaleEInvoices extends Command
{
    protected $signature = 'app:recover-stale-einvoices {--queued-minutes=10 : Minimum age in minutes before queued invoices are re-submitted} {--processing-minutes=15 : Minimum age in minutes before submitted or processing invoices are re-polled}';

    protected $description = 'Re-dispatch stale queued or processing e-invoices back into the EIS background pipeline';

    public function handle(EInvoiceService $eInvoiceService): int
    {
        $result = $eInvoiceService->dispatchStaleInvoices(
            queuedAfterMinutes: (int) $this->option('queued-minutes'),
            processingAfterMinutes: (int) $this->option('processing-minutes'),
        );

        $this->info(sprintf(
            'Dispatched %d queued invoice(s) and %d inquiry refresh(es).',
            $result['queued'],
            $result['inquiry'],
        ));

        return self::SUCCESS;
    }
}
