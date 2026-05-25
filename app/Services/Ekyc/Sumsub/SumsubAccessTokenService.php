<?php

namespace App\Services\Ekyc\Sumsub;

use App\Models\User;
use RuntimeException;

class SumsubAccessTokenService
{
    public function __construct(private readonly SumsubApiClient $client) {}

    /**
     * Generate a fresh Sumsub WebSDK access token for the given user.
     *
     * This is used when the frontend needs to (re)initialize the WebSDK widget,
     * for example on page load or when the previous token has expired.
     */
    public function generateForUser(User $user): string
    {
        $levelName = (string) config('ekyc.sumsub.level_name', 'basic-kyc-level');
        $ttl = (int) config('ekyc.sumsub.ttl_in_secs', 600);

        $token = $this->client->generateAccessToken(
            externalUserId: (string) $user->id,
            levelName: $levelName,
            ttlInSecs: $ttl,
        );

        if ($token === '') {
            throw new RuntimeException(__('Failed to generate verification session token.'));
        }

        return $token;
    }
}
