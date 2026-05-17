<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ScepService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SCEP API Controller
 * 
 * Implements SCEP (Simple Certificate Enrollment Protocol) endpoints.
 */
class ScepController extends Controller
{
    public function __construct(private readonly ScepService $scepService) {}

    /**
     * Handle GETCA request - Get CA information
     *
     * @return JsonResponse
     */
    public function getCa(): JsonResponse
    {
        $caInfo = $this->scepService->getCaInfo();

        return response()->json($caInfo);
    }

    /**
     * Handle PKI message - Enrollment, GetCert, GetCRL
     * Supports both binary (application/x-pki-message) and JSON formats.
     *
     * @param Request $request
     * @return JsonResponse|\Illuminate\Http\Response
     */
    public function handlePkiMessage(Request $request): JsonResponse|\Illuminate\Http\Response
    {
        $contentType = $request->header('Content-Type', '');

        // RFC 8894: Binary SCEP message format
        if (str_contains($contentType, 'application/x-pki-message')) {
            return $this->handleBinaryPkiMessage($request);
        }

        // JSON fallback
        $request->validate([
            'message' => 'required|string',
            'operation' => 'required|in:PKCSReq,GetCert,GetCRL',
        ]);

        $operation = $request->operation;
        $message = $request->message;

        try {
            switch ($operation) {
                case 'PKCSReq':
                    return $this->handleEnrollment($message);
                case 'GetCert':
                    return $this->handleGetCert($message);
                case 'GetCRL':
                    return $this->handleGetCrl($message);
                default:
                    return response()->json([
                        'error' => 'Unknown operation',
                    ], 400);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Processing failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle binary SCEP PKI message (RFC 8894 compliant).
     */
    private function handleBinaryPkiMessage(Request $request): \Illuminate\Http\Response
    {
        $binaryMessage = $request->getContent();

        if (empty($binaryMessage)) {
            return response('', 400)
                ->header('Content-Type', 'application/x-pki-message');
        }

        try {
            $parsed = $this->scepService->parsePkiMessage(base64_encode($binaryMessage));

            $result = $this->scepService->handleEnrollmentRequest($parsed);

            // Build SCEP response as PKCS#7
            $pkiStatus = match ($result['status']) {
                'success' => 0,
                'pending' => 3,
                default => 2,
            };

            $response = $this->scepService->buildScepResponse(
                $pkiStatus,
                $parsed['transactionId'],
                $parsed['senderNonce'],
                $result['certificate'] ? base64_decode($result['certificate']) : null
            );

            return response($response, 200)
                ->header('Content-Type', 'application/x-pki-message');
        } catch (\Throwable $e) {
            return response('', 500)
                ->header('Content-Type', 'application/x-pki-message');
        }
    }

    /**
     * Handle enrollment request
     *
     * @param string $message Base64-encoded PKI message
     * @return JsonResponse
     */
    private function handleEnrollment(string $message): JsonResponse
    {
        try {
            $pkiMessage = $this->scepService->parsePkiMessage($message);

            // Process enrollment
            $result = $this->scepService->handleEnrollmentRequest($pkiMessage);

            return response()->json([
                'status' => $result['status'],
                'certificate' => $result['certificate'],
                'errorMessage' => $result['errorMessage'],
                'transactionId' => $pkiMessage['transactionId'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Enrollment failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle GetCert request
     *
     * @param string $message Base64-encoded message
     * @return JsonResponse
     */
    private function handleGetCert(string $message): JsonResponse
    {
        try {
            $pkiMessage = $this->scepService->parsePkiMessage($message);

            // Extract serial number from message
            $serialNumber = $pkiMessage['transactionId'];

            $result = $this->scepService->handleGetCert($serialNumber);

            return response()->json([
                'status' => $result['status'],
                'certificate' => $result['certificate'],
                'transactionId' => $pkiMessage['transactionId'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'GetCert failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle GetCRL request
     *
     * @return JsonResponse
     */
    private function handleGetCrl(string $message): JsonResponse
    {
        try {
            $crlGenerator = new \App\Services\CrlGenerator();
            $crl = $crlGenerator->getPemFormat();

            return response()
                ->string($crl)
                ->header('Content-Type', 'application/x-pkcs7-crl');
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'GetCRL failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get SCEP CA certificate
     *
     * @return JsonResponse
     */
    public function getCaCertificate(): JsonResponse
    {
        try {
            $caService = new \App\Services\CertificateAuthorityService(
                app(\App\Contracts\CertificateAuthorityKeyStore::class)
            );

            $ca = $caService->getOrCreateRootAuthority();

            return response()
                ->string($ca->certificate_pem)
                ->header('Content-Type', 'application/x-x509-ca-cert');
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to get CA certificate',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
