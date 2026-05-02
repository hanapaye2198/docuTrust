<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class BlockchainProofService
{
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
