<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\NotaryRequest;
use App\Models\Payment;
use App\Services\GatewayHubService;
use App\Services\NotaryPaymentService;
use App\Services\NotaryRequestWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        } catch (\RuntimeException) {
            $enabledGateways = [];
        }

        $expiresAt = (int) $request->query('expires', now()->addDays(7)->timestamp);
        $postUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'public.notary.payment.checkout',
            \Illuminate\Support\Carbon::createFromTimestamp($expiresAt),
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
            ->to(\Illuminate\Support\Facades\URL::temporarySignedRoute(
                'public.notary.payment.show',
                \Illuminate\Support\Carbon::createFromTimestamp((int) $request->query('expires', now()->addDays(7)->timestamp)),
                ['notaryRequest' => $notaryRequest->id],
            ))
            ->with('status', __('Payment link created. Scan the QR code or open checkout below to continue.'));
    }
}
