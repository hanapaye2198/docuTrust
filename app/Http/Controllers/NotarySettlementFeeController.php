<?php

namespace App\Http\Controllers;

use App\Models\NotaryRequest;
use App\Services\AttorneyNotarialRegistryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotarySettlementFeeController extends Controller
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
            'settlement_fee' => ['required', 'numeric', 'gt:0'],
        ]);

        app(AttorneyNotarialRegistryService::class)->saveSettlementFee(
            $notaryRequest->fresh(['attorneyNotarialRegistry', 'documents.documentSigners', 'signers', 'identityVerifications']),
            $user,
            (float) $validated['settlement_fee'],
        );

        return redirect()
            ->route('notary.requests.show', ['notaryRequest' => $notaryRequest, 'tab' => 'closing'])
            ->with('status', __('Notarial fee saved. Create a payment link below if a fee applies.'));
    }
}
