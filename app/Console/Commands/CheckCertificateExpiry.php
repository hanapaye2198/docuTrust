<?php

namespace App\Console\Commands;

use App\Models\SignerCertificate;
use Illuminate\Console\Command;

/**
 * Check certificate expiry command
 * 
 * Checks for certificates that are expiring soon.
 */
class CheckCertificateExpiry extends Command
{
    protected $signature = 'certificate:check-expiry
        {--days=30 : Check certificates expiring within N days}';

    protected $description = 'Check for expiring certificates';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $this->info('Certificate Expiry Check');
        $this->line('======================');
        $this->line('Checking certificates expiring within ' . $days . ' days.');
        $this->newLine();

        $expiryDate = now()->addDays($days);

        $expiringCertificates = SignerCertificate::whereNotNull('valid_to')
            ->where('valid_to', '<=', $expiryDate)
            ->where('valid_to', '>=', now())
            ->where('status', 'active')
            ->orderBy('valid_to')
            ->get();

        if ($expiringCertificates->isEmpty()) {
            $this->info('No certificates expiring within ' . $days . ' days.');
            return self::SUCCESS;
        }

        $this->info('Expiring Certificates:');
        $this->line('----------------------');

        foreach ($expiringCertificates as $certificate) {
            $daysRemaining = now()->diffInDays($certificate->valid_to, false);
            $signerName = $certificate->signer?->name ?? 'Unknown';

            $this->line(sprintf(
                'Certificate ID: %s - Signer: %s - Expires: %s (%d days remaining)',
                $certificate->id,
                $signerName,
                $certificate->valid_to->toDateString(),
                $daysRemaining
            ));
        }

        $this->newLine();
        $this->warn($expiringCertificates->count() . ' certificate(s) expiring within ' . $days . ' days.');

        return self::SUCCESS;
    }
}
