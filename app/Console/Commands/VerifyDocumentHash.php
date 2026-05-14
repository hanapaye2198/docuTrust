<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\DocumentHash;
use App\Services\DocumentHashService;
use Illuminate\Console\Command;

/**
 * Verify document hash command
 * 
 * Verifies a document hash against the blockchain.
 */
class VerifyDocumentHash extends Command
{
    protected $signature = 'document:verify-hash
        {--document= : Document ID}
        {--hash= : Document hash}
        {--transaction= : Transaction ID}';

    protected $description = 'Verify document hash against blockchain';

    public function handle(): int
    {
        $documentId = $this->option('document');
        $hash = $this->option('hash');
        $transactionId = $this->option('transaction');

        if (!$documentId && !$hash) {
            $this->error('Specify --document or --hash option.');
            return self::FAILURE;
        }

        $documentHash = null;

        if ($documentId) {
            $document = Document::find($documentId);

            if (!$document) {
                $this->error("Document with ID {$documentId} not found.");
                return self::FAILURE;
            }

            $documentHash = DocumentHash::where('document_id', $documentId)->first();

            if (!$documentHash) {
                $this->error('No hash found for this document.');
                return self::FAILURE;
            }

            $hash = $documentHash->hash;
            $transactionId = $documentHash->transaction_id;
        }

        if (!$hash) {
            $this->error('Hash not found.');
            return self::FAILURE;
        }

        $this->info('Document Hash Verification');
        $this->line('========================');
        $this->line('Hash: ' . $hash);
        $this->line('Transaction ID: ' . $transactionId);
        $this->newLine();

        $hashService = app(DocumentHashService::class);

        try {
            $result = $hashService->verifyStoredProof($documentHash);

            $this->info('Verification Result:');
            $this->line('  Status: ' . strtoupper($result['status']));
            $this->line('  Anchored: ' . ($result['anchored'] ? 'Yes' : 'No'));
            $this->line('  Transaction Matches: ' . ($result['transaction_matches'] ? 'Yes' : 'No'));
            $this->line('  Block Number: ' . ($result['block_number'] ?? 'N/A'));
            $this->line('  Anchored At: ' . ($result['anchored_at'] ?? 'N/A'));
            $this->line('  Submitted By: ' . ($result['submitted_by'] ?? 'N/A'));
            $this->newLine();

            $this->info('Message: ' . $result['message']);

            if ($result['status'] === 'verified') {
                return self::SUCCESS;
            }

            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('Failed to verify hash: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
