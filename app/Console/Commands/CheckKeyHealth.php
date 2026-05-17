<?php

namespace App\Console\Commands;

use App\Models\DocumentSigner;
use App\Services\HsmKeyManager;
use Illuminate\Console\Command;

/**
 * Check key health command
 * 
 * Checks the health of HSM keys for signers.
 */
class CheckKeyHealth extends Command
{
    protected $signature = 'pki:key-health
        {--signer= : Specific signer ID}
        {--all : Check all signers}';

    protected $description = 'Check HSM key health for signers';

    public function handle(): int
    {
        $signerId = $this->option('signer');
        $checkAll = $this->option('all');

        if ($signerId) {
            $this->checkSingleSigner($signerId);
        } elseif ($checkAll) {
            $this->checkAllSigners();
        } else {
            $this->error('Specify --signer or --all option.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function checkSingleSigner(string $signerId): void
    {
        $signer = DocumentSigner::find($signerId);

        if (!$signer) {
            $this->error("Signer with ID {$signerId} not found.");
            return;
        }

        $this->info('Checking Key Health for Signer');
        $this->line('==============================');
        $this->line('Signer: ' . $signer->name);
        $this->line('HSM Key ID: ' . ($signer->hsm_key_id ?? 'Not assigned'));
        $this->newLine();

        $hsmKeyManager = app(HsmKeyManager::class);

        if (!$signer->hsm_key_id) {
            $this->warn('No HSM key assigned to this signer.');
            return;
        }

        try {
            $health = $hsmKeyManager->checkHealth();

            $this->info('Key Health Status: ' . strtoupper($health['status']));
            $this->line('Message: ' . $health['message']);
        } catch (\Throwable $e) {
            $this->error('Failed to check key health: ' . $e->getMessage());
        }
    }

    private function checkAllSigners(): void
    {
        $signers = DocumentSigner::where('status', '!=', 'revoked')->get();

        $this->info("Checking key health for {$signers->count()} signers.");
        $this->newLine();

        $healthy = 0;
        $degraded = 0;
        $unhealthy = 0;

        foreach ($signers as $signer) {
            $this->line("Checking: {$signer->name}...");

            if (!$signer->hsm_key_id) {
                $this->warn('  No HSM key assigned');
                $degraded++;
                continue;
            }

            try {
                $hsmKeyManager = app(HsmKeyManager::class);
                $health = $hsmKeyManager->checkHealth();

                if ($health['status'] === 'healthy') {
                    $this->info('  Healthy');
                    $healthy++;
                } elseif ($health['status'] === 'degraded') {
                    $this->warn('  Degraded: ' . $health['message']);
                    $degraded++;
                } else {
                    $this->error('  Unhealthy: ' . $health['message']);
                    $unhealthy++;
                }
            } catch (\Throwable $e) {
                $this->error('  Error: ' . $e->getMessage());
                $unhealthy++;
            }
        }

        $this->newLine();
        $this->info('Summary:');
        $this->line('  Healthy: ' . $healthy);
        $this->line('  Degraded: ' . $degraded);
        $this->line('  Unhealthy: ' . $unhealthy);
    }
}
