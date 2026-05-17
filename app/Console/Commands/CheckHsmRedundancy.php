<?php

namespace App\Console\Commands;

use App\Services\HsmService;
use Illuminate\Console\Command;

/**
 * Check HSM redundancy command
 * 
 * Checks HSM redundancy configuration.
 */
class CheckHsmRedundancy extends Command
{
    protected $signature = 'hsm:redundancy';

    protected $description = 'Check HSM redundancy configuration';

    public function handle(): int
    {
        $this->info('HSM Redundancy Check');
        $this->line('====================');
        $this->newLine();

        $hsmService = app(HsmService::class);

        try {
            $slotInfo = $hsmService->getSlotInfo();
            $status = $hsmService->getStatus();

            $this->info('Slot Information:');
            $this->line('  Total Slots: ' . $slotInfo['slotCount']);
            $this->line('  Available Slots: ' . $slotInfo['availableSlots']);
            $this->line('  Model: ' . $slotInfo['model']);
            $this->line('  Firmware: ' . $slotInfo['firmwareVersion']);
            $this->newLine();

            $this->info('Redundancy Status:');
            if ($slotInfo['availableSlots'] > 0) {
                $this->info('  HSM has redundant capacity.');
                $this->line('  Failover capable: Yes');
            } else {
                $this->warn('  HSM has no redundant capacity.');
                $this->line('  Failover capable: No');
            }

            $this->newLine();

            if ($status['status'] === 'online') {
                $this->info('HSM is online and redundancy is operational.');
                return self::SUCCESS;
            }

            $this->error('HSM is not online. Redundancy may be compromised.');
            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('Failed to check HSM redundancy: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
