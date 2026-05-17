<?php

namespace App\Console\Commands;

use App\Services\HsmAuditLogger;
use Illuminate\Console\Command;

/**
 * Get HSM audit log command
 * 
 * Retrieves audit logs for HSM operations.
 */
class GetHsmAuditLog extends Command
{
    protected $signature = 'hsm:audit-log
        {--start= : Start date (YYYY-MM-DD)}
        {--end= : End date (YYYY-MM-DD)}
        {--key-id= : Filter by key ID}
        {--operation= : Filter by operation}
        {--limit=10 : Number of records to show}';

    protected $description = 'Get HSM audit log records';

    public function handle(): int
    {
        $startDate = $this->option('start') ?? now()->subDays(7)->toDateString();
        $endDate = $this->option('end') ?? now()->toDateString();
        $keyId = $this->option('key-id');
        $operation = $this->option('operation');
        $limit = (int) $this->option('limit');

        $auditLogger = app(HsmAuditLogger::class);

        try {
            $report = $auditLogger->getAuditReport(
                new \DateTime($startDate),
                new \DateTime($endDate)
            );

            $records = $report['records'];

            if ($keyId) {
                $records = array_filter($records, function ($record) use ($keyId) {
                    return $record->key_id === $keyId;
                });
            }

            if ($operation) {
                $records = array_filter($records, function ($record) use ($operation) {
                    return $record->operation === $operation;
                });
            }

            $records = array_slice($records, 0, $limit);

            $this->info('HSM Audit Log');
            $this->line('=============');
            $this->line('Period: ' . $startDate . ' to ' . $endDate);
            $this->line('Total Records: ' . count($records));
            $this->newLine();

            if (empty($records)) {
                $this->warn('No audit records found.');
                return self::SUCCESS;
            }

            $this->info('Audit Records:');
            $this->line('--------------');

            foreach ($records as $record) {
                $this->line(sprintf(
                    '[%s] %s - Key: %s - Object: %s:%s - User: %s - IP: %s',
                    $record->created_at,
                    strtoupper($record->operation),
                    $record->key_id ?? 'N/A',
                    $record->object_type ?? 'N/A',
                    $record->object_id ?? 'N/A',
                    $record->user_id ?? 'N/A',
                    $record->ip_address ?? 'N/A'
                ));
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to get audit log: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
