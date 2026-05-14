<?php

namespace App\Console\Commands;

use App\Services\HsmService;
use Illuminate\Console\Command;

/**
 * Get public key command
 * 
 * Retrieves the public key from an HSM key.
 */
class GetPublicKey extends Command
{
    protected $signature = 'hsm:get-public-key
        {--key-id= : Key ID to retrieve}';

    protected $description = 'Get public key from HSM key';

    public function handle(): int
    {
        $keyId = $this->option('key-id');

        if (!$keyId) {
            $this->error('Specify --key-id option.');
            return self::FAILURE;
        }

        $this->info('Retrieving Public Key from HSM');
        $this->line('==============================');
        $this->line('Key ID: ' . $keyId);
        $this->newLine();

        $hsmService = app(HsmService::class);

        try {
            $publicKey = $hsmService->getPublicKey($keyId);

            $this->info('Public key retrieved successfully.');
            $this->newLine();

            $this->info('Public Key (PEM):');
            $this->line($publicKey);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to retrieve public key: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
