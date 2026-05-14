<?php

namespace App\Console\Commands;

use App\Services\HsmService;
use Illuminate\Console\Command;

/**
 * Generate key pair command
 * 
 * Generates a new RSA key pair in the HSM.
 */
class GenerateKeyPair extends Command
{
    protected $signature = 'hsm:generate-key
        {--bits=2048 : Key size in bits (2048 or 4096)}
        {--label= : Key label}';

    protected $description = 'Generate a new RSA key pair in HSM';

    public function handle(): int
    {
        $bits = (int) $this->option('bits');
        $label = $this->option('label') ?? 'docutrust_' . bin2hex(random_bytes(8));

        if ($bits < 2048) {
            $this->error('Key size must be at least 2048 bits.');
            return self::FAILURE;
        }

        $this->info('Generating RSA Key Pair in HSM');
        $this->line('===============================');
        $this->line('Key Size: ' . $bits . ' bits');
        $this->line('Label: ' . $label);
        $this->newLine();

        $hsmService = app(HsmService::class);

        try {
            $keyPair = $hsmService->generateRsaKeyPair($bits);

            $this->info('Key pair generated successfully.');
            $this->newLine();

            $this->info('Key Details:');
            $this->line('  Private Key ID: ' . $keyPair['privateKeyId']);
            $this->line('  Public Key Fingerprint: ' . $keyPair['fingerprint']);
            $this->line('  Key Size: ' . $bits . ' bits');
            $this->newLine();

            $this->info('Public Key:');
            $this->line($keyPair['publicKey']);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to generate key pair: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
