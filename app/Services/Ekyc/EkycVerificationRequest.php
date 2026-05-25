<?php

namespace App\Services\Ekyc;

readonly class EkycVerificationRequest
{
    public function __construct(
        public string $externalUserId,
        public string $firstName,
        public string $lastName,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $documentPath = null,
    ) {}
}
