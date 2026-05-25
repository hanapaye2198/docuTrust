<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentArchiveService
{
    public function archiveCompletedDocument(Document $document): ?Document
    {
        $document = $document->fresh();
        if ($document === null || $document->status !== DocumentStatus::Completed) {
            return null;
        }

        $archiveDiskName = (string) config('filesystems.docutrust_archive_disk', config('filesystems.docutrust_disk', 'local'));
        $workingDiskName = (string) config('filesystems.docutrust_disk', 'local');

        $workingDisk = Storage::disk($workingDiskName);
        $archiveDisk = Storage::disk($archiveDiskName);

        $archiveDocumentPath = $this->archiveDocumentFile($document, $workingDiskName, $workingDisk, $archiveDiskName, $archiveDisk);
        $archiveCertificatePath = $this->archiveCertificateFile($document, $workingDiskName, $archiveDiskName, $archiveDisk);

        $document->forceFill([
            'archive_storage_disk' => $archiveDiskName,
            'archive_document_path' => $archiveDocumentPath,
            'archive_certificate_path' => $archiveCertificatePath,
            'archived_at' => ($archiveDocumentPath !== null || $archiveCertificatePath !== null) ? now() : $document->archived_at,
        ])->save();

        return $document->fresh();
    }

    private function archiveDocumentFile(
        Document $document,
        string $workingDiskName,
        mixed $workingDisk,
        string $archiveDiskName,
        mixed $archiveDisk,
    ): ?string {
        $path = $document->verifiablePdfPath();
        if (! is_string($path) || $path === '') {
            return $document->archive_document_path;
        }

        if ($workingDiskName === $archiveDiskName) {
            return $path;
        }

        if (
            is_string($document->archive_document_path)
            && $document->archive_document_path !== ''
            && $document->archive_document_path === $path
            && $archiveDisk->exists($document->archive_document_path)
        ) {
            return $document->archive_document_path;
        }

        if (! $workingDisk->exists($path)) {
            return null;
        }

        $archivePath = sprintf('archives/documents/%d/%s.pdf', $document->id, Str::uuid()->toString());
        $archiveDisk->put($archivePath, $workingDisk->get($path));

        return $archivePath;
    }

    private function archiveCertificateFile(Document $document, string $workingDiskName, string $archiveDiskName, mixed $archiveDisk): ?string
    {
        $path = $document->certificate_path;
        if (! is_string($path) || $path === '') {
            return $document->archive_certificate_path;
        }

        if (is_string($document->archive_certificate_path) && $document->archive_certificate_path !== '' && $archiveDisk->exists($document->archive_certificate_path)) {
            return $document->archive_certificate_path;
        }

        if ($archiveDiskName === 'local') {
            return $path;
        }

        $workingDisk = Storage::disk($workingDiskName);
        if (! $workingDisk->exists($path)) {
            return null;
        }

        $archivePath = sprintf('archives/certificates/%d/%s.pdf', $document->id, Str::uuid()->toString());
        $archiveDisk->put($archivePath, $workingDisk->get($path));

        return $archivePath;
    }
}
