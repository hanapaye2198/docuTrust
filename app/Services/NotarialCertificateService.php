<?php

namespace App\Services;

use App\Models\NotarialRegisterEntry;
use App\Models\NotaryCredential;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NotarialCertificateService
{
    private function secureDiskName(): string
    {
        return (string) config('filesystems.docutrust_disk', 'local');
    }

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

        $pdf = Pdf::loadView('certificates.notarial', [
            'entry' => $entry,
            'credential' => $credential,
        ])->setPaper('a4');

        $path = sprintf(
            'certificates/notarial/%s-%s.pdf',
            $entry->notary_request_id,
            Str::uuid()->toString()
        );

        Storage::disk($this->secureDiskName())->put($path, $pdf->output());

        return $path;
    }
}
