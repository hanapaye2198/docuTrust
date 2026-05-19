<?php

namespace App\Services\Ekyc;

readonly class EkycNameMatchResult
{
    public function __construct(
        public bool $matched,
        public string $message,
        public ?string $ocrText = null,
    ) {}
}
