<?php

namespace App\Services;

use App\Models\NotaryRequest;
use App\Models\NotarySession;
use App\Models\NotarySigner;
use App\Models\User;
use Firebase\JWT\JWT;
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

    private ?string $apiKeyId;

    private string $domain;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('docutrust.notary.jitsi_base_url', 'https://meet.jit.si'), '/');
        $appId = (string) config('docutrust.notary.jitsi_app_id', '');
        $appSecret = (string) config('docutrust.notary.jitsi_app_secret', '');
        $apiKeyId = (string) config('docutrust.notary.jitsi_api_key_id', '');
        $this->appId = $appId !== '' ? $appId : null;
        $this->appSecret = $appSecret !== '' ? $appSecret : null;
        $this->apiKeyId = $apiKeyId !== '' ? $apiKeyId : null;
        $this->domain = parse_url($this->baseUrl, PHP_URL_HOST) ?: 'meet.jit.si';
    }

    /**
     * Build a unique room name for a notary request.
     */
    public function buildRoomName(NotaryRequest $request): string
    {
        return 'docutrust-'.$request->id.'-'.strtolower(Str::random(10));
    }

    public function buildRoomNameForSigner(NotaryRequest $request, NotarySigner $signer): string
    {
        return 'docutrust-'.$request->id.'-signer-'.$signer->id.'-'.strtolower(Str::random(8));
    }

    /**
     * Get the full meeting URL for a room.
     */
    public function meetingUrl(string $roomName): string
    {
        // For public Jitsi (no credentials), just return the plain URL
        if ($this->appId === null || $this->appSecret === null) {
            return $this->baseUrl.'/'.$roomName;
        }

        $isJaas = str_starts_with($this->appId, 'vpaas-magic-cookie-');

        if ($isJaas) {
            $url = $this->baseUrl.'/'.$this->appId.'/'.$roomName;
        } else {
            $url = $this->baseUrl.'/'.$roomName;
        }

        $jwt = $this->generateJwt($roomName);
        if ($jwt !== null) {
            $url .= '?jwt='.$jwt;
        }

        return $url;
    }

    /**
     * Get a meeting URL with JWT for a specific user (moderator or participant).
     */
    public function meetingUrlForUser(string $roomName, User $user, bool $isModerator = false): string
    {
        if ($this->appId === null || $this->appSecret === null) {
            return $this->baseUrl.'/'.$roomName;
        }

        $isJaas = str_starts_with($this->appId, 'vpaas-magic-cookie-');

        if ($isJaas) {
            $url = $this->baseUrl.'/'.$this->appId.'/'.$roomName;
        } else {
            $url = $this->baseUrl.'/'.$roomName;
        }

        $jwt = $this->generateJwtForUser($roomName, $user, $isModerator);
        if ($jwt !== null) {
            $url .= '?jwt='.$jwt;
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

    public function externalApiScriptUrl(): string
    {
        if ($this->appId !== null && str_starts_with($this->appId, 'vpaas-magic-cookie-')) {
            return $this->baseUrl.'/'.$this->appId.'/external_api.js';
        }

        return $this->baseUrl.'/external_api.js';
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
        $isJaas = $this->appId !== null && str_starts_with($this->appId, 'vpaas-magic-cookie-');

        // Only generate JWT if credentials are configured
        $jwt = ($this->appId !== null && $this->appSecret !== null)
            ? $this->generateJwtForUser($roomName, $user, $isModerator)
            : null;

        // JaaS requires roomName in format: <AppID>/<room>
        $iframeRoomName = $isJaas
            ? $this->appId.'/'.$roomName
            : $roomName;

        return [
            'domain' => $this->domain,
            'roomName' => $iframeRoomName,
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
            'iat' => $now - 60,
            'exp' => $now + ($ttlMinutes * 60),
            'nbf' => $now - 60,
            'context' => [
                'features' => [
                    'livestreaming' => false,
                    'recording' => true,
                    'transcription' => false,
                    'outbound-call' => false,
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
            'aud' => 'jitsi',
            'exp' => $now + ($ttlMinutes * 60),
            'nbf' => $now - 60,
            'room' => $isJaas ? '*' : $roomName,
            'sub' => $isJaas ? $this->appId : $this->domain,
            'context' => [
                'user' => [
                    'moderator' => $isModerator ? 'true' : 'false',
                    'email' => $user->email,
                    'name' => $user->name,
                    'avatar' => '',
                    'id' => (string) $user->id,
                ],
                'features' => [
                    'recording' => $isModerator ? 'true' : 'false',
                    'livestreaming' => 'false',
                    'transcription' => 'false',
                    'outbound-call' => 'false',
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
        $hasJwtAuth = $this->appId !== null && $this->appSecret !== null;

        return [
            // Security
            'requireDisplayName' => true,
            'enableLobby' => $hasJwtAuth,
            'lobbyEnabled' => false,

            // Audio/Video
            'startWithAudioMuted' => false,
            'startWithVideoMuted' => false,
            'enableNoisyMicDetection' => true,

            // Recording (only for moderator/notary)
            'fileRecordingsEnabled' => $isModerator,
            'localRecording' => ['enabled' => $isModerator],

            // Disable unnecessary features for notary sessions
            'enableWelcomePage' => false,
            'prejoinPageEnabled' => false,
            'disableDeepLinking' => true,
            'enableClosePage' => false,

            // Participants
            'maxParticipants' => 6,

            // Moderation
            'disableRemoteMute' => ! $isModerator,
            'remoteVideoMenu' => ['disableKick' => ! $isModerator],

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
     * Uses firebase/php-jwt library for reliable JWT generation.
     */
    private function encodeJwt(array $payload): string
    {
        $pem = $this->appSecret;
        // Handle escaped newlines from .env (literal \n characters)
        if (str_contains($pem, '\\n')) {
            $pem = str_replace('\\n', "\n", $pem);
        }
        if (! str_contains($pem, '-----BEGIN')) {
            $pem = "-----BEGIN PRIVATE KEY-----\n".chunk_split($pem, 64, "\n").'-----END PRIVATE KEY-----';
        }

        $isJaas = str_starts_with($this->appId, 'vpaas-magic-cookie-');

        if ($isJaas) {
            $kid = $this->apiKeyId
                ? $this->appId.'/'.$this->apiKeyId
                : $this->appId.'/generated-key';

            return JWT::encode($payload, $pem, 'RS256', $kid);
        }

        // For self-hosted with shared secret (HS256)
        $isRsaKey = str_contains($pem, 'PRIVATE KEY');
        if ($isRsaKey) {
            return JWT::encode($payload, $pem, 'RS256');
        }

        return JWT::encode($payload, $this->appSecret, 'HS256');
    }
}
