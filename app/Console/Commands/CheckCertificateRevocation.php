<?php

namespace App\Console\Commands;

use App\Models\SignerCertificate;
use Illuminate\Console\Command;

/**
 * Check certificate revocation command
 * 
 * Checks for revoked certificates.
 */
class CheckCertificateRevocation extends Command
{
    protected $signature = 'certificate:check-revocation';

    protected $description = 'Check for revoked certificates';

    public function handle(): int
    {
        $this->info('Certificate Revocation Check');
        $this->line('==========================');
        $this->newLine();

        $revokedCertificates = SignerCertificate::where('status', 'revoked')
            ->orWhereNotNull('revoked_at')
            ->orderBy('revoked_at', 'desc')
            ->get();

        if ($revokedCertificates->isEmpty()) {
            $this->info('No revoked certificates found.');
            return self::SUCCESS;
        }

        $this->info('Revoked Certificates:');
        $this->line('---------------------');

        foreach ($revokedCertificates as $certificate) {
            $signerName = $certificate->signer?->name ?? 'Unknown';
            $revokedAt = $certificate->revoked_at?->toDateTimeString() ?? 'Unknown';
            $reason = $certificate->revocation_reason ?? 'Unspecified';

            $this->line(sprintf(
                'Certificate ID: %s - Signer: %s - Revoked: %s - Reason: %s',
                $certificate->id,
                $signerName,
                $revokedAt,
                $reason
            ));
        }

        $this->newLine();
        $this->warn($revokedCertificates->count() . ' certificate(s) revoked.');

        return self::SUCCESS;
    }
}
