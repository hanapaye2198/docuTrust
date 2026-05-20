<?php

namespace App\Services;

use App\Data\ChatbotReply;
use Illuminate\Support\Facades\Log;
use Throwable;

class HybridChatbotService
{
    public function __construct(
        private ChatbotFaqMatcher $faqMatcher,
        private MarketingChatbotService $openAiChatbot,
    ) {}

    public function isAvailable(): bool
    {
        if (! $this->chatbotEnabled()) {
            return false;
        }

        return $this->hasFaqs() || $this->openAiChatbot->isOpenAiConfigured();
    }

    /**
     * @param  list<array{role: string, content: string}>  $history
     */
    public function respond(string $message, array $history = []): ChatbotReply
    {
        $normalizedMessage = mb_strtolower(trim($message));

        $faqAnswer = $this->faqMatcher->match($normalizedMessage);

        if ($faqAnswer !== null) {
            Log::info('Chatbot answered from local FAQ', [
                'message_preview' => mb_substr($normalizedMessage, 0, 80),
            ]);

            return new ChatbotReply(
                reply: $faqAnswer,
                source: ChatbotReply::SourceFaq,
            );
        }

        if ($this->openAiChatbot->isOpenAiConfigured()) {
            try {
                $aiReply = $this->openAiChatbot->reply(
                    $message,
                    $history,
                    (string) config('chatbot.openai_system_prompt'),
                );

                Log::info('Chatbot answered from OpenAI', [
                    'message_preview' => mb_substr($normalizedMessage, 0, 80),
                ]);

                return new ChatbotReply(
                    reply: $aiReply,
                    source: ChatbotReply::SourceAi,
                );
            } catch (Throwable $exception) {
                Log::error('Chatbot OpenAI fallback triggered', [
                    'message' => $exception->getMessage(),
                    'exception' => $exception::class,
                ]);
            }
        }

        return new ChatbotReply(
            reply: (string) config('chatbot.fallback_message'),
            source: ChatbotReply::SourceFallback,
        );
    }

    private function chatbotEnabled(): bool
    {
        return filter_var(config('chatbot.enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    private function hasFaqs(): bool
    {
        $faqs = config('chatbot.faqs', []);

        return is_array($faqs) && $faqs !== [];
    }
}
