<?php

namespace App\Http\Controllers;

use App\Http\Requests\MarketingChatbotMessageRequest;
use App\Services\HybridChatbotService;
use Illuminate\Http\JsonResponse;

class MarketingChatbotController extends Controller
{
    public function __invoke(
        MarketingChatbotMessageRequest $request,
        AIController $aiController,
        HybridChatbotService $chatbot,
    ): JsonResponse {
        return $aiController->ask($request, $chatbot);
    }
}
