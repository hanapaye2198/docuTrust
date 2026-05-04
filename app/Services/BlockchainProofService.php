<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class BlockchainProofService
{
    /**
     * @return array{
     *   exists: bool,
     *   transaction_matches: bool|null,
     *   block_number: int|null,
     *   proof_timestamp: int|null,
     *   submitted_by: string|null
     * }
     */
    public function verifyDocumentHash(string $hash, ?string $transactionId = null): array
    {
        $baseUrl = (string) config('services.blockchain.base_url');
        if ($baseUrl === '') {
            return [
                'exists' => false,
                'transaction_matches' => null,
                'block_number' => null,
                'proof_timestamp' => null,
                'submitted_by' => null,
            ];
        }

        $payload = ['hash' => $hash];

        if (is_string($transactionId) && $transactionId !== '') {
            $payload['transactionHash'] = $transactionId;
        }

        $response = Http::baseUrl($baseUrl)
            ->timeout((int) config('services.blockchain.timeout', 10))
            ->acceptJson()
            ->post('/verify', $payload);

        if ($response->failed()) {
            throw new RuntimeException('Blockchain verification request failed.');
        }

        return [
            'exists' => (bool) $response->json('exists', false),
            'transaction_matches' => $response->json('transactionMatches'),
            'block_number' => $response->json('blockNumber'),
            'proof_timestamp' => $response->json('proofTimestamp'),
            'submitted_by' => $response->json('submittedBy'),
        ];
    }

    public function anchorDocumentHash(string $hash): ?string
    {
        $baseUrl = (string) config('services.blockchain.base_url');
        if ($baseUrl === '') {
            return null;
        }

        $response = Http::baseUrl($baseUrl)
            ->timeout((int) config('services.blockchain.timeout', 10))
            ->acceptJson()
            ->post('/anchor', [
                'hash' => $hash,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Blockchain anchor request failed.');
        }

        /** @var string|null $transactionId */
        $transactionId = $response->json('transactionHash');

        return is_string($transactionId) && $transactionId !== '' ? $transactionId : null;
    }

    public function verifyTransaction(string $transactionId): bool
    {
        $baseUrl = (string) config('services.blockchain.base_url');
        if ($baseUrl === '' || $transactionId === '') {
            return false;
        }

        $response = Http::baseUrl($baseUrl)
            ->timeout((int) config('services.blockchain.timeout', 10))
            ->acceptJson()
            ->post('/verify', [
                'transactionHash' => $transactionId,
            ]);

        if ($response->failed()) {
            return false;
        }

        return (bool) $response->json('exists', false);
    }
}
