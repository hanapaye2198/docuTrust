<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\NotaryRequest;
use App\Models\Payment;
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

        $expiresAt = (int) $request->query('expires', now()->addDays(7)->timestamp);
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
            && $latestPayment->status === PaymentStatus::Pending
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
        $payment = Payment::query()
            ->where('notary_request_id', $notaryRequest->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        $checkoutUrl = $payment?->checkout_url ?? $payment?->redirect_url;
        $isActive = $payment instanceof Payment
            && $payment->status === PaymentStatus::Pending
            && ($payment->expires_at === null || $payment->expires_at->isFuture())
            && is_string($checkoutUrl)
            && $checkoutUrl !== '';

        if ($isActive) {
            return redirect()->away($checkoutUrl);
        }

        return redirect()
            ->to(URL::temporarySignedRoute(
                'public.notary.payment.show',
                Carbon::createFromTimestamp((int) $request->query('expires', now()->addDays(7)->timestamp)),
                ['notaryRequest' => $notaryRequest->id],
            ))
            ->with('status', __('Ask your attorney to generate a fresh payment link before paying.'));
    }
}
