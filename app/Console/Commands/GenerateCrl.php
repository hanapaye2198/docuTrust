<?php

namespace App\Console\Commands;

use App\Services\CrlGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Generate CRL command
 * 
 * Generates and stores the Certificate Revocation List (CRL).
 */
class GenerateCrl extends Command
{
    protected $signature = 'crl:generate
        {--format=pem : Output format (pem or der)}
        {--output= : Output file path}';

    protected $description = 'Generate Certificate Revocation List (CRL)';

    public function handle(): int
    {
        $format = $this->option('format') ?? 'pem';
        $output = $this->option('output');

        $this->info('Generating CRL...');

        $crlGenerator = new CrlGenerator();

        try {
            if ($format === 'pem') {
                $crl = $crlGenerator->getPemFormat();
                $contentType = 'application/x-pkcs7-crl';
                $extension = 'pem';
            } else {
                $crl = $crlGenerator->getDerFormat();
                $contentType = 'application/pkix-crl';
                $extension = 'der';
            }

            $this->info('CRL generated successfully.');
            $this->line('  Format: ' . strtoupper($format));
            $this->line('  Next Update: ' . $crlGenerator->getNextUpdate()->toIso8601String());

            // Output to file or console
            if ($output) {
                file_put_contents($output, $crl);
                $this->info('CRL saved to: ' . $output);
            } else {
                $this->line('--- CRL Output ---');
                $this->line($crl);
                $this->line('------------------');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to generate CRL: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
