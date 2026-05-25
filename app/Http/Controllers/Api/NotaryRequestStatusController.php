<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotaryRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotaryRequestStatusController extends Controller
{
    /**
     * Return a lightweight JSON payload with the current status of a notary request.
     * Used by the frontend AJAX polling to update the UI without a full page refresh.
     *
     * GET /api/notary-requests/{notaryRequest}/status
     */
    public function __invoke(Request $request, NotaryRequest $notaryRequest): JsonResponse
    {
        $this->authorize('view', $notaryRequest);

        $notaryRequest->loadMissing([
            'signers',
            'documents.documentSigners',
            'sessions',
            'identityVerifications',
        ]);

        $signers = $notaryRequest->signers->map(fn ($signer) => [
            'id' => $signer->id,
            'name' => $signer->full_name,
            'email' => $signer->email,
            'signing_status' => $this->resolveSignerSigningStatus($notaryRequest, $signer),
            'identity_status' => $this->resolveSignerIdentityStatus($signer),
        ]);

        $documents = $notaryRequest->documents->map(fn ($doc) => [
            'id' => $doc->id,
            'title' => $doc->title,
            'status' => $doc->status->value,
            'signers_signed' => $doc->documentSigners->filter(fn ($s) => $s->hasCompletedAction())->count(),
            'signers_total' => $doc->documentSigners->filter(fn ($s) => $s->isSigner())->count(),
        ]);

        $latestSession = $notaryRequest->sessions->first();

        return response()->json([
            'status' => $notaryRequest->status->value,
            'updated_at' => $notaryRequest->updated_at?->toIso8601String(),
            'identity_verified_at' => $notaryRequest->identity_verified_at?->toIso8601String(),
            'location_verified_at' => $notaryRequest->location_verified_at?->toIso8601String(),
            'signers' => $signers,
            'documents' => $documents,
            'session' => $latestSession ? [
                'id' => $latestSession->id,
                'status' => $latestSession->status ?? null,
                'scheduled_for' => $latestSession->scheduled_for?->toIso8601String(),
            ] : null,
        ]);
    }

    private function resolveSignerSigningStatus(NotaryRequest $request, $signer): ?string
    {
        foreach ($request->documents as $document) {
            $docSigner = $document->documentSigners
                ->first(fn ($ds) => strtolower(trim($ds->email)) === strtolower(trim($signer->email)));

            if ($docSigner !== null && $docSigner->hasCompletedAction()) {
                return 'signed';
            }

            if ($docSigner !== null) {
                return $docSigner->status?->value ?? 'pending';
            }
        }

        return 'not_assigned';
    }

    private function resolveSignerIdentityStatus($signer): ?string
    {
        $verification = $signer->identityVerifications()
            ->latest()
            ->first();

        return $verification?->verification_status?->value;
    }
}
