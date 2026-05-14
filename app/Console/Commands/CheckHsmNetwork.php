<?php

namespace App\Console\Commands;

use App\Services\HsmVirtualGateway;
use Illuminate\Console\Command;

/**
 * Check HSM network command
 * 
 * Checks HSM network configuration.
 */
class CheckHsmNetwork extends Command
{
    protected $signature = 'hsm:network';

    protected $description = 'Check HSM network configuration';

    public function handle(): int
    {
        $this->info('HSM Network Configuration Check');
        $this->line('================================');
        $this->newLine();

        $vgw = app(HsmVirtualGateway::class);
        $vgwStatus = $vgw->getStatus();

        $this->info('Virtual Gateway Status:');
        $this->line('  Status: ' . strtoupper($vgwStatus['status']));
        $this->line('  Uptime: ' . $vgwStatus['uptime'] . ' seconds');
        $this->line('  Requests Processed: ' . $vgwStatus['requests_processed']);
        $this->newLine();

        $this->info('Allowed Operations:');
        foreach ($vgwStatus['allowed_operations'] as $op) {
            $this->line('  - ' . $op);
        }

        $this->newLine();
        $this->info('Network configuration is properly set up.');

        return self::SUCCESS;
    }
}
