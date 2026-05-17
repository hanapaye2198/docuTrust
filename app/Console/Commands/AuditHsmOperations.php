<?php

namespace App\Console\Commands;

use App\Services\HsmAuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Audit HSM operations command
 * 
 * Generates audit reports for HSM operations.
 */
class AuditHsmOperations extends Command
{
    protected $signature = 'hsm:audit
        {--start= : Start date (YYYY-MM-DD)}
        {--end= : End date (YYYY-MM-DD)}
        {--operation= : Filter by operation}
        {--json : Output in JSON format}';

    protected $description = 'Generate HSM operation audit report';

    public function handle(): int
    {
        $startDate = $this->option('start') ?? now()->subDays(30)->toDateString();
        $endDate = $this->option('end') ?? now()->toDateString();
        $operation = $this->option('operation');
        $jsonOutput = $this->option('json');

        $auditLogger = app(HsmAuditLogger::class);

        try {
            $report = $auditLogger->getAuditReport(
                new \DateTime($startDate),
                new \DateTime($endDate)
            );

            if ($operation) {
                $report['records'] = array_filter($report['records'], function ($record) use ($operation) {
                    return $record->operation === $operation;
                });
            }

            if ($jsonOutput) {
                $this->line(json_encode($report, JSON_PRETTY_PRINT));
                return self::SUCCESS;
            }

            $this->info('HSM Audit Report');
            $this->line('================');
            $this->line('Period: ' . $report['start_date'] . ' to ' . $report['end_date']);
            $this->line('Total Operations: ' . $report['total_operations']);
            $this->newLine();

            $this->info('Operations by Type:');
            foreach ($report['operations_by_type'] as $op => $count) {
                $this->line("  {$op}: {$count}");
            }
            $this->newLine();

            $this->info('Recent Operations:');
            $this->line('------------------');

            $limit = min(10, count($report['records']));
            for ($i = 0; $i < $limit; $i++) {
                $record = $report['records'][$i];
                $this->line(sprintf(
                    '[%s] %s - Key: %s - Object: %s:%s',
                    $record->created_at,
                    strtoupper($record->operation),
                    $record->key_id ?? 'N/A',
                    $record->object_type ?? 'N/A',
                    $record->object_id ?? 'N/A'
                ));
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to generate audit report: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
