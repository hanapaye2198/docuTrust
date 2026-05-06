<?php

namespace App\Trust\Authorization;

final readonly class TrustAuthorizationMaterial
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function __construct(
        public string $providerName,
        public ?string $credentialId,
        public ?string $sad,
        public ?string $accessToken,
        public ?string $authorizationReference,
        public ?array $payload,
    ) {}
}
