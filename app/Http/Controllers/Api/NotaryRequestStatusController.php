<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotaryRequest;
use App\Services\NotaryRequestStatusPayloadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotaryRequestStatusController extends Controller
{
    /**
     * Return a lightweight JSON payload with the current status of a notary request.
     * Used by the frontend AJAX polling to update the UI without a full page refresh.
     *
     * GET /api/notary-requests/{notaryRequest}/status
     */
    public function __invoke(Request $request, NotaryRequest $notaryRequest): JsonResponse
    {
        $this->authorize('view', $notaryRequest);

        return response()->json(
            app(NotaryRequestStatusPayloadService::class)->build($notaryRequest)
        );
    }
}
