<?php

namespace App\Trust\Authorization;

use App\Models\DocumentSigner;
use App\Models\TrustAuthorizationSession;
use RuntimeException;

class TrustAuthorizationWorkflowService
{
    public function __construct(
        private readonly RemoteAuthorizationClient $remoteAuthorizationClient,
        private readonly TrustAuthorizationSessionService $trustAuthorizationSessionService,
    ) {}

    public function startForSigner(DocumentSigner $signer, int $numSignatures = 1): TrustAuthorizationSession
    {
        $providerName = trim((string) config('services.remote_signing.provider_name', 'remote_managed'));
        $authorizationMode = (string) config('services.remote_signing.csc.authorization_mode', 'explicit');

        if ((string) config('docutrust.pki.signing_backend', 'app_managed') !== 'remote_managed') {
            throw new RuntimeException('Trust authorization is only available for remote-managed signing.');
        }

        $result = $this->remoteAuthorizationClient->authorize($signer, $numSignatures);
        $payload = $result['payload'];
        $httpStatus = (int) ($result['http_status'] ?? 200);
        $credentialId = is_string($result['credential_id'] ?? null) ? $result['credential_id'] : null;
        $sad = is_string($payload['SAD'] ?? null) && trim((string) $payload['SAD']) !== '' ? (string) $payload['SAD'] : null;
        $handle = is_string($payload['handle'] ?? null) && trim((string) $payload['handle']) !== '' ? (string) $payload['handle'] : null;

        if ($httpStatus === 202 && $handle === null) {
            throw new RuntimeException('Remote signing authorization did not return a polling handle.');
        }

        return TrustAuthorizationSession::query()->create([
            'document_signer_id' => $signer->id,
            'provider_name' => $providerName !== '' ? $providerName : 'remote_managed',
            'credential_id' => $credentialId,
            'authorization_mode' => $authorizationMode,
            'status' => $httpStatus === 202 ? 'pending' : 'authorized',
            'authorization_reference' => $handle,
            'sad' => $sad,
            'access_token' => is_string($payload['access_token'] ?? null) ? $payload['access_token'] : null,
            'expires_at' => is_numeric($payload['expiresIn'] ?? null) ? now()->addSeconds((int) $payload['expiresIn']) : null,
            'completed_at' => $httpStatus === 202 ? null : now(),
            'payload' => is_array($payload) ? $payload : null,
        ]);
    }

    public function pollSession(TrustAuthorizationSession $session): TrustAuthorizationSession
    {
        if (! is_string($session->authorization_reference) || $session->authorization_reference === '') {
            throw new RuntimeException('Trust authorization session does not have a polling handle.');
        }

        $result = $this->remoteAuthorizationClient->checkAuthorization($session->authorization_reference);
        $payload = $result['payload'];
        $httpStatus = (int) ($result['http_status'] ?? 200);
        $sad = is_string($payload['SAD'] ?? null) && trim((string) $payload['SAD']) !== '' ? (string) $payload['SAD'] : null;
        $nextHandle = is_string($payload['handle'] ?? null) && trim((string) $payload['handle']) !== '' ? (string) $payload['handle'] : null;

        $session->forceFill([
            'status' => $httpStatus === 202 ? 'pending' : 'authorized',
            'authorization_reference' => $nextHandle ?: $session->authorization_reference,
            'sad' => $sad ?: $session->sad,
            'access_token' => is_string($payload['access_token'] ?? null) ? $payload['access_token'] : $session->access_token,
            'expires_at' => is_numeric($payload['expiresIn'] ?? null) ? now()->addSeconds((int) $payload['expiresIn']) : $session->expires_at,
            'completed_at' => $httpStatus === 202 ? $session->completed_at : now(),
            'payload' => is_array($payload) ? $payload : $session->payload,
        ])->save();

        return $session->refresh();
    }
}
