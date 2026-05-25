<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Ekyc\Sumsub\SumsubWebhookHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SumsubWebhookController extends Controller
{
    public function __construct(private readonly SumsubWebhookHandler $handler) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->isSignatureValid($request)) {
            Log::warning('Sumsub webhook signature verification failed.', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        $payload = $request->json()->all();
        $type = $payload['type'] ?? null;
        $applicantId = $payload['applicantId'] ?? '';
        $externalUserId = $payload['externalUserId'] ?? '';

        Log::info('Sumsub webhook received.', [
            'type' => $type,
            'applicant_id' => $applicantId,
            'external_user_id' => $externalUserId,
        ]);

        match ($type) {
            'applicantReviewed' => $this->handler->handleReviewed($applicantId, $externalUserId, $payload),
            'applicantPending' => $this->handler->handlePending($applicantId, $externalUserId, $payload),
            default => Log::debug('Sumsub webhook type ignored.', ['type' => $type]),
        };

        return response()->json(['status' => 'ok']);
    }

    /**
     * Validate the webhook payload signature using HMAC-SHA1.
     *
     * Sumsub signs webhook payloads with the webhook secret key using HMAC-SHA1
     * and sends the digest in the X-Payload-Digest header (hex-encoded).
     */
    private function isSignatureValid(Request $request): bool
    {
        $secret = (string) config('ekyc.sumsub.webhook_secret');

        if ($secret === '') {
            Log::error('Sumsub webhook_secret is not configured.');

            return false;
        }

        $providedDigest = $request->header('X-Payload-Digest', '');

        if ($providedDigest === '' || $providedDigest === null) {
            return false;
        }

        $computedDigest = hash_hmac('sha1', $request->getContent(), $secret);

        return hash_equals($computedDigest, $providedDigest);
    }
}
