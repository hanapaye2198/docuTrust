<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PublicPdfStream
{
    /**
     * Stream a PDF from the public disk. Paths are storage-relative (e.g. documents/foo.pdf), not URLs.
     */
    public static function inlineResponse(?string $relativePath): StreamedResponse
    {
        $disk = Storage::disk((string) config('filesystems.docutrust_disk', 'local'));

        if ($relativePath === null || $relativePath === '' || ! $disk->exists($relativePath)) {
            abort(404);
        }

        $filename = basename($relativePath);

        return $disk->response($relativePath, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=600, stale-while-revalidate=3600',
        ]);
    }
}
