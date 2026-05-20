<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GatewayHubService;
use App\Services\NotaryPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GatewayHubWebhookController extends Controller
{
    public function __construct(
        private readonly GatewayHubService $gatewayHubService,
        private readonly NotaryPaymentService $notaryPaymentService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $timestamp = $request->header('X-Merchant-Timestamp');
        $signature = $request->header('X-Merchant-Signature');

        if (! $this->gatewayHubService->verifyWebhookSignature($timestamp, $signature, $rawBody)) {
            Log::warning('GatewayHub webhook signature verification failed.', [
                'timestamp' => $timestamp,
                'signature_present' => $signature !== null && $signature !== '',
                'body_length' => strlen($rawBody),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'message' => 'Invalid webhook signature.',
            ], 401);
        }

        $payload = $request->json()->all();
        if (! is_array($payload) || ($payload['event'] ?? null) !== 'payment.updated') {
            Log::info('GatewayHub webhook event ignored.', [
                'event' => is_array($payload) ? ($payload['event'] ?? null) : null,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Event ignored.',
            ], 202);
        }

        Log::info('GatewayHub webhook received.', [
            'payment_id' => $payload['data']['payment_id'] ?? null,
            'reference' => $payload['data']['reference'] ?? null,
            'status' => $payload['data']['status'] ?? null,
        ]);

        $payment = $this->notaryPaymentService->handleGatewayWebhook($payload);

        if ($payment === null) {
            Log::warning('GatewayHub webhook payment not found.', [
                'payment_id' => $payload['data']['payment_id'] ?? null,
                'reference' => $payload['data']['reference'] ?? null,
            ]);

            return response()->json([
                'message' => 'Webhook received.',
            ], 202);
        }

        return response()->json([
            'message' => 'Webhook processed.',
            'payment_id' => $payment->id,
            'status' => $payment->status->value,
        ]);
    }
}
