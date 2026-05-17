<?php

namespace App\Console\Commands;

use App\Models\DocumentSigner;
use App\Services\HsmCertificateAuthorityService;
use App\Services\HsmKeyManager;
use App\Services\HsmPkiSignatureService;
use Illuminate\Console\Command;

/**
 * Generate certificate command
 * 
 * Generates a new certificate for a signer.
 */
class GenerateCertificate extends Command
{
    protected $signature = 'certificate:generate
        {--signer= : Signer ID}
        {--subject= : Subject DN (CN=Name,O=Org,C=PH)}
        {--key-size=2048 : Key size (2048 or 4096)}';

    protected $description = 'Generate a new certificate for a signer';

    public function handle(): int
    {
        $signerId = $this->option('signer');
        $subjectInput = $this->option('subject');
        $keySize = (int) $this->option('key-size');

        if (!$signerId) {
            $this->error('Specify --signer option.');
            return self::FAILURE;
        }

        $signer = DocumentSigner::find($signerId);

        if (!$signer) {
            $this->error("Signer with ID {$signerId} not found.");
            return self::FAILURE;
        }

        // Parse subject DN
        $subject = $this->parseSubjectDn($subjectInput ?? "CN={$signer->name},O=DocuTrust,C=PH");

        $this->info('Generating Certificate');
        $this->line('====================');
        $this->line('Signer: ' . $signer->name);
        $this->line('Subject: ' . implode(', ', array_map(fn($k, $v) => "$k=$v", array_keys($subject), $subject)));
        $this->line('Key Size: ' . $keySize);
        $this->newLine();

        // Generate key pair in HSM
        $hsmKeyManager = app(HsmKeyManager::class);
        $hsmCaService = app(HsmCertificateAuthorityService::class);

        try {
            $keyPair = $hsmKeyManager->generateKeyPairForSigner($signer);
            $this->info('Key pair generated in HSM.');
            $this->line('  Key ID: ' . $signer->hsm_key_id);
            $this->line('  Fingerprint: ' . substr($signer->public_key_fingerprint, 0, 16) . '...');
        } catch (\Throwable $e) {
            $this->error('Failed to generate key pair: ' . $e->getMessage());
            return self::FAILURE;
        }

        // Generate certificate
        try {
            $certificate = $hsmCaService->createSelfSignedAuthorityCertificate();
            $this->info('Certificate generated.');
            $this->line('  Subject: ' . $certificate['subject_dn']);
            $this->line('  Valid From: ' . $certificate['valid_from']->toIso8601String());
            $this->line('  Valid To: ' . $certificate['valid_to']->toIso8601String());
        } catch (\Throwable $e) {
            $this->error('Failed to generate certificate: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info('Certificate generation complete.');
        $this->newLine();

        return self::SUCCESS;
    }

    private function parseSubjectDn(string $subject): array
    {
        $parts = explode(',', $subject);
        $dn = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if (str_contains($part, '=')) {
                [$key, $value] = explode('=', $part, 2);
                $dn[trim($key)] = trim($value);
            }
        }

        return $dn;
    }
}
