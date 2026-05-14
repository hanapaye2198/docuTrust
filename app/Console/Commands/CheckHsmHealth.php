<?php

namespace App\Console\Commands;

use App\Services\HsmHealthMonitor;
use Illuminate\Console\Command;

/**
 * Check HSM health command
 * 
 * Checks HSM health status and reports issues.
 */
class CheckHsmHealth extends Command
{
    protected $signature = 'hsm:health
        {--json : Output in JSON format}';

    protected $description = 'Check HSM health status';

    public function handle(): int
    {
        $jsonOutput = $this->option('json');

        $healthMonitor = app(HsmHealthMonitor::class);
        $health = $healthMonitor->check();

        if ($jsonOutput) {
            $this->line(json_encode($health, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->info('HSM Health Check');
        $this->line('================');
        $this->line('Status: ' . strtoupper($health['status']));
        $this->line('Timestamp: ' . $health['timestamp']);
        $this->newLine();

        $this->info('HSM Details:');
        $this->line('  Status: ' . $health['details']['hsm_status']['status']);
        $this->line('  Uptime: ' . $health['details']['hsm_status']['uptime'] . ' seconds');
        $this->line('  Errors: ' . $health['details']['hsm_status']['errors']);
        $this->newLine();

        $this->info('Slot Information:');
        $this->line('  Total Slots: ' . $health['details']['slot_info']['slotCount']);
        $this->line('  Available Slots: ' . $health['details']['slot_info']['availableSlots']);
        $this->line('  Model: ' . $health['details']['slot_info']['model']);
        $this->line('  Firmware: ' . $health['details']['slot_info']['firmwareVersion']);
        $this->newLine();

        if ($health['status'] === 'unhealthy') {
            $this->error('HSM is not operational. Check logs for details.');
            return self::FAILURE;
        }

        if ($health['status'] === 'degraded') {
            $this->warn('HSM is degraded. Some operations may be affected.');
            return self::SUCCESS;
        }

        $this->info('HSM is healthy and operational.');
        return self::SUCCESS;
    }
}
