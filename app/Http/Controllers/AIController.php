<?php

namespace App\Http\Controllers;

use App\Http\Requests\MarketingChatbotMessageRequest;
use App\Services\HybridChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AIController extends Controller
{
    public function ask(
        MarketingChatbotMessageRequest $request,
        HybridChatbotService $chatbot,
    ): JsonResponse {
        if (! $chatbot->isAvailable()) {
            Log::warning('Chatbot requested but service is disabled');

            return response()->json([
                'success' => false,
                'reply' => null,
                'source' => null,
                'error' => __('The AI assistant is not available right now.'),
            ], 503);
        }

        $result = $chatbot->respond(
            $request->string('message')->toString(),
            $request->validated('history', []),
        );

        return response()->json($result->toArray());
    }
}
