<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use RuntimeException;

class DocumentStorageService
{
    public function secureDiskName(): string
    {
        return (string) config('filesystems.docutrust_disk', 'local');
    }

    public function secureDisk(): Filesystem
    {
        return Storage::disk($this->secureDiskName());
    }

    /**
     * @template T
     *
     * @param  callable(string): T  $callback
     * @return T
     */
    public function withTemporaryLocalPath(string $path, callable $callback): mixed
    {
        $disk = $this->secureDisk();

        if (! $disk->exists($path)) {
            throw new RuntimeException("Secure asset [{$path}] does not exist.");
        }

        $stream = $disk->readStream($path);
        if (! is_resource($stream)) {
            throw new RuntimeException("Unable to read secure asset [{$path}] as a stream.");
        }

        $tempBasePath = tempnam(sys_get_temp_dir(), 'docutrust-secure-');
        if ($tempBasePath === false) {
            fclose($stream);

            throw new RuntimeException('Unable to allocate a temporary file for a secure asset.');
        }

        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $tempPath = $extension !== '' ? $tempBasePath.'.'.$extension : $tempBasePath;

        try {
            if ($tempPath !== $tempBasePath && ! @rename($tempBasePath, $tempPath)) {
                $tempPath = $tempBasePath;
            }

            $tempHandle = fopen($tempPath, 'wb');
            if (! is_resource($tempHandle)) {
                throw new RuntimeException('Unable to open a temporary file for secure asset materialization.');
            }

            try {
                stream_copy_to_stream($stream, $tempHandle);
            } finally {
                fclose($tempHandle);
            }

            return $callback($tempPath);
        } finally {
            fclose($stream);

            if (is_file($tempPath)) {
                @unlink($tempPath);
            }

            if ($tempBasePath !== $tempPath && is_file($tempBasePath)) {
                @unlink($tempBasePath);
            }
        }
    }

    public function storeCertificatePdf(string $contents, ?string $path = null): string
    {
        $targetPath = $path ?? sprintf('certificates/%s.pdf', Str::uuid()->toString());
        $this->secureDisk()->put($targetPath, $contents);

        return $targetPath;
    }

    public function storeGeneratedDocumentPdf(int $documentId, string $mode, string $contents, ?string $path = null): string
    {
        $targetPath = $path ?? sprintf(
            'documents/generated/%d-%s-%s.pdf',
            $documentId,
            $mode,
            Str::uuid()->toString()
        );

        $this->secureDisk()->put($targetPath, $contents);

        return $targetPath;
    }

    public function storeNotarizedDocumentPdf(int $notaryRequestId, string $contents, ?string $path = null): string
    {
        $targetPath = $path ?? sprintf(
            'documents/notarized/%d-notarized-%s.pdf',
            $notaryRequestId,
            Str::uuid()->toString()
        );

        $this->secureDisk()->put($targetPath, $contents);

        return $targetPath;
    }

    public function storeVerificationQrCode(string $token, string $contents, ?string $path = null): string
    {
        $targetPath = $path ?? sprintf('notary/qr/%s.png', $token);
        $this->secureDisk()->put($targetPath, $contents);

        return $targetPath;
    }

    public function certificateExists(string $path): bool
    {
        return $this->secureDisk()->exists($path);
    }

    public function certificateResponse(string $path, string $downloadName): StreamedResponse
    {
        return Storage::disk($this->secureDiskName())->response(
            $path,
            $downloadName,
            ['Content-Type' => 'application/pdf']
        );
    }

    public function certificateDownload(string $path, string $downloadName): StreamedResponse
    {
        return Storage::disk($this->secureDiskName())->download(
            $path,
            $downloadName,
            ['Content-Type' => 'application/pdf']
        );
    }
}
