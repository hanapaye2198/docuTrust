<?php

namespace App\Console\Commands;

use App\Services\HsmAuditLogger;
use App\Services\HsmCertificateAuthorityService;
use App\Services\HsmKeyManager;
use App\Services\HsmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Initialize HSM for CSC compliance
 * 
 * This command initializes the HSM with the root CA key pair and sets up
 * the initial PKI infrastructure.
 */
class InitializeHsm extends Command
{
    protected $signature = 'hsm:initialize
        {--force : Force re-initialization}';

    protected $description = 'Initialize HSM with root CA key pair';

    public function handle(): int
    {
        $this->info('Initializing HSM for CSC compliance...');

        // Check if already initialized
        if (!$this->option('force') && $this->isInitialized()) {
            $this->warn('HSM is already initialized. Use --force to re-initialize.');
            return self::FAILURE;
        }

        // Initialize HSM service
        $hsmService = app(HsmService::class);
        $hsmKeyManager = app(HsmKeyManager::class);
        $hsmCaService = app(HsmCertificateAuthorityService::class);
        $auditLogger = app(HsmAuditLogger::class);

        // Check HSM status
        $status = $hsmService->getStatus();
        if ($status['status'] !== 'online') {
            $this->error('HSM is not online. Status: ' . $status['status']);
            return self::FAILURE;
        }

        $this->info('HSM is online. Generating root CA key pair...');

        // Generate root CA key pair
        try {
            $keyPair = $hsmService->generateRsaKeyPair(2048);
            $this->info('Root CA key pair generated.');
            $this->line('  Public Key Fingerprint: ' . substr($keyPair['fingerprint'], 0, 16) . '...');
            $this->line('  Private Key ID: ' . $keyPair['privateKeyId']);
        } catch (\Throwable $e) {
            $this->error('Failed to generate root CA key pair: ' . $e->getMessage());
            return self::FAILURE;
        }

        // Create root CA certificate
        $this->info('Creating root CA certificate...');

        try {
            $certificate = $hsmCaService->createSelfSignedAuthorityCertificate();
            $this->info('Root CA certificate created.');
            $this->line('  Subject: ' . $certificate['subject_dn']);
            $this->line('  Valid From: ' . $certificate['valid_from']->toIso8601String());
            $this->line('  Valid To: ' . $certificate['valid_to']->toIso8601String());
        } catch (\Throwable $e) {
            $this->error('Failed to create root CA certificate: ' . $e->getMessage());
            return self::FAILURE;
        }

        // Store root CA in database
        $this->info('Storing root CA in database...');

        try {
            DB::table('certificate_authorities')->insert([
                'name' => config('docutrust.pki.root_ca_name', 'DocuTrust Root CA'),
                'subject_dn' => $certificate['subject_dn'],
                'issuer_dn' => $certificate['issuer_dn'],
                'serial_number' => $certificate['serial_number'],
                'public_key_pem' => $certificate['public_key_pem'],
                'private_key_id' => $keyPair['privateKeyId'],
                'certificate_pem' => $certificate['certificate_pem'],
                'fingerprint_sha256' => $certificate['fingerprint_sha256'],
                'valid_from' => $certificate['valid_from'],
                'valid_to' => $certificate['valid_to'],
                'is_root' => true,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->info('Root CA stored in database.');
        } catch (\Throwable $e) {
            $this->error('Failed to store root CA: ' . $e->getMessage());
            return self::FAILURE;
        }

        // Log initialization
        $auditLogger->logKeyGeneration(
            $keyPair['privateKeyId'],
            'root_ca',
            null,
            'system'
        );

        $this->info('HSM initialization complete.');
        $this->newLine();

        return self::SUCCESS;
    }

    private function isInitialized(): bool
    {
        $count = DB::table('certificate_authorities')
            ->where('is_root', true)
            ->where('status', 'active')
            ->count();

        return $count > 0;
    }
}
