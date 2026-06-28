<?php

namespace App\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PublicPdfStream
{
    /**
     * Stream a PDF from a private disk. Paths are storage-relative (e.g. documents/foo.pdf), not URLs.
     */
    public static function inlineResponse(?string $relativePath, ?string $diskName = null): StreamedResponse
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($diskName ?: (string) config('filesystems.docutrust_disk', 'local'));

        if ($relativePath === null || $relativePath === '' || ! $disk->exists($relativePath)) {
            abort(404);
        }

        $filename = basename($relativePath);

        return $disk->response($relativePath, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
