<?php

namespace App\Services;

use App\Support\MarketingKnowledge;
use Illuminate\Support\Facades\Log;
use OpenAI;
use OpenAI\Client;
use RuntimeException;
use Throwable;

class MarketingChatbotService
{
    private const MaxHistoryMessages = 12;

    public function isEnabled(): bool
    {
        if (! filter_var(config('services.marketing_chatbot.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return $this->isOpenAiConfigured();
    }

    public function isOpenAiConfigured(): bool
    {
        return $this->apiKey() !== '';
    }

    /**
     * @param  list<array{role: string, content: string}>  $history
     */
    public function reply(string $message, array $history = [], ?string $systemPrompt = null): string
    {
        if (! $this->isOpenAiConfigured()) {
            throw new RuntimeException('OpenAI is not configured.');
        }

        $systemContent = $systemPrompt ?? (string) config('chatbot.openai_system_prompt', MarketingKnowledge::systemPrompt());

        $messages = [
            ['role' => 'system', 'content' => $systemContent],
            ...$this->normalizeHistory($history),
            ['role' => 'user', 'content' => $message],
        ];

        try {
            $response = $this->client()->chat()->create([
                'model' => (string) config('services.openai.model', 'gpt-4o-mini'),
                'messages' => $messages,
                'temperature' => 0.35,
                'max_tokens' => 700,
            ]);
        } catch (Throwable $exception) {
            Log::error('OpenAI chatbot API error', [
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            throw new RuntimeException(
                'Unable to reach the AI assistant: '.$exception->getMessage(),
                previous: $exception,
            );
        }

        $content = $response->choices[0]->message->content ?? null;

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('The AI assistant returned an empty response.');
        }

        return trim($content);
    }

    private function client(): Client
    {
        $factory = OpenAI::factory()->withApiKey($this->apiKey());

        $baseUrl = (string) config('services.openai.base_url', 'https://api.openai.com/v1');

        if ($baseUrl !== '' && $baseUrl !== 'https://api.openai.com/v1') {
            $factory = $factory->withBaseUri(rtrim($baseUrl, '/'));
        }

        $timeout = (int) config('services.openai.timeout', 30);

        if ($timeout > 0) {
            $factory = $factory->withHttpClient(new \GuzzleHttp\Client([
                'timeout' => $timeout,
                'connect_timeout' => min($timeout, 10),
            ]));
        }

        return $factory->make();
    }

    private function apiKey(): string
    {
        $key = config('services.openai.key') ?? config('services.openai.api_key');

        return is_string($key) ? trim($key) : '';
    }

    /**
     * @param  list<array{role?: mixed, content?: mixed}>  $history
     * @return list<array{role: string, content: string}>
     */
    private function normalizeHistory(array $history): array
    {
        $normalized = [];

        foreach ($history as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $role = $entry['role'] ?? null;
            $content = $entry['content'] ?? null;

            if (! in_array($role, ['user', 'assistant'], true) || ! is_string($content)) {
                continue;
            }

            $trimmed = trim($content);

            if ($trimmed === '') {
                continue;
            }

            $normalized[] = [
                'role' => $role,
                'content' => mb_substr($trimmed, 0, 4000),
            ];
        }

        return array_slice($normalized, -self::MaxHistoryMessages);
    }
}
