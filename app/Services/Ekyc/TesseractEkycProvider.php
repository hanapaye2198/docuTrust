<?php

namespace App\Services\Ekyc;

use App\Contracts\Ekyc\EkycVerificationProvider;
use App\Enums\EkycStatus;
use App\Exceptions\EkycOcrUnavailableException;
use App\Models\User;

class TesseractEkycProvider implements EkycVerificationProvider
{
    public function __construct(
        private readonly EkycNameVerificationService $verificationService,
    ) {}

    public function initiate(EkycVerificationRequest $request): EkycProviderResult
    {
        if ($request->documentPath === null || $request->documentPath === '') {
            return new EkycProviderResult(
                status: EkycStatus::Rejected,
                failureReason: __('A document image path is required for Tesseract verification.'),
            );
        }

        $user = User::query()->find((int) $request->externalUserId);

        if ($user === null) {
            return new EkycProviderResult(
                status: EkycStatus::Rejected,
                failureReason: __('User not found.'),
            );
        }

        try {
            $result = $this->verificationService->verify($user, $request->documentPath);
        } catch (EkycOcrUnavailableException $e) {
            return new EkycProviderResult(
                status: EkycStatus::Rejected,
                failureReason: $e->getMessage(),
            );
        }

        if ($result->matched) {
            return new EkycProviderResult(
                status: EkycStatus::Verified,
                extractedData: ['ocr_text' => $result->ocrText],
            );
        }

        return new EkycProviderResult(
            status: EkycStatus::Rejected,
            failureReason: $result->message,
            extractedData: ['ocr_text' => $result->ocrText],
        );
    }

    public function isAsync(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'tesseract';
    }
}
