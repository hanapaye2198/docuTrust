<?php

namespace App\Services;

use App\Concerns\ResolvesSecureDisk;
use App\Models\NotarialRegisterEntry;
use App\Models\NotaryCredential;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class NotarialCertificateService
{
    use ResolvesSecureDisk;

    public function __construct(
        private readonly DocumentStorageService $documentStorageService,
    ) {}

    /**
     * Generate a notarial certificate PDF for a register entry.
     */
    public function generate(NotarialRegisterEntry $entry): ?string
    {
        $entry->loadMissing(['notaryCredential.user']);

        $credential = $entry->notaryCredential;
        if (! $credential instanceof NotaryCredential) {
            return null;
        }

        if (! extension_loaded('gd')) {
            if (app()->environment('production')) {
                throw new RuntimeException('The PHP GD extension is required to generate notarial certificates.');
            }

            Log::warning('Skipping notarial certificate generation because GD is not installed.', [
                'entry_id' => $entry->id,
                'notary_request_id' => $entry->notary_request_id,
                'environment' => app()->environment(),
            ]);

            return null;
        }

        try {
            $output = $this->renderCertificatePdf($entry, $credential);

            return $this->documentStorageService->storeCertificatePdf(
                $output,
                sprintf(
                    'certificates/notarial/%s-%s.pdf',
                    $entry->notary_request_id,
                    Str::uuid()->toString()
                )
            );
        } catch (Throwable $throwable) {
            if (app()->environment('production')) {
                throw $throwable;
            }

            Log::warning('Skipping notarial certificate generation after local PDF failure.', [
                'entry_id' => $entry->id,
                'notary_request_id' => $entry->notary_request_id,
                'message' => $throwable->getMessage(),
                'exception' => $throwable::class,
            ]);

            return null;
        }
    }

    private function renderCertificatePdf(NotarialRegisterEntry $entry, NotaryCredential $credential): string
    {
        $qrCodePath = $entry->qr_code_path;
        if (! is_string($qrCodePath) || $qrCodePath === '') {
            return $this->makeCertificatePdf($entry, $credential, null);
        }

        return $this->documentStorageService->withTemporaryLocalPath(
            $qrCodePath,
            fn (string $localQrImagePath): string => $this->makeCertificatePdf($entry, $credential, $localQrImagePath)
        );
    }

    private function makeCertificatePdf(NotarialRegisterEntry $entry, NotaryCredential $credential, ?string $qrCodeImagePath): string
    {
        return Pdf::loadView('certificates.notarial', [
            'entry' => $entry,
            'credential' => $credential,
            'qrCodeImagePath' => $qrCodeImagePath,
        ])->setPaper('a4')->output();
    }
}
