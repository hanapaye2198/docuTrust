<?php

namespace App\Services;

use App\Models\NotaryRequest;
use App\Models\NotarySigner;
use App\Models\Payment;

class NotaryRequestStatusPayloadService
{
    /**
     * @return array<string, mixed>
     */
    public function build(NotaryRequest $notaryRequest): array
    {
        $notaryRequest->loadMissing([
            'signers',
            'documents.documentSigners',
            'sessions.notarySigner',
            'identityVerifications',
            'payments',
        ]);

        $signers = $notaryRequest->signers->map(fn (NotarySigner $signer): array => [
            'id' => $signer->id,
            'name' => $signer->full_name,
            'email' => $signer->email,
            'signing_status' => $this->resolveSignerSigningStatus($notaryRequest, $signer),
            'identity_status' => $this->resolveSignerIdentityStatus($signer),
        ])->values()->all();

        $documents = $notaryRequest->documents->map(fn ($doc): array => [
            'id' => $doc->id,
            'title' => $doc->title,
            'status' => $doc->status->value,
            'signers_signed' => $doc->documentSigners->filter(fn ($s) => $s->hasCompletedAction())->count(),
            'signers_total' => $doc->documentSigners->filter(fn ($s) => $s->isSigner())->count(),
        ])->values()->all();

        $sessions = $notaryRequest->sessions
            ->sortByDesc(fn ($session) => $session->updated_at?->getTimestamp() ?? 0)
            ->values()
            ->map(fn ($session): array => [
                'id' => $session->id,
                'status' => $session->status ?? null,
                'notary_signer_id' => $session->notary_signer_id,
                'party_name' => $session->notarySigner?->full_name,
                'signer_confirmed' => (bool) $session->signer_confirmed,
                'signer_waiting' => $session->status === 'scheduled' && (bool) $session->signer_confirmed,
                'scheduled_for' => $session->scheduled_for?->toIso8601String(),
                'started_at' => $session->started_at?->toIso8601String(),
                'updated_at' => $session->updated_at?->toIso8601String(),
            ])
            ->all();

        $highlightedSession = collect($sessions)
            ->sortBy(fn (array $session): int => match ($session['status'] ?? '') {
                'in_progress' => 0,
                default => ($session['signer_waiting'] ?? false) ? 1 : 2,
            })
            ->first();

        $latestPayment = $notaryRequest->payments
            ->sortByDesc(fn (Payment $payment) => $payment->created_at?->getTimestamp() ?? 0)
            ->first();

        return [
            'request_id' => $notaryRequest->id,
            'status' => $notaryRequest->status->value,
            'updated_at' => $notaryRequest->updated_at?->toIso8601String(),
            'identity_verified_at' => $notaryRequest->identity_verified_at?->toIso8601String(),
            'location_verified_at' => $notaryRequest->location_verified_at?->toIso8601String(),
            'signers' => $signers,
            'documents' => $documents,
            'sessions' => $sessions,
            'waiting_video_parties' => collect($sessions)
                ->filter(fn (array $session): bool => (bool) ($session['signer_waiting'] ?? false))
                ->values()
                ->all(),
            'session' => $highlightedSession,
            'payment' => $latestPayment ? [
                'id' => $latestPayment->id,
                'status' => $latestPayment->status->value,
                'reference' => $latestPayment->reference,
                'gateway' => $latestPayment->gateway,
                'paid_at' => $latestPayment->paid_at?->toIso8601String(),
                'expires_at' => $latestPayment->expires_at?->toIso8601String(),
                'last_verified_at' => $latestPayment->last_verified_at?->toIso8601String(),
                'updated_at' => $latestPayment->updated_at?->toIso8601String(),
            ] : null,
        ];
    }

    private function resolveSignerSigningStatus(NotaryRequest $request, NotarySigner $signer): ?string
    {
        foreach ($request->documents as $document) {
            $docSigner = $document->documentSigners
                ->first(fn ($ds) => strtolower(trim((string) $ds->email)) === strtolower(trim((string) $signer->email)));

            if ($docSigner !== null && $docSigner->hasCompletedAction()) {
                return 'signed';
            }

            if ($docSigner !== null) {
                return $docSigner->status?->value ?? 'pending';
            }
        }

        return 'not_assigned';
    }

    private function resolveSignerIdentityStatus(NotarySigner $signer): ?string
    {
        $verification = $signer->identityVerifications()
            ->latest()
            ->first();

        return $verification?->verification_status?->value;
    }
}
