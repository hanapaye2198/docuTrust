<?php

namespace App\Console\Commands;

use App\Services\HsmHealthMonitor;
use App\Services\HsmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Check CSC compliance command
 * 
 * Checks if the system meets CSC compliance requirements.
 */
class CheckCscCompliance extends Command
{
    protected $signature = 'csc:check';

    protected $description = 'Check CSC compliance status';

    public function handle(): int
    {
        $this->info('CSC Compliance Check');
        $this->line('====================');
        $this->newLine();

        $issues = [];
        $warnings = [];
        $passes = [];

        // Check 1: HSM Backend
        $this->info('1. HSM Backend Configuration');
        $hsmBackend = config('hsm.backend', 'mock');
        if ($hsmBackend === 'mock') {
            $warnings[] = 'HSM backend is set to "mock". Use "thales", "aws-cloudhsm", or "utimaco" for production.';
            $this->warn('  [WARNING] Using mock HSM - not suitable for production');
        } else {
            $passes[] = 'HSM backend configured: ' . $hsmBackend;
            $this->info('  [PASS] HSM backend configured: ' . $hsmBackend);
        }

        // Check 2: Key Size
        $this->info('2. Key Size Configuration');
        $keySize = (int) config('docutrust.pki.key_size', 2048);
        if ($keySize < 2048) {
            $issues[] = 'Key size is less than 2048 bits. CSC requires minimum 2048 bits.';
            $this->error('  [FAIL] Key size is ' . $keySize . ' bits (minimum 2048 required)');
        } else {
            $passes[] = 'Key size is ' . $keySize . ' bits';
            $this->info('  [PASS] Key size is ' . $keySize . ' bits');
        }

        // Check 3: Root CA
        $this->info('3. Root CA Configuration');
        $rootCa = DB::table('certificate_authorities')
            ->where('is_root', true)
            ->where('status', 'active')
            ->first();

        if (!$rootCa) {
            $issues[] = 'Root CA not found in database.';
            $this->error('  [FAIL] Root CA not found');
        } else {
            $passes[] = 'Root CA configured';
            $this->info('  [PASS] Root CA configured');
        }

        // Check 4: HSM Health
        $this->info('4. HSM Health Status');
        try {
            $hsmService = app(HsmService::class);
            $status = $hsmService->getStatus();

            if ($status['status'] !== 'online') {
                $issues[] = 'HSM is not online. Status: ' . $status['status'];
                $this->error('  [FAIL] HSM status: ' . $status['status']);
            } else {
                $passes[] = 'HSM is online';
                $this->info('  [PASS] HSM is online');
            }
        } catch (\Throwable $e) {
            $warnings[] = 'Could not check HSM health: ' . $e->getMessage();
            $this->warn('  [WARNING] Could not check HSM health');
        }

        // Check 5: Audit Logging
        $this->info('5. Audit Logging');
        $auditTableExists = DB::getSchemaBuilder()->hasTable('hsm_key_audit_log');
        if (!$auditTableExists) {
            $issues[] = 'HSM audit log table does not exist.';
            $this->error('  [FAIL] Audit log table does not exist');
        } else {
            $passes[] = 'Audit log table exists';
            $this->info('  [PASS] Audit log table exists');
        }

        // Check 6: HSM Key ID on Signers
        $this->info('6. HSM Key Assignment');
        $signersWithoutHsmKey = DB::table('document_signers')
            ->whereNull('hsm_key_id')
            ->where('status', '!=', 'revoked')
            ->count();

        if ($signersWithoutHsmKey > 0) {
            $warnings[] = $signersWithoutHsmKey . ' signers do not have HSM keys assigned.';
            $this->warn('  [WARNING] ' . $signersWithoutHsmKey . ' signers without HSM keys');
        } else {
            $passes[] = 'All signers have HSM keys';
            $this->info('  [PASS] All signers have HSM keys');
        }

        // Summary
        $this->newLine();
        $this->info('Summary');
        $this->line('-------');
        $this->line('Passed: ' . count($passes));
        $this->line('Warnings: ' . count($warnings));
        $this->line('Issues: ' . count($issues));
        $this->newLine();

        if (count($issues) > 0) {
            $this->error('CSC Compliance: FAILED');
            $this->newLine();
            $this->info('Issues:');
            foreach ($issues as $issue) {
                $this->line('  - ' . $issue);
            }
            return self::FAILURE;
        }

        if (count($warnings) > 0) {
            $this->warn('CSC Compliance: WARNING');
            $this->newLine();
            $this->info('Warnings:');
            foreach ($warnings as $warning) {
                $this->line('  - ' . $warning);
            }
            return self::SUCCESS;
        }

        $this->info('CSC Compliance: PASSED');
        return self::SUCCESS;
    }
}
