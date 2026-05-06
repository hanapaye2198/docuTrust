<?php

namespace App\Contracts;

use App\Models\DocumentSigner;

interface SignerKeyStore
{
    public function hasKeyPair(DocumentSigner $signer): bool;

    /**
     * @return array{public_key: string, private_key: string}
     */
    public function keyPairFor(DocumentSigner $signer): array;

    /**
     * @return array{public_key: string, private_key: string}
     */
    public function storeKeyPair(DocumentSigner $signer, string $publicKeyPem, string $privateKeyPem): array;
}
