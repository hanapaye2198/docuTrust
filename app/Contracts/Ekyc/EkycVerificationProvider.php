<?php

namespace App\Contracts\Ekyc;

use App\Services\Ekyc\EkycProviderResult;
use App\Services\Ekyc\EkycVerificationRequest;

interface EkycVerificationProvider
{
    /**
     * Initiate an eKYC verification.
     *
     * For synchronous providers (e.g. Tesseract): verifies immediately and returns a final result.
     * For asynchronous providers (e.g. Sumsub): creates the applicant, returns a pending result
     * with an access token for the frontend SDK.
     */
    public function initiate(EkycVerificationRequest $request): EkycProviderResult;

    /**
     * Whether this provider uses asynchronous (webhook-based) verification.
     */
    public function isAsync(): bool;

    /**
     * The provider's unique identifier.
     */
    public function name(): string;
}
