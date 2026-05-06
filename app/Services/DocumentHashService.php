<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentHash;
use Carbon\CarbonImmutable;
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
            $filePath = $document->verifiablePdfPath();
            if ($filePath === null || ! Storage::disk($this->secureDiskName())->exists($filePath)) {
                return null;
            }

            $hash = $this->generateDocumentHash($filePath);

            return $this->createOrRefreshForCompletedDocument($document, $hash);
        } catch (Throwable $throwable) {
            Log::channel('errors')->error('Document hash creation failed', [
                'document_id' => $document->id,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);

            return null;
        }
    }

    public function createOrRefreshForCompletedDocument(Document $document, string $hash): ?DocumentHash
    {
        try {
            $existing = DocumentHash::query()->where('document_id', $document->id)->first();
            if ($existing !== null && hash_equals(strtolower($existing->hash), strtolower($hash))) {
                return $existing;
            }

            $transactionId = $this->anchorHashOnBlockchain($hash);

            if ($existing !== null) {
                $existing->forceFill([
                    'hash' => $hash,
                    'transaction_id' => $transactionId,
                    'created_at' => now(),
                ])->save();

                return $existing->refresh();
            }

            return DocumentHash::query()->create([
                'document_id' => $document->id,
                'hash' => $hash,
                'transaction_id' => $transactionId,
                'created_at' => now(),
            ]);
        } catch (Throwable $throwable) {
            Log::channel('errors')->error('Document hash persistence failed', [
                'document_id' => $document->id,
                'hash' => $hash,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);

            return null;
        }
    }

    public function generateDocumentHash(string $filePath): string
    {
        $disk = Storage::disk($this->secureDiskName());
        $stream = $disk->readStream($filePath);

        if (is_resource($stream)) {
            try {
                $context = hash_init('sha256');
                hash_update_stream($context, $stream);

                return hash_final($context);
            } finally {
                fclose($stream);
            }
        }

        return hash('sha256', $disk->get($filePath));
    }

    public function generateHashForDocument(Document $document): string
    {
        $filePath = $document->verifiablePdfPath() ?: $document->primaryPdfPath();
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

    /**
     * @return array{
     *   status: 'verified'|'failed'|'not_available',
     *   anchored: bool,
     *   transaction_id: string|null,
     *   transaction_matches: bool|null,
     *   block_number: int|null,
     *   anchored_at: string|null,
     *   submitted_by: string|null,
     *   message: string
     * }
     */
    public function verifyStoredProof(DocumentHash $documentHash): array
    {
        if (! is_string($documentHash->transaction_id) || $documentHash->transaction_id === '') {
            return [
                'status' => 'not_available',
                'anchored' => false,
                'transaction_id' => null,
                'transaction_matches' => null,
                'block_number' => null,
                'anchored_at' => null,
                'submitted_by' => null,
                'message' => 'No blockchain transaction is recorded for this document.',
            ];
        }

        try {
            $result = $this->blockchainProofService->verifyDocumentHash($documentHash->hash, $documentHash->transaction_id);

            $anchoredAt = is_numeric($result['proof_timestamp'])
                ? CarbonImmutable::createFromTimestampUTC((int) $result['proof_timestamp'])->toDateTimeString()
                : null;

            $isVerified = $result['exists'] && $result['transaction_matches'] !== false;

            return [
                'status' => $isVerified ? 'verified' : 'failed',
                'anchored' => $result['exists'],
                'transaction_id' => $documentHash->transaction_id,
                'transaction_matches' => $result['transaction_matches'],
                'block_number' => is_numeric($result['block_number']) ? (int) $result['block_number'] : null,
                'anchored_at' => $anchoredAt,
                'submitted_by' => is_string($result['submitted_by']) && $result['submitted_by'] !== '' ? $result['submitted_by'] : null,
                'message' => $this->blockchainVerificationMessage($result),
            ];
        } catch (Throwable $throwable) {
            Log::channel('errors')->error('Blockchain proof verification failed', [
                'document_hash_id' => $documentHash->id,
                'document_id' => $documentHash->document_id,
                'transaction_id' => $documentHash->transaction_id,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'anchored' => false,
                'transaction_id' => $documentHash->transaction_id,
                'transaction_matches' => null,
                'block_number' => null,
                'anchored_at' => null,
                'submitted_by' => null,
                'message' => 'Unable to verify blockchain proof right now.',
            ];
        }
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

    /**
     * @param  array{exists: bool, transaction_matches: bool|null, block_number: int|null, proof_timestamp: int|null, submitted_by: string|null}  $result
     */
    private function blockchainVerificationMessage(array $result): string
    {
        if (! $result['exists']) {
            return 'Document hash was not found on-chain.';
        }

        if ($result['transaction_matches'] === false) {
            return 'Document hash exists on-chain, but the stored transaction does not match the blockchain record.';
        }

        return 'Document hash is anchored on-chain and matches the recorded transaction.';
    }
}
