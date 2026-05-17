<?php

namespace App\Console\Commands;

use App\Models\DocumentSigner;
use App\Services\HsmSignerSealProvider;
use Illuminate\Console\Command;

/**
 * Update signer seal provider command
 * 
 * Updates the signer seal provider to use HSM for existing signers.
 */
class UpdateSignerSealProvider extends Command
{
    protected $signature = 'pki:update-seal-provider
        {--signer= : Specific signer ID}
        {--all : Update all signers}
        {--dry-run : Show what would be updated}';

    protected $description = 'Update signer seal provider to use HSM';

    public function handle(): int
    {
        $signerId = $this->option('signer');
        $updateAll = $this->option('all');
        $dryRun = $this->option('dry-run');

        if ($signerId) {
            $this->updateSingleSigner($signerId, $dryRun);
        } elseif ($updateAll) {
            $this->updateAllSigners($dryRun);
        } else {
            $this->error('Specify --signer or --all option.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function updateSingleSigner(string $signerId, bool $dryRun): void
    {
        $signer = DocumentSigner::find($signerId);

        if (!$signer) {
            $this->error("Signer with ID {$signerId} not found.");
            return;
        }

        $this->info("Updating seal provider for signer: {$signer->name}");

        if ($dryRun) {
            $this->warn('Dry run mode - no changes will be made.');
            $this->line('Would update signing_provider to "hsm_managed".');
            return;
        }

        $signer->update([
            'signing_provider' => 'hsm_managed',
        ]);

        $this->info('Seal provider updated to "hsm_managed".');
    }

    private function updateAllSigners(bool $dryRun): void
    {
        $signers = DocumentSigner::where('status', '!=', 'revoked')->get();

        $this->info("Found {$signers->count()} signers to update.");

        foreach ($signers as $signer) {
            $this->line("Updating: {$signer->name}...");
            $this->updateSingleSigner((string) $signer->id, $dryRun);
        }

        $this->info('Seal provider update complete.');
    }
}
