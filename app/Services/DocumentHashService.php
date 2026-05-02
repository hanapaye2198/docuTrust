<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentHash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DocumentHashService
{
    public function __construct(private readonly BlockchainProofService $blockchainProofService) {}

    private function secureDiskName(): string
    {
        return (string) config('filesystems.docutrust_disk', 'local');
    }

    public function createForCompletedDocument(Document $document): ?DocumentHash
    {
        try {
            $existing = DocumentHash::query()->where('document_id', $document->id)->first();
            if ($existing !== null) {
                return $existing;
            }

            $filePath = $document->primaryPdfPath();
            if ($filePath === null || ! Storage::disk($this->secureDiskName())->exists($filePath)) {
                return null;
            }

            $hash = $this->generateDocumentHash($filePath);
            $transactionId = $this->anchorHashOnBlockchain($hash);

            return DocumentHash::query()->create([
                'document_id' => $document->id,
                'hash' => $hash,
                'transaction_id' => $transactionId,
                'created_at' => now(),
            ]);
        } catch (Throwable $throwable) {
            Log::channel('errors')->error('Document hash creation failed', [
                'document_id' => $document->id,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);

            return null;
        }
    }

    public function generateDocumentHash(string $filePath): string
    {
        $fileContents = Storage::disk($this->secureDiskName())->get($filePath);

        return hash('sha256', $fileContents);
    }

    public function generateHashForDocument(Document $document): string
    {
        $filePath = $document->primaryPdfPath();
        if ($filePath !== null && Storage::disk($this->secureDiskName())->exists($filePath)) {
            return $this->generateDocumentHash($filePath);
        }

        $payload = implode('|', [
            (string) $document->id,
            (string) $document->title,
            (string) $document->status->value,
            optional($document->updated_at)->toIso8601String() ?? '',
        ]);

        return hash('sha256', $payload);
    }

    public function transactionExistsOnBlockchain(string $transactionId): bool
    {
        return $this->blockchainProofService->verifyTransaction($transactionId);
    }

    private function anchorHashOnBlockchain(string $hash): ?string
    {
        try {
            return $this->blockchainProofService->anchorDocumentHash($hash);
        } catch (Throwable $throwable) {
            Log::channel('errors')->error('Blockchain document hash anchoring failed', [
                'hash' => $hash,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);

            return null;
        }
    }
}
