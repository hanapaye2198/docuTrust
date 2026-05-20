<?php

namespace App\Services\Signature;

use App\Contracts\Signature\SignatureEngineInterface;
use App\Data\FieldSignatureCaptureResult;
use App\Models\Document;
use App\Models\DocumentHash;
use App\Models\DocumentSigner;
use App\Models\SignatureField;
use App\Services\CompletedDocumentSealingService;
use App\Services\FieldSignatureCaptureService;

class BasicElectronicSignatureDriver implements SignatureEngineInterface
{
    public function __construct(
        private readonly FieldSignatureCaptureService $fieldSignatureCaptureService,
        private readonly CompletedDocumentSealingService $completedDocumentSealingService,
    ) {}

    /**
     * @return array<string, bool>
     */
    public function capabilities(): array
    {
        return [
            'visual_signature' => true,
            'detached_hash_signing' => true,
            'x509_embed' => false,
            'pades' => false,
            'hsm' => false,
            'tsa' => false,
        ];
    }

    public function captureField(
        DocumentSigner $signer,
        SignatureField $field,
        ?string $submittedValue,
        ?string $signatureImage,
        string $ipAddress,
    ): FieldSignatureCaptureResult {
        return $this->fieldSignatureCaptureService->capture(
            $signer,
            $field,
            $submittedValue,
            $signatureImage,
            $ipAddress,
        );
    }

    public function sealDocument(Document $document): ?DocumentHash
    {
        return $this->completedDocumentSealingService->seal($document);
    }
}
