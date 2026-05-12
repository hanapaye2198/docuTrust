<?php

namespace App\Services;

use App\Concerns\ResolvesSecureDisk;
use App\Models\Document;
use App\Models\DocumentHash;
use App\Models\DocumentSigner;
use App\Models\Signature;
use Illuminate\Support\Facades\Storage;

class CompletedDocumentSealingService
{
    use ResolvesSecureDisk;

    public function __construct(
        private readonly DocumentPdfStampingService $documentPdfStampingService,
        private readonly DocumentHashService $documentHashService,
        private readonly SignerSealProviderManager $signerSealProviderManager,
    ) {}

    public function seal(Document $document): ?DocumentHash
    {
        $document = $document->fresh(['signatures', 'documentSigners']);

        if ($document === null) {
            return null;
        }

        $finalPdfPath = $document->verifiablePdfPath();
        if ($finalPdfPath === null || ! Storage::disk($this->secureDiskName())->exists($finalPdfPath)) {
            $finalPdfPath = $this->documentPdfStampingService->generateFinalPdf($document);
            $document = $document->fresh(['signatures', 'documentSigners']);
        }

        if ($document === null) {
            return null;
        }

        $verifiablePath = $document->verifiablePdfPath();
        if ($verifiablePath === null || ! Storage::disk($this->secureDiskName())->exists($verifiablePath)) {
            return null;
        }

        $hash = $this->documentHashService->generateDocumentHash($verifiablePath);
        $this->sealSignerSignatures($document, $hash);

        return $this->documentHashService->createOrRefreshForCompletedDocument($document->fresh(), $hash);
    }

    private function sealSignerSignatures(Document $document, string $hash): void
    {
        $signaturesBySigner = $document->signatures
            ->filter(fn (Signature $signature): bool => $signature->signer_id !== null)
            ->groupBy('signer_id');

        foreach ($document->documentSigners as $signer) {
            $signatures = $signaturesBySigner->get($signer->id);
            if ($signatures === null || $signatures->isEmpty()) {
                continue;
            }

            $this->sealSignerSignatureSet($signer, $signatures->all(), $hash);
        }
    }

    /**
     * @param  array<int, Signature>  $signatures
     */
    private function sealSignerSignatureSet(DocumentSigner $signer, array $signatures, string $hash): void
    {
        $sealResult = $this->signerSealProviderManager->seal($signer, $hash);

        foreach ($signatures as $signature) {
            $signature->forceFill([
                'signer_certificate_id' => $sealResult->signerCertificateId,
                'signature_value' => $sealResult->signatureValue,
                'signature_hash' => $sealResult->signatureHash,
                'public_key_fingerprint' => $sealResult->publicKeyFingerprint,
                'signature_algorithm' => $sealResult->signatureAlgorithm,
                'signing_provider' => $sealResult->signingProvider,
                'signing_provider_reference' => $sealResult->signingProviderReference,
                'signing_provider_payload' => $sealResult->signingProviderPayload,
            ])->save();
        }
    }
}
