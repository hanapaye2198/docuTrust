<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Services\DocumentHashService;
use Illuminate\Console\Command;

/**
 * Generate document hash command
 * 
 * Generates and stores the hash for a document.
 */
class GenerateDocumentHash extends Command
{
    protected $signature = 'document:hash
        {--document= : Document ID}
        {--file= : File path to hash}
        {--store : Store hash in database}';

    protected $description = 'Generate hash for a document';

    public function handle(): int
    {
        $documentId = $this->option('document');
        $filePath = $this->option('file');
        $store = $this->option('store');

        if (!$documentId && !$filePath) {
            $this->error('Specify --document or --file option.');
            return self::FAILURE;
        }

        $hashService = app(DocumentHashService::class);

        if ($filePath) {
            // Hash file directly
            $hash = $hashService->generateDocumentHash($filePath);

            $this->info('Document Hash');
            $this->line('=============');
            $this->line('File: ' . $filePath);
            $this->line('Hash (SHA-256): ' . $hash);
            $this->newLine();

            if ($store) {
                $this->warn('Storing file hashes is not supported. Use --document for document hashes.');
            }

            return self::SUCCESS;
        }

        // Hash document
        $document = Document::find($documentId);

        if (!$document) {
            $this->error("Document with ID {$documentId} not found.");
            return self::FAILURE;
        }

        $hash = $hashService->generateDocumentHash($document->verifiablePdfPath() ?? $document->sourcePdfPath());

        $this->info('Document Hash');
        $this->line('=============');
        $this->line('Document ID: ' . $document->id);
        $this->line('Document Title: ' . $document->title);
        $this->line('Hash (SHA-256): ' . $hash);
        $this->newLine();

        if ($store) {
            $documentHash = $hashService->createForCompletedDocument($document);

            if ($documentHash) {
                $this->info('Hash stored in database.');
                $this->line('Transaction ID: ' . $documentHash->transaction_id);
            } else {
                $this->error('Failed to store hash in database.');
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
