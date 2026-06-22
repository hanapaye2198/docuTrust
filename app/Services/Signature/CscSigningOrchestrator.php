<?php

namespace App\Services\Signature;

use App\Contracts\PadesSigningContract;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\Signature;
use Illuminate\Log\LogManager;
use RuntimeException;
use Throwable;

class CscSigningOrchestrator
{
    public function __construct(
        private readonly PadesSigningContract $padesService,
        private readonly CscApiClient $cscClient,
        private readonly LogManager $log,
        private readonly SadLifecycleService $sadService,
    ) {}

    /**
     * @return array{digest: string, byte_range: array{0: int, 1: int, 2: int, 3: int}, prepared_pdf_path: string, contents_offset: int, contents_length: int}
     */
    public function prepareDocumentDigest(string $stampedPdfPath): array
    {
        $digestData = $this->padesService->prepareDigest($stampedPdfPath);

        $this->log->channel('signature')->info("PAdES digest prepared for path: {$stampedPdfPath}");

        return $digestData;
    }

    public function requestCscSignature(
        string $accessToken,
        string $sad,
        string $credentialId,
        string $digest,
    ): string {
        $response = $this->cscClient->signHash(
            $accessToken,
            $sad,
            $credentialId,
            $digest,
        );

        $signatures = $response['signatures'] ?? null;
        if (! is_array($signatures) || ! isset($signatures[0]) || ! is_string($signatures[0]) || $signatures[0] === '') {
            throw new RuntimeException('CSC signHash returned no signature');
        }

        $this->log->channel('signature')->info("CSC signHash completed for credentialId: {$credentialId}");

        return $signatures[0];
    }

    /**
     * @param  array{0: int, 1: int, 2: int, 3: int}  $byteRange
     * @return array{success: bool, output_path: string, byte_range: array{0: int, 1: int, 2: int, 3: int}}
     */
    public function embedAndFinalize(
        string $preparedPdfPath,
        string $cmsSignatureBase64,
        array $byteRange,
        string $outputPath,
    ): array {
        $cmsBinary = base64_decode($cmsSignatureBase64, true);
        if ($cmsBinary === false) {
            throw new RuntimeException('CSC signHash returned an invalid base64 CMS signature');
        }

        $embedResult = $this->padesService->embedSignature(
            $preparedPdfPath,
            bin2hex($cmsBinary),
            $byteRange,
            $outputPath,
        );

        $this->log->channel('signature')->info("PAdES signature embedded. Output: {$outputPath}");

        return $embedResult;
    }

    /**
     * @param  string  $sad  Deprecated: SAD is consumed internally through SadLifecycleService.
     *
     * @deprecated The $sad parameter is kept for backward compatibility. SAD is consumed internally through SadLifecycleService.
     *
     * @param  array<string, mixed>  $credentialInfo
     * @return array<string, mixed>
     */
    public function orchestrate(
        Document $document,
        DocumentSigner $signer,
        string $stampedPdfPath,
        string $accessToken,
        string $sad,
        string $credentialId,
        string $outputPath,
        array $credentialInfo = [],
    ): array {
        try {
            $digestData = $this->prepareDocumentDigest($stampedPdfPath);
            $plainSad = $this->sadService->consumeSad(
                $document->id,
                $signer->id,
            );
            $cmsSignature = $this->requestCscSignature(
                $accessToken,
                $plainSad,
                $credentialId,
                $digestData['digest'],
            );
            $embedResult = $this->embedAndFinalize(
                $digestData['prepared_pdf_path'],
                $cmsSignature,
                $digestData['byte_range'],
                $outputPath,
            );

            $result = [
                'output_path' => $outputPath,
                'byte_range' => $digestData['byte_range'],
                'cms_signature' => $cmsSignature,
                'digest' => $digestData['digest'],
                'embed_result' => $embedResult,
                'ltv_applied' => false,
            ];

            $certificateChain = $credentialInfo['cert']['certificates'] ?? null;
            $ltvEnabled = config('signature.ltv_enabled', false);
            if ($ltvEnabled && is_array($certificateChain) && $certificateChain !== []) {
                $ltvEmbedder = app(LtvEmbedder::class);
                $ltvOutputPath = str_replace('.pdf', '_ltv.pdf', $outputPath);
                $ltvResult = $ltvEmbedder->embedLtv(
                    padesSignedPdfPath: $outputPath,
                    cmsSignatureBase64: $cmsSignature,
                    certificateChainPem: $certificateChain,
                    outputPath: $ltvOutputPath,
                );
                $result['ltv_output_path'] = $ltvResult['output_path'];
                $result['ltv_dss_path'] = $ltvResult['output_path'].'.dss.json';
                $result['ltv_applied'] = true;
            }

            $signedAt = now();
            $signature = Signature::query()
                ->where('document_id', $document->id)
                ->where('signer_id', $signer->id)
                ->latest()
                ->first();

            if ($signature) {
                $signature->update([
                    'cms_signature' => $cmsSignature,
                    'byte_range' => $digestData['byte_range'],
                    'digest_algorithm' => 'SHA-256',
                    'signing_time' => $signedAt,
                    'pades_profile' => config('signature.ltv_enabled', false) ? 'B-LT' : 'B-B',
                    'csc_credential_id' => $credentialId,
                    'validation_status' => 'valid',
                    'validated_at' => $signedAt,
                    'ltv_applied' => $result['ltv_applied'] ?? false,
                    'ltv_dss_path' => $result['ltv_dss_path'] ?? null,
                ]);
            }

            $signer->update([
                'csc_signing_completed' => true,
                'csc_signing_completed_at' => $signedAt,
            ]);

            $this->log->channel('signature')->info(
                "CSC orchestration complete for document ID: {$document->id}, signer ID: {$signer->id}"
            );

            return $result;
        } catch (Throwable $throwable) {
            $this->log->channel('signature')->error('CSC orchestration failed', [
                'document_id' => $document->id,
                'signer_id' => $signer->id,
                'stamped_pdf_path' => $stampedPdfPath,
                'credential_id' => $credentialId,
                'output_path' => $outputPath,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);

            throw $throwable;
        }
    }
}
