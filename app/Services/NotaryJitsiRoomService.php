<?php

namespace App\Services;

use App\Models\NotaryRequest;
use App\Models\NotarySession;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Jitsi Meet Integration Service
 *
 * Provides video conferencing for notary sessions via Jitsi Meet.
 * Supports both public (meet.jit.si) and self-hosted instances.
 * When JWT is configured, generates signed tokens for private rooms.
 */
final class NotaryJitsiRoomService
{
    private string $baseUrl;
    private ?string $appId;
    private ?string $appSecret;
    private string $domain;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('docutrust.notary.jitsi_base_url', 'https://meet.jit.si'), '/');
        $this->appId = config('docutrust.notary.jitsi_app_id') ?: null;
        $this->appSecret = config('docutrust.notary.jitsi_app_secret') ?: null;
        $this->domain = parse_url($this->baseUrl, PHP_URL_HOST) ?: 'meet.jit.si';
    }

    /**
     * Build a unique room name for a notary request.
     */
    public function buildRoomName(NotaryRequest $request): string
    {
        return 'docutrust-' . $request->id . '-' . strtolower(Str::random(10));
    }

    /**
     * Get the full meeting URL for a room.
     */
    public function meetingUrl(string $roomName): string
    {
        $isJaas = str_starts_with($this->appId ?? '', 'vpaas-magic-cookie-');

        if ($isJaas) {
            // JaaS requires: https://8x8.vc/{appId}/{roomName}
            $url = $this->baseUrl . '/' . $this->appId . '/' . $roomName;
        } else {
            $url = $this->baseUrl . '/' . $roomName;
        }

        // Append JWT if configured
        $jwt = $this->generateJwt($roomName);
        if ($jwt !== null) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'jwt=' . $jwt;
        }

        return $url;
    }

    /**
     * Get a meeting URL with JWT for a specific user (moderator or participant).
     */
    public function meetingUrlForUser(string $roomName, User $user, bool $isModerator = false): string
    {
        $isJaas = str_starts_with($this->appId ?? '', 'vpaas-magic-cookie-');

        if ($isJaas) {
            $url = $this->baseUrl . '/' . $this->appId . '/' . $roomName;
        } else {
            $url = $this->baseUrl . '/' . $roomName;
        }

        $jwt = $this->generateJwtForUser($roomName, $user, $isModerator);
        if ($jwt !== null) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'jwt=' . $jwt;
        }

        return $url;
    }

    /**
     * Get the Jitsi domain (without protocol) for the iframe API.
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Get iframe API configuration for embedding Jitsi in the browser.
     *
     * @return array{
     *   domain: string,
     *   roomName: string,
     *   jwt: string|null,
     *   configOverwrite: array,
     *   interfaceConfigOverwrite: array,
     *   userInfo: array|null
     * }
     */
    public function getIframeConfig(NotarySession $session, User $user, bool $isModerator = false): array
    {
        $roomName = $session->room_name ?? $this->buildRoomName($session->notaryRequest);
        $jwt = $this->generateJwtForUser($roomName, $user, $isModerator);

        return [
            'domain' => $this->domain,
            'roomName' => $roomName,
            'jwt' => $jwt,
            'configOverwrite' => $this->getRoomConfig($isModerator),
            'interfaceConfigOverwrite' => $this->getInterfaceConfig(),
            'userInfo' => [
                'displayName' => $user->name,
                'email' => $user->email,
            ],
        ];
    }

    /**
     * Generate a JWT token for Jitsi room access.
     * Returns null if JWT auth is not configured (public instance).
     */
    public function generateJwt(string $roomName, int $ttlMinutes = 120): ?string
    {
        if ($this->appId === null || $this->appSecret === null) {
            return null;
        }

        $now = time();
        $isJaas = str_starts_with($this->appId, 'vpaas-magic-cookie-');

        $payload = [
            'iss' => 'chat',
            'sub' => $isJaas ? $this->appId : $this->domain,
            'aud' => $isJaas ? 'jitsi' : 'jitsi',
            'room' => $isJaas ? '*' : $roomName,
            'iat' => $now,
            'exp' => $now + ($ttlMinutes * 60),
            'nbf' => $now - 10,
            'context' => [
                'features' => [
                    'livestreaming' => false,
                    'recording' => true,
                    'transcription' => false,
                    'outbound-call' => false,
                ],
                'room' => [
                    'regex' => false,
                ],
            ],
        ];

        return $this->encodeJwt($payload);
    }

    /**
     * Generate a JWT token for a specific user with role-based permissions.
     */
    public function generateJwtForUser(string $roomName, User $user, bool $isModerator = false, int $ttlMinutes = 120): ?string
    {
        if ($this->appId === null || $this->appSecret === null) {
            return null;
        }

        $now = time();
        $isJaas = str_starts_with($this->appId, 'vpaas-magic-cookie-');

        $payload = [
            'iss' => 'chat',
            'sub' => $isJaas ? $this->appId : $this->domain,
            'aud' => 'jitsi',
            'room' => $isJaas ? '*' : $roomName,
            'iat' => $now,
            'exp' => $now + ($ttlMinutes * 60),
            'nbf' => $now - 10,
            'context' => [
                'user' => [
                    'id' => (string) $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => '',
                    'moderator' => $isModerator ? 'true' : 'false',
                ],
                'features' => [
                    'livestreaming' => false,
                    'recording' => $isModerator,
                    'transcription' => false,
                    'outbound-call' => false,
                ],
                'room' => [
                    'regex' => false,
                ],
            ],
        ];

        return $this->encodeJwt($payload);
    }

    /**
     * Get Jitsi room configuration for notary sessions.
     */
    private function getRoomConfig(bool $isModerator): array
    {
        return [
            // Security
            'requireDisplayName' => true,
            'enableLobby' => true,
            'lobbyEnabled' => true,

            // Audio/Video
            'startWithAudioMuted' => false,
            'startWithVideoMuted' => false,
            'enableNoisyMicDetection' => true,

            // Recording (only for moderator/notary)
            'fileRecordingsEnabled' => $isModerator,
            'localRecording' => ['enabled' => $isModerator],

            // Disable unnecessary features for notary sessions
            'enableWelcomePage' => false,
            'prejoinPageEnabled' => true,
            'disableDeepLinking' => true,
            'enableClosePage' => false,

            // Participants
            'maxParticipants' => 6,

            // Moderation
            'disableRemoteMute' => !$isModerator,
            'remoteVideoMenu' => ['disableKick' => !$isModerator],

            // Branding
            'subject' => 'DocuTrust Notary Session',
        ];
    }

    /**
     * Get Jitsi interface configuration (UI customization).
     */
    private function getInterfaceConfig(): array
    {
        return [
            'APP_NAME' => 'DocuTrust Notary',
            'SHOW_JITSI_WATERMARK' => false,
            'SHOW_WATERMARK_FOR_GUESTS' => false,
            'SHOW_BRAND_WATERMARK' => false,
            'SHOW_POWERED_BY' => false,
            'SHOW_PROMOTIONAL_CLOSE_PAGE' => false,
            'HIDE_INVITE_MORE_HEADER' => true,
            'DISABLE_JOIN_LEAVE_NOTIFICATIONS' => false,
            'DISABLE_FOCUS_INDICATOR' => true,

            // Toolbar buttons for notary workflow
            'TOOLBAR_BUTTONS' => [
                'camera',
                'chat',
                'closedcaptions',
                'desktop',
                'fullscreen',
                'hangup',
                'microphone',
                'participants-pane',
                'raisehand',
                'recording',
                'security',
                'select-background',
                'settings',
                'tileview',
                'toggle-camera',
                'videoquality',
            ],

            'SETTINGS_SECTIONS' => ['devices', 'language', 'moderator', 'profile'],
        ];
    }

    /**
     * Encode a JWT payload.
     * Uses RS256 if the secret looks like an RSA key, otherwise HS256.
     */
    private function encodeJwt(array $payload): string
    {
        $isRsaKey = str_contains($this->appSecret, 'MIIE') || str_contains($this->appSecret, 'BEGIN');

        if ($isRsaKey) {
            return $this->encodeJwtRs256($payload);
        }

        return $this->encodeJwtHs256($payload);
    }

    /**
     * Encode JWT with RS256 (for JaaS / 8x8 private key).
     */
    private function encodeJwtRs256(array $payload): string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $this->appId . '/generated-key'];

        $segments = [
            $this->base64UrlEncode(json_encode($header)),
            $this->base64UrlEncode(json_encode($payload)),
        ];

        $signingInput = implode('.', $segments);

        // Reconstruct PEM if it was stored as raw base64
        $pem = $this->appSecret;
        if (!str_contains($pem, '-----BEGIN')) {
            $pem = "-----BEGIN PRIVATE KEY-----\n" . chunk_split($pem, 64, "\n") . "-----END PRIVATE KEY-----";
        }

        $privateKey = openssl_pkey_get_private($pem);
        if ($privateKey === false) {
            throw new \RuntimeException('Invalid Jitsi private key. Check DOCUTRUST_NOTARY_JITSI_APP_SECRET.');
        }

        $signature = '';
        if (!openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Failed to sign Jitsi JWT.');
        }

        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    /**
     * Encode JWT with HS256 (for self-hosted Jitsi with shared secret).
     */
    private function encodeJwtHs256(array $payload): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];

        $segments = [
            $this->base64UrlEncode(json_encode($header)),
            $this->base64UrlEncode(json_encode($payload)),
        ];

        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, $this->appSecret, true);
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
