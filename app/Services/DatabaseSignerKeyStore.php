<?php

namespace App\Services;

use App\Contracts\SignerKeyStore;
use App\Models\DocumentSigner;
use RuntimeException;

class DatabaseSignerKeyStore implements SignerKeyStore
{
    public function hasKeyPair(DocumentSigner $signer): bool
    {
        return is_string($signer->signing_public_key) && trim($signer->signing_public_key) !== ''
            && is_string($signer->signing_private_key) && trim($signer->signing_private_key) !== '';
    }

    public function keyPairFor(DocumentSigner $signer): array
    {
        $publicKey = $signer->signing_public_key;
        $privateKey = $signer->signing_private_key;

        if (! is_string($publicKey) || trim($publicKey) === '') {
            throw new RuntimeException('Signer public key is unavailable.');
        }

        if (! is_string($privateKey) || trim($privateKey) === '') {
            throw new RuntimeException('Signer private key is unavailable.');
        }

        return [
            'public_key' => $publicKey,
            'private_key' => $privateKey,
        ];
    }

    public function storeKeyPair(DocumentSigner $signer, string $publicKeyPem, string $privateKeyPem): array
    {
        $signer->forceFill([
            'signing_public_key' => $publicKeyPem,
            'signing_private_key' => $privateKeyPem,
        ])->save();

        $signer->refresh();

        return $this->keyPairFor($signer);
    }
}
