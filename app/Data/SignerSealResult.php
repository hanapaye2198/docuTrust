<?php

namespace App\Data;

class SignerSealResult
{
    public function __construct(
        public readonly int $signerCertificateId,
        public readonly string $signatureValue,
        public readonly string $signatureHash,
        public readonly string $publicKeyFingerprint,
        public readonly string $signatureAlgorithm,
        public readonly string $signingProvider,
        public readonly ?string $signingProviderReference = null,
        /** @var array<string, mixed>|null */
        public readonly ?array $signingProviderPayload = null,
    ) {}
}
