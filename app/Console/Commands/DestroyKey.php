<?php

namespace App\Console\Commands;

use App\Services\HsmService;
use Illuminate\Console\Command;

/**
 * Destroy key command
 * 
 * Destroys a key in the HSM.
 */
class DestroyKey extends Command
{
    protected $signature = 'hsm:destroy-key
        {--key-id= : Key ID to destroy}';

    protected $description = 'Destroy a key in HSM';

    public function handle(): int
    {
        $keyId = $this->option('key-id');

        if (!$keyId) {
            $this->error('Specify --key-id option.');
            return self::FAILURE;
        }

        $this->info('Destroying Key in HSM');
        $this->line('====================');
        $this->line('Key ID: ' . $keyId);
        $this->newLine();

        $hsmService = app(HsmService::class);

        try {
            $destroyed = $hsmService->destroyKey($keyId);

            if ($destroyed) {
                $this->info('Key destroyed successfully.');
                return self::SUCCESS;
            }

            $this->error('Failed to destroy key. Key may not exist.');
            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('Failed to destroy key: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
