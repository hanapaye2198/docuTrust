<?php

namespace App\Data;

use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\Signature;

final readonly class FieldSignatureCaptureResult
{
    public function __construct(
        public Document $document,
        public DocumentSigner $signer,
        public Signature $signature,
        public string $message,
    ) {}
}
