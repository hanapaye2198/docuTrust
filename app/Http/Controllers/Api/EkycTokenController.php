<?php

namespace App\Http\Controllers\Api;

use App\Enums\EkycStatus;
use App\Http\Controllers\Controller;
use App\Models\EkycRecord;
use App\Services\Ekyc\EkycProviderManager;
use App\Services\Ekyc\EkycVerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EkycTokenController extends Controller
{
    public function __construct(private readonly EkycProviderManager $providerManager) {}

    /**
     * Generate a Sumsub access token and create a pending EkycRecord.
     *
     * POST /api/ekyc/token
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $provider = $this->providerManager->driver();

        if (! $provider->isAsync()) {
            return response()->json([
                'message' => 'Token generation is not supported for the current eKYC driver.',
            ], 422);
        }

        $result = $provider->initiate(new EkycVerificationRequest(
            externalUserId: (string) $user->id,
            firstName: $user->resolvedFirstName(),
            lastName: $user->resolvedLastName(),
            email: $user->email,
            phone: $user->phone ?? null,
        ));

        if ($result->isRejected()) {
            return response()->json([
                'message' => $result->failureReason ?? __('Verification could not be initiated.'),
            ], 422);
        }

        // Create or update the pending EkycRecord
        $record = EkycRecord::query()->create([
            'user_id' => $user->id,
            'document_type' => 'sumsub_verification',
            'document_path' => '',
            'provider' => $provider->name(),
            'provider_reference' => $result->providerReference,
            'status' => EkycStatus::Pending->value,
        ]);

        $user->forceFill(['ekyc_status' => EkycStatus::Pending])->save();

        Log::info('eKYC token generated.', [
            'user_id' => $user->id,
            'provider' => $provider->name(),
            'ekyc_record_id' => $record->id,
        ]);

        return response()->json([
            'access_token' => $result->accessToken,
            'applicant_id' => $result->providerReference,
        ]);
    }
}
