<?php

namespace App\Trust\RemoteSigning;

final readonly class RemoteSignatureMaterial
{
    /**
     * @param  array<string, mixed>|null  $evidence
     */
    public function __construct(
        public string $signatureValue,
        public string $certificatePem,
        public string $issuerCertificatePem,
        public ?string $providerReference,
        public string $signatureAlgorithm,
        public ?string $publicKeyPem,
        public ?array $evidence,
    ) {}
}
