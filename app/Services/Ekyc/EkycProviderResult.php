<?php

namespace App\Services\Ekyc;

use App\Enums\EkycStatus;

readonly class EkycProviderResult
{
    public function __construct(
        public EkycStatus $status,
        public ?string $providerReference = null,
        public ?string $accessToken = null,
        public ?array $extractedData = null,
        public ?string $failureReason = null,
        public ?float $confidenceScore = null,
    ) {}

    public function isPending(): bool
    {
        return $this->status === EkycStatus::Pending;
    }

    public function isVerified(): bool
    {
        return $this->status === EkycStatus::Verified;
    }

    public function isRejected(): bool
    {
        return $this->status === EkycStatus::Rejected;
    }
}
