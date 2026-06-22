<?php

namespace App\Services\Signature;

use Illuminate\Log\LogManager;
use RuntimeException;
use Throwable;

class LtvEmbedder
{
    public function __construct(
        private readonly TimestampAuthorityClient $tsaClient,
        private readonly LogManager $log,
    ) {}

    /**
     * @param  array<int, string>  $certificateChainPem
     * @return array{output_path: string, cert_count: int, has_timestamp: bool, tsr_bytes_length: int}
     */
    public function embedLtv(
        string $padesSignedPdfPath,
        string $cmsSignatureBase64,
        array $certificateChainPem,
        string $outputPath,
    ): array {
        $ocspResponses = [];
        foreach ($certificateChainPem as $certPem) {
            $ocspResponses[] = $this->fetchOcspResponse($certPem);
        }

        $sigBytes = base64_decode($cmsSignatureBase64, true);
        if ($sigBytes === false) {
            throw new RuntimeException('Unable to decode CMS signature for LTV timestamping.');
        }

        $sigDigest = hash('sha256', $sigBytes);
        $tsrBytes = $this->tsaClient->requestTimestamp($sigDigest);
        $dssData = $this->buildDssDictionary(
            $certificateChainPem,
            $ocspResponses,
            $tsrBytes,
        );

        $this->appendDssToPdf(
            $padesSignedPdfPath,
            $dssData,
            $outputPath,
        );

        $this->log->channel('signature')->info("LTV embedding complete for path: {$padesSignedPdfPath}");

        return [
            'output_path' => $outputPath,
            'cert_count' => count($certificateChainPem),
            'has_timestamp' => true,
            'tsr_bytes_length' => strlen($tsrBytes),
        ];
    }

    public function fetchOcspResponse(string $certPem): ?string
    {
        try {
            $cert = openssl_x509_read($certPem);
            if ($cert === false) {
                $this->log->channel('signature')->warning('Unable to read certificate for OCSP lookup.');

                return null;
            }

            $parsed = openssl_x509_parse($cert);
            if (! is_array($parsed)) {
                $this->log->channel('signature')->warning('Unable to parse certificate for OCSP lookup.');

                return null;
            }

            $ocspUrl = $this->extractOcspUrl($parsed);
            if ($ocspUrl === null) {
                $this->log->channel('signature')->warning('No OCSP responder URL found in certificate AIA extension.');

                return null;
            }

            $this->log->channel('signature')->warning('OCSP request generation stubbed — issuer certificate required for full OCSP DER request.', [
                'ocsp_url' => $ocspUrl,
            ]);

            return null;
        } catch (Throwable $throwable) {
            $this->log->channel('signature')->warning('OCSP response fetch failed', [
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<int, string>  $certsPem
     * @param  array<int, ?string>  $ocspResponses
     * @return array{certs: array<int, string>, ocsps: array<int, string>, crls: array<int, string>, vri: array<int, mixed>, tsr: string}
     */
    public function buildDssDictionary(array $certsPem, array $ocspResponses, string $tsrBytes): array
    {
        return [
            'certs' => array_values(array_map(
                fn (string $certPem): string => base64_encode($this->pemCertificateToDer($certPem)),
                $certsPem,
            )),
            'ocsps' => array_values(array_map(
                fn (string $ocspResponse): string => base64_encode($ocspResponse),
                array_filter($ocspResponses, fn (?string $response): bool => $response !== null),
            )),
            'crls' => [],
            'vri' => [],
            'tsr' => base64_encode($tsrBytes),
        ];
    }

    /**
     * TODO: Replace this sidecar stub with a real PDF incremental update that
     * appends DSS stream objects, xref entries, and an updated trailer.
     *
     * @param  array<string, mixed>  $dssData
     */
    public function appendDssToPdf(string $inputPath, array $dssData, string $outputPath): void
    {
        if (! is_file($inputPath) || ! is_readable($inputPath)) {
            throw new RuntimeException("Unable to read PAdES signed PDF for LTV embedding: {$inputPath}");
        }

        $outputDirectory = dirname($outputPath);
        if (! is_dir($outputDirectory) || ! is_writable($outputDirectory)) {
            throw new RuntimeException("Unable to write LTV output PDF to directory: {$outputDirectory}");
        }

        if (! copy($inputPath, $outputPath)) {
            throw new RuntimeException("Unable to copy PAdES signed PDF to LTV output path: {$outputPath}");
        }

        $json = json_encode($dssData, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        if (file_put_contents($outputPath.'.dss.json', $json) === false) {
            throw new RuntimeException("Unable to write DSS sidecar file: {$outputPath}.dss.json");
        }

        $this->log->channel('signature')->warning(
            'LTV DSS embedding stubbed — PDF library required for full implementation. DSS data written to sidecar.',
            [
                'input_path' => $inputPath,
                'output_path' => $outputPath,
                'sidecar_path' => $outputPath.'.dss.json',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $parsedCertificate
     */
    private function extractOcspUrl(array $parsedCertificate): ?string
    {
        $aia = $parsedCertificate['extensions']['authorityInfoAccess'] ?? null;
        if (! is_string($aia) || $aia === '') {
            return null;
        }

        if (preg_match('/OCSP\s*-\s*URI:(?<url>\S+)/i', $aia, $matches)) {
            return rtrim((string) $matches['url']);
        }

        if (preg_match('/OCSP.*?(?<url>https?:\/\/\S+)/i', $aia, $matches)) {
            return rtrim((string) $matches['url']);
        }

        return null;
    }

    private function pemCertificateToDer(string $certPem): string
    {
        $base64 = preg_replace('/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s+/', '', $certPem);
        if (! is_string($base64) || $base64 === '') {
            throw new RuntimeException('Certificate PEM is empty or invalid.');
        }

        $der = base64_decode($base64, true);
        if ($der === false) {
            throw new RuntimeException('Unable to decode certificate PEM to DER.');
        }

        return $der;
    }
}
