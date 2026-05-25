<?php

namespace App\Console\Commands;

use App\Services\EInvoiceSubmissionRetentionService;
use Illuminate\Console\Command;

class PruneEInvoiceSubmissionPayloads extends Command
{
    protected $signature = 'app:prune-einvoice-submission-payloads
        {--days=30 : Minimum age in days before resolved submission payloads are pruned}
        {--limit=500 : Maximum number of rows to prune in one run}';

    protected $description = 'Prune old request and response payload blobs from resolved e-invoice submission audits';

    public function handle(EInvoiceSubmissionRetentionService $retentionService): int
    {
        $pruned = $retentionService->pruneResolvedPayloads(
            olderThanDays: (int) $this->option('days'),
            limit: (int) $this->option('limit'),
        );

        $this->info(sprintf('Pruned payloads for %d e-invoice submission audit row(s).', $pruned));

        return self::SUCCESS;
    }
}
