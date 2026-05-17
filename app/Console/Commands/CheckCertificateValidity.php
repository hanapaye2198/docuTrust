<?php

namespace App\Console\Commands;

use App\Models\SignerCertificate;
use Illuminate\Console\Command;

/**
 * Check certificate validity command
 * 
 * Checks for certificates that are not yet valid or have expired.
 */
class CheckCertificateValidity extends Command
{
    protected $signature = 'certificate:check-validity';

    protected $description = 'Check for invalid certificates (not yet valid or expired)';

    public function handle(): int
    {
        $this->info('Certificate Validity Check');
        $this->line('========================');
        $this->newLine();

        // Check not yet valid certificates
        $notYetValid = SignerCertificate::whereNotNull('valid_from')
            ->where('valid_from', '>', now())
            ->where('status', 'active')
            ->get();

        // Check expired certificates
        $expired = SignerCertificate::whereNotNull('valid_to')
            ->where('valid_to', '<', now())
            ->where('status', 'active')
            ->get();

        $issues = [];

        if (!$notYetValid->isEmpty()) {
            $issues[] = ['type' => 'not_yet_valid', 'count' => $notYetValid->count()];
        }

        if (!$expired->isEmpty()) {
            $issues[] = ['type' => 'expired', 'count' => $expired->count()];
        }

        if (empty($issues)) {
            $this->info('All certificates are valid.');
            return self::SUCCESS;
        }

        $this->warn('Certificate Validity Issues:');
        $this->line('--------------------------');

        foreach ($issues as $issue) {
            if ($issue['type'] === 'not_yet_valid') {
                $this->warn($issue['count'] . ' certificate(s) not yet valid:');
                foreach ($notYetValid->take(5) as $certificate) {
                    $this->line('  - ' . $certificate->subject_dn . ' (valid from: ' . $certificate->valid_from->toDateString() . ')');
                }
            } elseif ($issue['type'] === 'expired') {
                $this->error($issue['count'] . ' certificate(s) expired:');
                foreach ($expired->take(5) as $certificate) {
                    $this->line('  - ' . $certificate->subject_dn . ' (expired: ' . $certificate->valid_to->toDateString() . ')');
                }
            }
        }

        return self::FAILURE;
    }
}
