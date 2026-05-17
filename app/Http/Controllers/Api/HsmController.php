<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HsmAuditLogger;
use App\Services\HsmKeyManager;
use App\Services\HsmPkiSignatureService;
use App\Services\HsmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * HSM API Controller
 * 
 * Provides HSM operations through the Virtual Gateway.
 */
class HsmController extends Controller
{
    public function __construct(
        private readonly HsmService $hsmService,
        private readonly HsmKeyManager $hsmKeyManager,
        private readonly HsmPkiSignatureService $pkiSignatureService,
        private readonly HsmAuditLogger $auditLogger,
    ) {}

    /**
     * Sign a hash using HSM
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sign(Request $request): JsonResponse
    {
        $request->validate([
            'hash' => 'required|string|size:64',
            'key_id' => 'required|string',
        ]);

        try {
            $signature = $this->hsmService->sign($request->hash, $request->key_id);

            $this->auditLogger->logKeySign(
                $request->key_id,
                $request->hash,
                null,
                null,
                $request->user()?->id
            );

            return response()->json([
                'success' => true,
                'signature' => $signature,
                'algorithm' => 'RSA-SHA256',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Signing failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify a signature using HSM
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'hash' => 'required|string|size:64',
            'signature' => 'required|string',
            'key_id' => 'required|string',
        ]);

        $verified = $this->hsmService->verify($request->hash, $request->signature, $request->key_id);

        $this->auditLogger->logKeyVerify(
            $request->key_id,
            $request->hash,
            $verified,
            null,
            null,
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'verified' => $verified,
        ]);
    }

    /**
     * Generate RSA key pair in HSM
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generateKey(Request $request): JsonResponse
    {
        $request->validate([
            'key_size' => 'nullable|integer|min:2048|max:4096',
        ]);

        $keySize = $request->input('key_size', 2048);

        try {
            $keyPair = $this->hsmService->generateRsaKeyPair($keySize);

            $this->auditLogger->logKeyGeneration(
                $keyPair['privateKeyId'],
                null,
                null,
                $request->user()?->id
            );

            return response()->json([
                'success' => true,
                'key_pair' => $keyPair,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Key generation failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get public key from HSM
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getPublicKey(Request $request): JsonResponse
    {
        $request->validate([
            'key_id' => 'required|string',
        ]);

        try {
            $publicKey = $this->hsmService->getPublicKey($request->key_id);

            return response()->json([
                'success' => true,
                'public_key' => $publicKey,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Key not found',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Destroy key in HSM
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function destroyKey(Request $request): JsonResponse
    {
        $request->validate([
            'key_id' => 'required|string',
        ]);

        $destroyed = $this->hsmService->destroyKey($request->key_id);

        if ($destroyed) {
            $this->auditLogger->logKeyDestruction(
                $request->key_id,
                null,
                null,
                $request->user()?->id
            );
        }

        return response()->json([
            'success' => $destroyed,
            'destroyed' => $destroyed,
        ]);
    }

    /**
     * Get HSM status
     *
     * @return JsonResponse
     */
    public function status(): JsonResponse
    {
        $status = $this->hsmService->getStatus();
        $slotInfo = $this->hsmService->getSlotInfo();

        return response()->json([
            'status' => $status,
            'slot_info' => $slotInfo,
        ]);
    }
}
