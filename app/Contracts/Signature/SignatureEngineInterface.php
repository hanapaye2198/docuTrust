<?php

namespace App\Contracts\Signature;

use App\Data\FieldSignatureCaptureResult;
use App\Models\Document;
use App\Models\DocumentHash;
use App\Models\DocumentSigner;
use App\Models\SignatureField;

interface SignatureEngineInterface
{
    /**
     * @return array<string, bool>
     */
    public function capabilities(): array;

    public function captureField(
        DocumentSigner $signer,
        SignatureField $field,
        ?string $submittedValue,
        ?string $signatureImage,
        string $ipAddress,
    ): FieldSignatureCaptureResult;

    public function sealDocument(Document $document): ?DocumentHash;
}
