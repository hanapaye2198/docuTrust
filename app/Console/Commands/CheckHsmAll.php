<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Check HSM all command
 * 
 * Runs all HSM checks and provides a comprehensive report.
 */
class CheckHsmAll extends Command
{
    protected $signature = 'hsm:check-all';

    protected $description = 'Run all HSM checks';

    public function handle(): int
    {
        $this->info('========================================');
        $this->info('HSM Comprehensive Check');
        $this->info('========================================');
        $this->newLine();

        $checks = [
            'Status' => 'hsm:status',
            'Health' => 'hsm:health',
            'Redundancy' => 'hsm:redundancy',
            'Performance' => 'hsm:performance',
            'Security' => 'hsm:security',
            'Compliance' => 'hsm:compliance',
            'Audit' => 'hsm:audit-check',
            'Network' => 'hsm:network',
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
            $this->info('All HSM checks passed.');
            return self::SUCCESS;
        }

        $this->error('Some HSM checks failed.');
        return self::FAILURE;
    }
}
