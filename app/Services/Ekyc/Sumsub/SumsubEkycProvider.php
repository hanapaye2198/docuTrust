<?php

namespace App\Services\Ekyc\Sumsub;

use App\Contracts\Ekyc\EkycVerificationProvider;
use App\Enums\EkycStatus;
use App\Services\Ekyc\EkycProviderResult;
use App\Services\Ekyc\EkycVerificationRequest;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SumsubEkycProvider implements EkycVerificationProvider
{
    public function __construct(private readonly SumsubApiClient $client) {}

    public function initiate(EkycVerificationRequest $request): EkycProviderResult
    {
        $levelName = (string) config('ekyc.sumsub.level_name', 'basic-kyc-level');
        $ttl = (int) config('ekyc.sumsub.ttl_in_secs', 600);

        try {
            // Create or retrieve the applicant in Sumsub
            $applicant = $this->client->createApplicant(
                externalUserId: $request->externalUserId,
                levelName: $levelName,
                fixedInfo: array_filter([
                    'firstName' => $request->firstName,
                    'lastName' => $request->lastName,
                    'email' => $request->email,
                    'phone' => $request->phone,
                ]),
            );

            $applicantId = $applicant['id'] ?? null;

            if ($applicantId === null || $applicantId === '') {
                Log::error('Sumsub createApplicant returned no ID', ['response' => $applicant]);

                return new EkycProviderResult(
                    status: EkycStatus::Rejected,
                    failureReason: __('Failed to initialize identity verification. Please try again.'),
                );
            }

            // Generate an access token for the WebSDK
            $accessToken = $this->client->generateAccessToken(
                externalUserId: $request->externalUserId,
                levelName: $levelName,
                ttlInSecs: $ttl,
            );

            if ($accessToken === '') {
                Log::error('Sumsub generateAccessToken returned empty token', [
                    'applicant_id' => $applicantId,
                ]);

                return new EkycProviderResult(
                    status: EkycStatus::Rejected,
                    providerReference: $applicantId,
                    failureReason: __('Failed to generate verification session. Please try again.'),
                );
            }

            return new EkycProviderResult(
                status: EkycStatus::Pending,
                providerReference: $applicantId,
                accessToken: $accessToken,
            );
        } catch (RuntimeException $e) {
            Log::error('Sumsub eKYC initiation failed', [
                'external_user_id' => $request->externalUserId,
                'error' => $e->getMessage(),
            ]);

            return new EkycProviderResult(
                status: EkycStatus::Rejected,
                failureReason: __('Identity verification service is temporarily unavailable. Please try again later.'),
            );
        }
    }

    public function isAsync(): bool
    {
        return true;
    }

    public function name(): string
    {
        return 'sumsub';
    }
}
