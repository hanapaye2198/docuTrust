<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\DocumentHash;
use App\Services\CertificateVerificationService;
use Illuminate\Console\Command;

/**
 * Verify document signature command
 * 
 * Verifies the digital signatures on a document.
 */
class VerifySignature extends Command
{
    protected $signature = 'signature:verify
        {--document= : Document ID}
        {--hash= : Document hash}
        {--verbose : Show detailed output}';

    protected $description = 'Verify document digital signatures';

    public function handle(): int
    {
        $documentId = $this->option('document');
        $hash = $this->option('hash');
        $verbose = $this->option('verbose');

        if (!$documentId && !$hash) {
            $this->error('Specify --document or --hash option.');
            return self::FAILURE;
        }

        $document = null;
        $documentHash = null;

        if ($documentId) {
            $document = Document::find($documentId);

            if (!$document) {
                $this->error("Document with ID {$documentId} not found.");
                return self::FAILURE;
            }

            $documentHash = DocumentHash::where('document_id', $documentId)->first();
        } else {
            $documentHash = DocumentHash::where('hash', $hash)->first();

            if (!$documentHash) {
                $this->error("Document hash not found.");
                return self::FAILURE;
            }

            $document = $documentHash->document;
        }

        if (!$document) {
            $this->error('Document not found for hash.');
            return self::FAILURE;
        }

        $this->info('Verifying Document Signature');
        $this->line('============================');
        $this->line('Document ID: ' . $document->id);
        $this->line('Document Title: ' . $document->title);
        $this->line('Document Hash: ' . $documentHash->hash);
        $this->line('Transaction ID: ' . $documentHash->transaction_id);
        $this->newLine();

        $verificationService = app(CertificateVerificationService::class);
        $result = $verificationService->verifyDocumentSignatures($document, $documentHash->hash);

        if ($verbose) {
            $this->info('Verification Result:');
            $this->line('  Status: ' . strtoupper($result['status']));
            $this->line('  All Valid: ' . ($result['all_valid'] ? 'Yes' : 'No'));
            $this->line('  Verified Signatures: ' . $result['verified_signatures']);
            $this->line('  Failed Signatures: ' . $result['failed_signatures']);
            $this->newLine();

            $this->info('Signature Details:');
            foreach ($result['details'] as $index => $detail) {
                $this->line("  Signature " . ($index + 1) . ":");
                $this->line('    Signer: ' . $detail['signer_name']);
                $this->line('    Result: ' . strtoupper($detail['result']));
                $this->line('    Reason: ' . $detail['reason']);
                $this->line('    Certificate Status: ' . ($detail['certificate_status'] ?? 'N/A'));
                $this->line('    Fingerprint: ' . ($detail['certificate_fingerprint'] ?? 'N/A'));
                $this->line('    Issuer: ' . ($detail['issuer_dn'] ?? 'N/A'));
                $this->line('    Serial: ' . ($detail['serial_number'] ?? 'N/A'));
                $this->line('    Provider: ' . ($detail['signing_provider'] ?? 'N/A'));
                $this->line('    Revoked: ' . ($detail['revoked_at'] ? 'Yes' : 'No'));
                $this->newLine();
            }
        } else {
            $this->info('Verification Result: ' . strtoupper($result['status']));
            $this->line('Message: ' . $result['message']);
        }

        if ($result['status'] === 'verified') {
            return self::SUCCESS;
        }

        return self::FAILURE;
    }
}
