<?php

namespace App\Console\Commands;

use App\Services\HsmService;
use Illuminate\Console\Command;

/**
 * Get HSM status command
 * 
 * Retrieves the current status of the HSM.
 */
class GetHsmStatus extends Command
{
    protected $signature = 'hsm:status';

    protected $description = 'Get HSM status and information';

    public function handle(): int
    {
        $this->info('HSM Status');
        $this->line('==========');
        $this->newLine();

        $hsmService = app(HsmService::class);

        try {
            $status = $hsmService->getStatus();
            $slotInfo = $hsmService->getSlotInfo();

            $this->info('HSM Status:');
            $this->line('  Status: ' . strtoupper($status['status']));
            $this->line('  Last Check: ' . $status['lastCheck']);
            $this->line('  Uptime: ' . $status['uptime'] . ' seconds');
            $this->line('  Errors: ' . $status['errors']);
            $this->newLine();

            $this->info('Slot Information:');
            $this->line('  Model: ' . $slotInfo['model']);
            $this->line('  Firmware: ' . $slotInfo['firmwareVersion']);
            $this->line('  Total Slots: ' . $slotInfo['slotCount']);
            $this->line('  Available Slots: ' . $slotInfo['availableSlots']);
            $this->newLine();

            if ($status['status'] === 'online' && $status['errors'] === 0) {
                $this->info('HSM is healthy and operational.');
                return self::SUCCESS;
            }

            if ($status['status'] === 'offline') {
                $this->error('HSM is offline.');
                return self::FAILURE;
            }

            $this->warn('HSM is degraded.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to get HSM status: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
