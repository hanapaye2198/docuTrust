<?php

namespace App\Http\Controllers;

use App\Models\NotaryRequest;
use App\Models\Payment;
use App\Services\GatewayHubService;
use App\Services\NotaryPaymentService;
use App\Services\NotaryRequestWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class PublicNotaryPaymentController extends Controller
{
    public function show(Request $request, NotaryRequest $notaryRequest): View
    {
        $notaryRequest->loadMissing(['requester', 'notary', 'registerEntries', 'payments', 'attorneyNotarialRegistry']);

        $workflow = app(NotaryRequestWorkflowService::class);
        $latestPayment = Payment::query()
            ->where('notary_request_id', $notaryRequest->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        try {
            $enabledGateways = app(GatewayHubService::class)->enabledGateways();
        } catch (\Throwable $exception) {
            $enabledGateways = [];
            report($exception);
        }

        $expiresAt = (int) $request->query('expires', now()->addDays(7)->timestamp);
        $postUrl = URL::temporarySignedRoute(
            'public.notary.payment.checkout',
            Carbon::createFromTimestamp($expiresAt),
            ['notaryRequest' => $notaryRequest->id],
        );
        $statusUrl = URL::temporarySignedRoute(
            'public.notary.payment.status',
            Carbon::createFromTimestamp($expiresAt),
            ['notaryRequest' => $notaryRequest->id],
        );

        return view('notary.public-payment', [
            'notaryRequest' => $notaryRequest,
            'latestPayment' => $latestPayment,
            'paymentRequired' => $workflow->paymentRequired($notaryRequest),
            'hasSettledPayment' => $workflow->hasSettledPayment($notaryRequest),
            'settlementDueAmount' => $workflow->settlementDueAmount($notaryRequest),
            'enabledGateways' => $enabledGateways,
            'postUrl' => $postUrl,
            'statusUrl' => $statusUrl,
        ]);
    }

    public function status(Request $request, NotaryRequest $notaryRequest): JsonResponse
    {
        $notaryRequest->loadMissing(['payments', 'registerEntries', 'attorneyNotarialRegistry']);

        $workflow = app(NotaryRequestWorkflowService::class);
        $latestPayment = Payment::query()
            ->where('notary_request_id', $notaryRequest->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        $currentPaymentExpired = $latestPayment instanceof Payment
            && $latestPayment->status === \App\Enums\PaymentStatus::Pending
            && $latestPayment->expires_at?->isPast();

        return response()->json([
            'request_id' => $notaryRequest->id,
            'has_settled_payment' => $workflow->hasSettledPayment($notaryRequest),
            'payment_required' => $workflow->paymentRequired($notaryRequest),
            'payment_id' => $latestPayment?->id,
            'payment_status' => $currentPaymentExpired ? 'expired' : $latestPayment?->status?->value,
            'payment_updated_at' => $latestPayment?->updated_at?->toIso8601String(),
            'status_page_url' => URL::temporarySignedRoute(
                'public.notary.payment.show',
                Carbon::createFromTimestamp((int) $request->query('expires', now()->addDays(7)->timestamp)),
                ['notaryRequest' => $notaryRequest->id],
            ),
        ]);
    }

    public function checkout(Request $request, NotaryRequest $notaryRequest): RedirectResponse
    {
        $validated = $request->validate([
            'payment_gateway' => ['required', 'string', 'max:50'],
        ]);

        $payment = app(NotaryPaymentService::class)->createGatewayPayment(
            $notaryRequest->fresh(['registerEntries', 'payments', 'attorneyNotarialRegistry']),
            (string) $validated['payment_gateway'],
            null,
        );

        return redirect()
            ->to(URL::temporarySignedRoute(
                'public.notary.payment.show',
                Carbon::createFromTimestamp((int) $request->query('expires', now()->addDays(7)->timestamp)),
                ['notaryRequest' => $notaryRequest->id],
            ))
            ->with('status', __('Payment link created. Scan the QR code or open checkout below to continue.'));
    }
}
