<?php

namespace App\Data;

class ChatbotReply
{
    public const SourceFaq = 'faq';

    public const SourceAi = 'ai';

    public const SourceFallback = 'fallback';

    public function __construct(
        public readonly string $reply,
        public readonly string $source,
        public readonly bool $success = true,
    ) {}

    /**
     * @return array{success: bool, reply: string, source: string, enabled: bool}
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'reply' => $this->reply,
            'source' => $this->source,
            'enabled' => true,
        ];
    }
}
