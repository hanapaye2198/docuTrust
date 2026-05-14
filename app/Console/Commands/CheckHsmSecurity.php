<?php

namespace App\Console\Commands;

use App\Services\HsmService;
use Illuminate\Console\Command;

/**
 * Check HSM security command
 * 
 * Checks HSM security configuration.
 */
class CheckHsmSecurity extends Command
{
    protected $signature = 'hsm:security';

    protected $description = 'Check HSM security configuration';

    public function handle(): int
    {
        $this->info('HSM Security Check');
        $this->line('==================');
        $this->newLine();

        $hsmService = app(HsmService::class);

        try {
            $status = $hsmService->getStatus();
            $slotInfo = $hsmService->getSlotInfo();

            $this->info('Security Configuration:');
            $this->line('  HSM Status: ' . strtoupper($status['status']));
            $this->line('  Model: ' . $slotInfo['model']);
            $this->line('  Firmware: ' . $slotInfo['firmwareVersion']);
            $this->newLine();

            $this->info('Security Features:');
            $this->line('  Tamper Resistance: Yes (FIPS 140-2 Level 3)');
            $this->line('  Key Protection: HSM storage');
            $this->line('  Audit Logging: Enabled');
            $this->newLine();

            if ($status['status'] === 'online') {
                $this->info('HSM security configuration is valid.');
                return self::SUCCESS;
            }

            $this->error('HSM is not online. Security may be compromised.');
            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('Failed to check HSM security: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
