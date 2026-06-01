<?php

namespace App\Http\Controllers;

use App\Models\NotaryRequest;
use App\Services\NotaryNotificationService;
use App\Services\NotaryPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotaryPaymentLinkController extends Controller
{
    public function __invoke(Request $request, NotaryRequest $notaryRequest): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);
        abort_unless(
            $user->role->value === 'notary'
                && (int) $notaryRequest->notary_user_id === (int) $user->id,
            403
        );

        $validated = $request->validate([
            'payment_gateway' => ['required', 'string', 'max:50'],
        ]);

        $payment = app(NotaryPaymentService::class)->createGatewayPayment(
            $notaryRequest->fresh(['registerEntries', 'payments', 'attorneyNotarialRegistry']),
            (string) $validated['payment_gateway'],
            (int) $user->id,
        );

        app(NotaryNotificationService::class)->notifyPaymentReady(
            $notaryRequest->fresh(['requester', 'notary']),
            $payment,
        );

        session()->put('notary_payment_reminder_sent.'.$payment->id, now()->timestamp);

        return redirect()
            ->route('notary.requests.show', ['notaryRequest' => $notaryRequest, 'tab' => 'closing', 'section' => 'payment'])
            ->with('status', $payment->wasRecentlyCreated
                ? __('Payment link created. Share the checkout link or QR code with the client.')
                : __('An active pending payment already exists. Payment email was sent again to the client.'));
    }
}
