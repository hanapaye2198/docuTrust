<?php

namespace App\Console\Commands;

use App\Models\DocumentSigner;
use App\Services\HsmKeyManager;
use Illuminate\Console\Command;

/**
 * Rotate keys command
 * 
 * Rotates signing keys for document signers as part of key management.
 */
class RotateKeys extends Command
{
    protected $signature = 'pki:rotate-keys
        {--signer= : Specific signer ID to rotate}
        {--all : Rotate all signer keys}
        {--dry-run : Show what would be rotated}';

    protected $description = 'Rotate PKI signing keys for signers';

    public function handle(): int
    {
        $signerId = $this->option('signer');
        $rotateAll = $this->option('all');
        $dryRun = $this->option('dry-run');

        $hsmKeyManager = app(HsmKeyManager::class);

        if ($signerId) {
            $this->rotateSingleSigner($signerId, $dryRun, $hsmKeyManager);
        } elseif ($rotateAll) {
            $this->rotateAllSigners($dryRun, $hsmKeyManager);
        } else {
            $this->error('Specify --signer or --all option.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function rotateSingleSigner(string $signerId, bool $dryRun, HsmKeyManager $hsmKeyManager): void
    {
        $signer = DocumentSigner::find($signerId);

        if (!$signer) {
            $this->error("Signer with ID {$signerId} not found.");
            return;
        }

        $this->info("Rotating key for signer: {$signer->name} ({$signer->email})");

        if ($dryRun) {
            $this->warn('Dry run mode - no changes will be made.');
            $this->line('Would generate new key pair in HSM.');
            $this->line('Would update signer record with new key ID.');
            return;
        }

        try {
            $keyPair = $hsmKeyManager->generateKeyPairForSigner($signer);

            $this->info('New key pair generated:');
            $this->line('  Key ID: ' . $signer->hsm_key_id);
            $this->line('  Fingerprint: ' . substr($signer->public_key_fingerprint, 0, 16) . '...');

            $this->info('Key rotation complete.');
        } catch (\Throwable $e) {
            $this->error('Failed to rotate key: ' . $e->getMessage());
        }
    }

    private function rotateAllSigners(bool $dryRun, HsmKeyManager $hsmKeyManager): void
    {
        $signers = DocumentSigner::where('status', '!=', 'revoked')->get();

        $this->info("Found {$signers->count()} signers to process.");

        foreach ($signers as $signer) {
            $this->line("Processing: {$signer->name}...");
            $this->rotateSingleSigner((string) $signer->id, $dryRun, $hsmKeyManager);
        }

        $this->info('Key rotation batch complete.');
    }
}
