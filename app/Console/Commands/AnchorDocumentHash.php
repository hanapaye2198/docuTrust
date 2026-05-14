<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Services\DocumentHashService;
use Illuminate\Console\Command;

/**
 * Anchor document hash command
 * 
 * Anchors a document hash to the blockchain.
 */
class AnchorDocumentHash extends Command
{
    protected $signature = 'document:anchor-hash
        {--document= : Document ID}
        {--hash= : Document hash to anchor}';

    protected $description = 'Anchor document hash to blockchain';

    public function handle(): int
    {
        $documentId = $this->option('document');
        $hash = $this->option('hash');

        if (!$documentId && !$hash) {
            $this->error('Specify --document or --hash option.');
            return self::FAILURE;
        }

        $hashService = app(DocumentHashService::class);

        if ($documentId) {
            $document = Document::find($documentId);

            if (!$document) {
                $this->error("Document with ID {$documentId} not found.");
                return self::FAILURE;
            }

            $this->info('Anchoring Document Hash to Blockchain');
            $this->line('====================================');
            $this->line('Document ID: ' . $document->id);
            $this->line('Document Title: ' . $document->title);
            $this->newLine();

            $documentHash = $hashService->createForCompletedDocument($document);

            if (!$documentHash) {
                $this->error('Failed to anchor hash.');
                return self::FAILURE;
            }

            $hash = $documentHash->hash;
            $transactionId = $documentHash->transaction_id;
        } else {
            $this->info('Anchoring Hash to Blockchain');
            $this->line('============================');
            $this->line('Hash: ' . $hash);
            $this->newLine();

            $transactionId = $hashService->anchorHashOnBlockchain($hash);

            if (!$transactionId) {
                $this->error('Failed to anchor hash.');
                return self::FAILURE;
            }
        }

        $this->info('Hash anchored successfully.');
        $this->line('Hash: ' . $hash);
        $this->line('Transaction ID: ' . $transactionId);

        return self::SUCCESS;
    }
}
