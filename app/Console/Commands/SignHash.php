<?php

namespace App\Console\Commands;

use App\Services\HsmService;
use Illuminate\Console\Command;

/**
 * Sign hash command
 * 
 * Signs a hash using an HSM key.
 */
class SignHash extends Command
{
    protected $signature = 'hsm:sign
        {--hash= : Hash to sign (64 hex characters)}
        {--key-id= : Key ID to use for signing}';

    protected $description = 'Sign a hash using HSM key';

    public function handle(): int
    {
        $hash = $this->option('hash');
        $keyId = $this->option('key-id');

        if (!$hash) {
            $this->error('Specify --hash option.');
            return self::FAILURE;
        }

        if (!$keyId) {
            $this->error('Specify --key-id option.');
            return self::FAILURE;
        }

        // Validate hash format
        if (!preg_match('/^[a-f0-9]{64}$/i', $hash)) {
            $this->error('Invalid hash format. Must be 64 hex characters.');
            return self::FAILURE;
        }

        $this->info('Signing Hash with HSM');
        $this->line('====================');
        $this->line('Hash: ' . substr($hash, 0, 16) . '...');
        $this->line('Key ID: ' . $keyId);
        $this->newLine();

        $hsmService = app(HsmService::class);

        try {
            $signature = $hsmService->sign($hash, $keyId);

            $this->info('Signature generated successfully.');
            $this->newLine();

            $this->info('Signature (Base64):');
            $this->line($signature);
            $this->newLine();

            $this->info('Signature (Hex):');
            $this->line(bin2hex(base64_decode($signature)));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to sign hash: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
