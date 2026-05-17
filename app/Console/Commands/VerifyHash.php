<?php

namespace App\Console\Commands;

use App\Services\HsmService;
use Illuminate\Console\Command;

/**
 * Verify hash command
 * 
 * Verifies a signature for a hash using an HSM key.
 */
class VerifyHash extends Command
{
    protected $signature = 'hsm:verify
        {--hash= : Hash to verify}
        {--signature= : Base64-encoded signature}
        {--key-id= : Key ID to use for verification}';

    protected $description = 'Verify a signature for a hash using HSM key';

    public function handle(): int
    {
        $hash = $this->option('hash');
        $signature = $this->option('signature');
        $keyId = $this->option('key-id');

        if (!$hash) {
            $this->error('Specify --hash option.');
            return self::FAILURE;
        }

        if (!$signature) {
            $this->error('Specify --signature option.');
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

        $this->info('Verifying Signature with HSM');
        $this->line('============================');
        $this->line('Hash: ' . substr($hash, 0, 16) . '...');
        $this->line('Key ID: ' . $keyId);
        $this->newLine();

        $hsmService = app(HsmService::class);

        try {
            $verified = $hsmService->verify($hash, $signature, $keyId);

            if ($verified) {
                $this->info('Signature verification: PASSED');
                $this->line('The signature is valid for the given hash and key.');
                return self::SUCCESS;
            }

            $this->error('Signature verification: FAILED');
            $this->line('The signature is NOT valid for the given hash and key.');
            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('Failed to verify signature: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
