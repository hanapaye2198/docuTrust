<?php

namespace App\Services;

use App\Models\NotaryRequest;
use App\Models\NotarySigner;

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
            'sessions',
            'identityVerifications',
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

        $latestSession = $notaryRequest->sessions->first();

        return [
            'request_id' => $notaryRequest->id,
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
