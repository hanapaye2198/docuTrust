<?php

namespace App\Trust\Authorization;

use App\Models\DocumentSigner;
use App\Models\TrustAuthorizationSession;

class TrustAuthorizationSessionService
{
    public function activeForSigner(DocumentSigner $signer, string $providerName): ?TrustAuthorizationMaterial
    {
        $session = TrustAuthorizationSession::query()
            ->where('document_signer_id', $signer->id)
            ->where('provider_name', $providerName)
            ->whereIn('status', ['authorized', 'active'])
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->latest('id')
            ->first();

        if ($session === null) {
            return null;
        }

        return new TrustAuthorizationMaterial(
            providerName: $session->provider_name,
            credentialId: $session->credential_id,
            sad: is_string($session->sad) && $session->sad !== '' ? $session->sad : null,
            accessToken: is_string($session->access_token) && $session->access_token !== '' ? $session->access_token : null,
            authorizationReference: is_string($session->authorization_reference) && $session->authorization_reference !== '' ? $session->authorization_reference : null,
            payload: is_array($session->payload) ? $session->payload : null,
        );
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function recordAuthorizedSession(
        DocumentSigner $signer,
        string $providerName,
        ?string $credentialId,
        string $authorizationMode,
        ?string $sad = null,
        ?string $accessToken = null,
        ?string $authorizationReference = null,
        ?\DateTimeInterface $expiresAt = null,
        ?array $payload = null,
    ): TrustAuthorizationSession {
        return TrustAuthorizationSession::query()->create([
            'document_signer_id' => $signer->id,
            'provider_name' => $providerName,
            'credential_id' => $credentialId,
            'authorization_mode' => $authorizationMode,
            'status' => 'authorized',
            'authorization_reference' => $authorizationReference,
            'sad' => $sad,
            'access_token' => $accessToken,
            'expires_at' => $expiresAt,
            'completed_at' => now(),
            'payload' => $payload,
        ]);
    }
}
