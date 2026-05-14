<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Check all command
 * 
 * Runs all compliance and system checks.
 */
class CheckAll extends Command
{
    protected $signature = 'check:all';

    protected $description = 'Run all compliance and system checks';

    public function handle(): int
    {
        $this->info('========================================');
        $this->info('Comprehensive System Check');
        $this->info('========================================');
        $this->newLine();

        $checks = [
            'CSC Compliance' => 'csc:check',
            'HSM All' => 'hsm:check-all',
            'Certificate Expiry' => 'certificate:check-expiry',
            'Certificate Revocation' => 'certificate:check-revocation',
            'Certificate Validity' => 'certificate:check-validity',
        ];

        $passed = 0;
        $failed = 0;

        foreach ($checks as $name => $command) {
            $this->info("Checking {$name}...");
            $this->line(str_repeat('-', 40));

            $exitCode = $this->call($command);

            if ($exitCode === self::SUCCESS) {
                $this->info("  [PASS] {$name}");
                $passed++;
            } else {
                $this->error("  [FAIL] {$name}");
                $failed++;
            }

            $this->newLine();
        }

        $this->info('========================================');
        $this->info('Summary');
        $this->info('========================================');
        $this->line('Passed: ' . $passed . '/' . count($checks));
        $this->line('Failed: ' . $failed . '/' . count($checks));
        $this->newLine();

        if ($failed === 0) {
            $this->info('All checks passed.');
            return self::SUCCESS;
        }

        $this->error('Some checks failed.');
        return self::FAILURE;
    }
}
