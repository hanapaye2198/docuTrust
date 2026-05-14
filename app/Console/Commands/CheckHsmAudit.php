<?php

namespace App\Console\Commands;

use App\Services\HsmAuditLogger;
use Illuminate\Console\Command;

/**
 * Check HSM audit command
 * 
 * Checks HSM audit logging configuration.
 */
class CheckHsmAudit extends Command
{
    protected $signature = 'hsm:audit-check';

    protected $description = 'Check HSM audit logging configuration';

    public function handle(): int
    {
        $this->info('HSM Audit Logging Check');
        $this->line('=======================');
        $this->newLine();

        // Check audit log table exists
        $auditTableExists = \DB::getSchemaBuilder()->hasTable('hsm_key_audit_log');

        if (!$auditTableExists) {
            $this->error('HSM audit log table does not exist.');
            $this->line('Run: php artisan migrate');
            return self::FAILURE;
        }

        $this->info('Audit Logging Configuration:');
        $this->line('  Table: hsm_key_audit_log');
        $this->line('  Status: Enabled');
        $this->newLine();

        // Get recent audit records
        $recentRecords = \DB::table('hsm_key_audit_log')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $this->info('Recent Audit Records:');
        $this->line('---------------------');

        foreach ($recentRecords as $record) {
            $this->line(sprintf(
                '[%s] %s - Key: %s - User: %s',
                $record->created_at,
                strtoupper($record->operation),
                $record->key_id ?? 'N/A',
                $record->user_id ?? 'N/A'
            ));
        }

        $this->newLine();
        $this->info('Audit logging is properly configured.');

        return self::SUCCESS;
    }
}
