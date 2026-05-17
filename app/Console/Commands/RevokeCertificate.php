<?php

namespace App\Console\Commands;

use App\Models\SignerCertificate;
use App\Services\HsmAuditLogger;
use App\Services\HsmKeyManager;
use Illuminate\Console\Command;

/**
 * Revoke certificate command
 * 
 * Revokes a signer certificate and updates the CRL.
 */
class RevokeCertificate extends Command
{
    protected $signature = 'certificate:revoke
        {--certificate= : Certificate ID}
        {--reason= : Revocation reason (unspecified, keyCompromise, certificateHold, removeFromCRL)}';

    protected $description = 'Revoke a signer certificate';

    public function handle(): int
    {
        $certificateId = $this->option('certificate');
        $reason = $this->option('reason') ?? 'unspecified';

        if (!$certificateId) {
            $this->error('Specify --certificate option.');
            return self::FAILURE;
        }

        $certificate = SignerCertificate::find($certificateId);

        if (!$certificate) {
            $this->error("Certificate with ID {$certificateId} not found.");
            return self::FAILURE;
        }

        $this->info('Revoking Certificate');
        $this->line('===================');
        $this->line('Certificate ID: ' . $certificate->id);
        $this->line('Signer: ' . $certificate->signer?->name ?? 'Unknown');
        $this->line('Subject: ' . $certificate->subject_dn);
        $this->line('Serial: ' . $certificate->serial_number);
        $this->line('Reason: ' . $reason);
        $this->newLine();

        // Check if already revoked
        if ($certificate->revoked_at !== null) {
            $this->warn('Certificate is already revoked.');
            return self::FAILURE;
        }

        // Revoke certificate
        $certificate->update([
            'revoked_at' => now(),
            'revocation_reason' => $reason,
            'status' => 'revoked',
        ]);

        $this->info('Certificate revoked successfully.');

        // Log revocation
        $auditLogger = app(HsmAuditLogger::class);
        $auditLogger->logKeyDestruction(
            $certificate->signer?->hsm_key_id,
            'signer_certificate',
            $certificate->id,
            'system'
        );

        $this->info('Revocation logged.');

        // Note: CRL needs to be regenerated
        $this->warn('Note: CRL must be regenerated to include this revocation.');
        $this->line('Run: php artisan crl:generate');

        return self::SUCCESS;
    }
}
