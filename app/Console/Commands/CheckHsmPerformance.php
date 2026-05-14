<?php

namespace App\Console\Commands;

use App\Services\HsmService;
use Illuminate\Console\Command;

/**
 * Check HSM performance command
 * 
 * Checks HSM performance metrics.
 */
class CheckHsmPerformance extends Command
{
    protected $signature = 'hsm:performance';

    protected $description = 'Check HSM performance metrics';

    public function handle(): int
    {
        $this->info('HSM Performance Check');
        $this->line('====================');
        $this->newLine();

        $hsmService = app(HsmService::class);

        try {
            $status = $hsmService->getStatus();

            $this->info('Performance Metrics:');
            $this->line('  Status: ' . strtoupper($status['status']));
            $this->line('  Uptime: ' . $this->formatUptime($status['uptime']));
            $this->line('  Errors: ' . $status['errors']);
            $this->newLine();

            if ($status['status'] === 'online' && $status['errors'] === 0) {
                $this->info('HSM performance is healthy.');
                return self::SUCCESS;
            }

            if ($status['errors'] > 0) {
                $this->warn('HSM has encountered ' . $status['errors'] . ' error(s).');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to check HSM performance: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function formatUptime(int $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        return sprintf('%d days, %d hours, %d minutes, %d seconds', $days, $hours, $minutes, $seconds);
    }
}
