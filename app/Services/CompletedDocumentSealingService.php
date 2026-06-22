<?php

namespace App\Services;

use App\Concerns\ResolvesSecureDisk;
use App\Models\Document;
use App\Models\DocumentHash;
use App\Models\DocumentSigner;
use App\Models\Signature;
use App\Services\Signature\CscSigningOrchestrator;
use App\Services\Signature\SadLifecycleService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

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

        $signer = $document->documentSigners
            ->first(fn (DocumentSigner $signer): bool => is_string($signer->remote_credential_id) && $signer->remote_credential_id !== '');
        $stampedPdfPath = $this->resolveLocalSecureDiskPath($verifiablePath);
        $sadService = app(SadLifecycleService::class);

        // CSC PAdES signing (when remote credential is available)
        $useCscSigning = config('signature.pades_enabled', false)
            && $signer?->remote_credential_id
            && session()->has('csc_access_token')
            && $sadService->isValid($document->id, $signer->id);

        if ($useCscSigning) {
            $orchestrator = app(CscSigningOrchestrator::class);
            $cscResult = $orchestrator->orchestrate(
                document: $document,
                signer: $signer,
                stampedPdfPath: $stampedPdfPath,
                accessToken: session('csc_access_token'),
                sad: '',
                credentialId: $signer->remote_credential_id,
                outputPath: $this->resolveFinalOutputPath($document),
            );

            $document->update([
                'final_pdf_path' => $this->relativeSecureDiskPath($cscResult['output_path']),
                'csc_signed' => true,
                'pades_byte_range' => $cscResult['byte_range'],
                'pades_cms_signature' => $cscResult['cms_signature'],
            ]);

            Log::channel('signature')
                ->info('CompletedDocumentSealingService: CSC PAdES signing applied', [
                    'document_id' => $document->id,
                    'signer_id' => $signer->id,
                ]);

            $document = $document->fresh(['signatures', 'documentSigners']);
            if ($document === null) {
                return null;
            }

            $verifiablePath = $document->verifiablePdfPath();
            if ($verifiablePath === null || ! Storage::disk($this->secureDiskName())->exists($verifiablePath)) {
                return null;
            }
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

    private function resolveLocalSecureDiskPath(string $path): string
    {
        $localPath = Storage::disk($this->secureDiskName())->path($path);
        if (! is_string($localPath) || $localPath === '') {
            throw new RuntimeException('Unable to resolve local secure disk path.');
        }

        return $localPath;
    }

    private function resolveFinalOutputPath(Document $document): string
    {
        return $this->resolveLocalSecureDiskPath(sprintf(
            'documents/generated/%d-pades-%s.pdf',
            $document->id,
            Str::uuid()->toString(),
        ));
    }

    private function relativeSecureDiskPath(string $absolutePath): string
    {
        $root = rtrim(str_replace('\\', '/', $this->resolveLocalSecureDiskPath('')), '/');
        $path = str_replace('\\', '/', $absolutePath);

        if (! str_starts_with($path, $root.'/')) {
            throw new RuntimeException('Signed PAdES PDF output path is outside the secure disk root.');
        }

        return ltrim(substr($path, strlen($root)), '/');
    }
}
