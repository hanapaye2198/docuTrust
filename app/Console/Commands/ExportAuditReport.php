<?php

namespace App\Console\Commands;

use App\Services\HsmAuditLogger;
use Illuminate\Console\Command;

/**
 * Export audit report command
 * 
 * Exports HSM audit logs to a file.
 */
class ExportAuditReport extends Command
{
    protected $signature = 'hsm:export-audit
        {--start= : Start date (YYYY-MM-DD)}
        {--end= : End date (YYYY-MM-DD)}
        {--format=csv : Output format (csv or json)}
        {--output= : Output file path}';

    protected $description = 'Export HSM audit log to file';

    public function handle(): int
    {
        $startDate = $this->option('start') ?? now()->subDays(30)->toDateString();
        $endDate = $this->option('end') ?? now()->toDateString();
        $format = $this->option('format') ?? 'csv';
        $output = $this->option('output');

        if (!$output) {
            $output = storage_path('app/audit/hsm-audit-' . now()->format('Y-m-d') . '.' . $format);
        }

        $auditLogger = app(HsmAuditLogger::class);

        try {
            $report = $auditLogger->getAuditReport(
                new \DateTime($startDate),
                new \DateTime($endDate)
            );

            // Create output directory if needed
            $outputDir = dirname($output);
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            if ($format === 'json') {
                file_put_contents($output, json_encode($report, JSON_PRETTY_PRINT));
            } else {
                // CSV format
                $csv = $this->convertToCsv($report['records']);
                file_put_contents($output, $csv);
            }

            $this->info('Audit report exported to: ' . $output);
            $this->line('Period: ' . $startDate . ' to ' . $endDate);
            $this->line('Total Records: ' . $report['total_operations']);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to export audit report: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function convertToCsv(array $records): string
    {
        $csv = "timestamp,operation,key_id,object_type,object_id,user_id,ip_address,details\n";

        foreach ($records as $record) {
            $csv .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s","%s"\n',
                $record->created_at,
                $record->operation,
                $record->key_id ?? '',
                $record->object_type ?? '',
                $record->object_id ?? '',
                $record->user_id ?? '',
                $record->ip_address ?? '',
                str_replace('"', '""', $record->details ?? '')
            );
        }

        return $csv;
    }
}
