<?php

namespace App\Console\Commands;

use App\Services\HsmService;
use Illuminate\Console\Command;

/**
 * Get HSM slot info command
 * 
 * Retrieves slot information from the HSM.
 */
class GetHsmSlotInfo extends Command
{
    protected $signature = 'hsm:slot-info';

    protected $description = 'Get HSM slot information';

    public function handle(): int
    {
        $this->info('HSM Slot Information');
        $this->line('====================');
        $this->newLine();

        $hsmService = app(HsmService::class);

        try {
            $slotInfo = $hsmService->getSlotInfo();

            $this->info('Slot Details:');
            $this->line('  Model: ' . $slotInfo['model']);
            $this->line('  Firmware: ' . $slotInfo['firmwareVersion']);
            $this->line('  Total Slots: ' . $slotInfo['slotCount']);
            $this->line('  Available Slots: ' . $slotInfo['availableSlots']);
            $this->newLine();

            $this->info('Redundancy:');
            if ($slotInfo['availableSlots'] > 0) {
                $this->info('  HSM has redundant capacity.');
            } else {
                $this->warn('  HSM has no redundant capacity.');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to get HSM slot info: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
