<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Signature;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureEvidenceRecord;
use App\Models\TrustAuthorizationSession;

class SignatureEvidenceRecordService
{
    public function recordForCompletedDocument(Document $document): void
    {
        $document->loadMissing([
            'documentSigners.user',
            'signatures.signer',
            'documentHash',
            'signatureAuditEvents',
        ]);

        $documentHash = $document->documentHash?->hash;
        $blockchainTxn = $document->documentHash?->transaction_id;
        $auditSnapshot = $document->signatureAuditEvents
            ->map(fn (SignatureAuditEvent $event): array => [
                'action' => $event->action,
                'signer_id' => $event->signer_id,
                'ip_address' => $event->ip_address,
                'created_at' => $event->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        foreach ($document->signatures as $signature) {
            $this->upsertForSignature(
                $document,
                $signature,
                $documentHash,
                $blockchainTxn,
                $auditSnapshot,
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $auditSnapshot
     */
    private function upsertForSignature(
        Document $document,
        Signature $signature,
        ?string $documentHash,
        ?string $blockchainTxn,
        array $auditSnapshot,
    ): void {
        $signer = $signature->signer;
        if ($signer === null) {
            return;
        }

        $signer->loadMissing('user');
        $user = $signer->user;

        $otp = $this->resolveOtpEvidence($signer->id, $signature);

        SignatureEvidenceRecord::query()->updateOrCreate(
            [
                'document_id' => $document->id,
                'signature_id' => $signature->id,
            ],
            [
                'signer_id' => $signer->id,
                'signer_identity' => [
                    'name' => $signer->name,
                    'email' => $signer->email,
                    'user_id' => $user?->id,
                    'ekyc_status' => $user?->ekyc_status,
                ],
                'ip_address' => $this->latestIpForSigner($document->id, $signer->id),
                'user_agent' => null,
                'device_info' => null,
                'signed_at' => $signer->signed_at,
                'document_hash' => $documentHash,
                'signature_hash' => $signature->signature_hash,
                'signature_algorithm' => $signature->signature_algorithm,
                'blockchain_txn' => $blockchainTxn,
                'otp_verified' => $otp['verified'],
                'otp_method' => $otp['method'],
                'signing_provider' => $signature->signing_provider,
                'signing_provider_payload' => $signature->signing_provider_payload,
                'audit_trail_snapshot' => $auditSnapshot,
            ],
        );
    }

    /**
     * @return array{verified: bool, method: string|null}
     */
    private function resolveOtpEvidence(int $signerId, Signature $signature): array
    {
        $payload = is_array($signature->signing_provider_payload)
            ? $signature->signing_provider_payload
            : [];

        $authMethod = $payload['authentication_method'] ?? null;
        if (is_string($authMethod) && $authMethod !== '') {
            return [
                'verified' => true,
                'method' => $authMethod,
            ];
        }

        $hasTrustSession = TrustAuthorizationSession::query()
            ->where('document_signer_id', $signerId)
            ->whereNotNull('completed_at')
            ->exists();

        if ($hasTrustSession) {
            return [
                'verified' => true,
                'method' => 'trust_authorization',
            ];
        }

        return [
            'verified' => false,
            'method' => null,
        ];
    }

    private function latestIpForSigner(int $documentId, int $signerId): ?string
    {
        $event = SignatureAuditEvent::query()
            ->where('document_id', $documentId)
            ->where('signer_id', $signerId)
            ->where('action', SignatureAuditEvent::ACTION_SIGNED)
            ->latest('id')
            ->first();

        return $event?->ip_address;
    }
}
