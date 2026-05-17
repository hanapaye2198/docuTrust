<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CmpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CMP API Controller
 * 
 * Implements PKIX-CMP (Certificate Management Protocol) endpoints.
 */
class CmpController extends Controller
{
    public function __construct(private readonly CmpService $cmpService) {}

    /**
     * Handle CMP message (RFC 6712 - HTTP Transfer for CMP)
     *
     * Accepts application/pkixcmp content type with DER-encoded PKIMessage.
     *
     * @param Request $request
     * @return JsonResponse|\Illuminate\Http\Response
     */
    public function handleCmpMessage(Request $request): JsonResponse|\Illuminate\Http\Response
    {
        $contentType = $request->header('Content-Type', '');

        // RFC 6712: CMP over HTTP uses application/pkixcmp
        if (str_contains($contentType, 'application/pkixcmp')) {
            return $this->handleDerMessage($request);
        }

        // Fallback: JSON-based for API consumers
        $request->validate([
            'message' => 'required|string',
            'operation' => 'required|in:Enrollment,Revocation,KeyUpdate,Confirmation',
        ]);

        $operation = $request->operation;
        $message = $request->message;

        try {
            switch ($operation) {
                case 'Enrollment':
                    return $this->handleEnrollment($message);
                case 'Revocation':
                    return $this->handleRevocation($message);
                case 'KeyUpdate':
                    return $this->handleKeyUpdate($message);
                case 'Confirmation':
                    return $this->handleConfirmation($message);
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
     * Handle DER-encoded CMP message (RFC 6712 compliant).
     */
    private function handleDerMessage(Request $request): \Illuminate\Http\Response
    {
        $derMessage = $request->getContent();

        if (empty($derMessage)) {
            return response('', 400)
                ->header('Content-Type', 'application/pkixcmp');
        }

        try {
            $parsed = $this->cmpService->parseMessage($derMessage);

            // Route based on message type
            $responseBody = match ($parsed['messageType']) {
                CmpService::TYPE_IR, CmpService::TYPE_CR => $this->processCertRequest($parsed),
                CmpService::TYPE_KUR => $this->processKeyUpdate($parsed),
                CmpService::TYPE_RR => $this->processRevocation($parsed),
                CmpService::TYPE_CERTCONF => $this->processConfirmation($parsed),
                default => $this->buildErrorResponse($parsed['transactionId'], 'Unsupported message type'),
            };

            return response($responseBody, 200)
                ->header('Content-Type', 'application/pkixcmp');
        } catch (\Throwable $e) {
            return response('', 500)
                ->header('Content-Type', 'application/pkixcmp');
        }
    }

    /**
     * Process certificate request and return DER-encoded response.
     */
    private function processCertRequest(array $parsed): string
    {
        $result = $this->cmpService->handleEnrollmentRequest($parsed);

        return $this->cmpService->buildPkiMessage(
            CmpService::TYPE_CP,
            $result['certificate'] ?? '',
            hex2bin($parsed['transactionId']),
            $parsed['senderNonce'] ? hex2bin($parsed['senderNonce']) : null
        );
    }

    /**
     * Process key update and return DER-encoded response.
     */
    private function processKeyUpdate(array $parsed): string
    {
        return $this->cmpService->buildPkiMessage(
            CmpService::TYPE_KUP,
            '',
            hex2bin($parsed['transactionId'])
        );
    }

    /**
     * Process revocation and return DER-encoded response.
     */
    private function processRevocation(array $parsed): string
    {
        $result = $this->cmpService->handleRevocationRequest($parsed);

        return $this->cmpService->buildPkiMessage(
            CmpService::TYPE_RP,
            '',
            hex2bin($parsed['transactionId'])
        );
    }

    /**
     * Process confirmation and return DER-encoded response.
     */
    private function processConfirmation(array $parsed): string
    {
        return $this->cmpService->buildPkiMessage(
            CmpService::TYPE_PKICONF,
            '',
            hex2bin($parsed['transactionId'])
        );
    }

    /**
     * Build error response.
     */
    private function buildErrorResponse(string $transactionId, string $errorText): string
    {
        return $this->cmpService->buildPkiMessage(
            CmpService::TYPE_ERROR,
            $errorText,
            hex2bin($transactionId)
        );
    }

    /**
     * Handle enrollment request
     *
     * @param string $message Base64-encoded CMP message
     * @return JsonResponse
     */
    private function handleEnrollment(string $message): JsonResponse
    {
        try {
            $pkiMessage = $this->cmpService->parseMessage($message);

            // Process enrollment
            $result = $this->cmpService->handleEnrollmentRequest($pkiMessage);

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
     * Handle revocation request
     *
     * @param string $message Base64-encoded CMP message
     * @return JsonResponse
     */
    private function handleRevocation(string $message): JsonResponse
    {
        try {
            $pkiMessage = $this->cmpService->parseMessage($message);

            $result = $this->cmpService->handleRevocationRequest($pkiMessage);

            return response()->json([
                'status' => $result['status'],
                'errorMessage' => $result['errorMessage'],
                'transactionId' => $pkiMessage['transactionId'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Revocation failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle key update request
     *
     * @param string $message Base64-encoded CMP message
     * @return JsonResponse
     */
    private function handleKeyUpdate(string $message): JsonResponse
    {
        try {
            $pkiMessage = $this->cmpService->parseMessage($message);

            // Process key update
            return response()->json([
                'status' => 'pending',
                'transactionId' => $pkiMessage['transactionId'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Key update failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle confirmation message
     *
     * @param string $message Base64-encoded CMP message
     * @return JsonResponse
     */
    private function handleConfirmation(string $message): JsonResponse
    {
        try {
            $pkiMessage = $this->cmpService->parseMessage($message);

            return response()->json([
                'status' => 'success',
                'transactionId' => $pkiMessage['transactionId'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Confirmation failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get CA information
     *
     * @return JsonResponse
     */
    public function getCaInfo(): JsonResponse
    {
        $caInfo = $this->cmpService->getCaInfo();

        return response()->json($caInfo);
    }
}
