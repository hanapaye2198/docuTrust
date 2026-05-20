<?php

namespace App\Services;

class ChatbotFaqMatcher
{
    public function match(string $message): ?string
    {
        $normalized = $this->normalize($message);

        if ($normalized === '') {
            return null;
        }

        foreach ($this->faqs() as $faq) {
            if ($this->matchesFaq($normalized, $faq)) {
                return $faq['answer'];
            }
        }

        return null;
    }

    /**
     * @param  array{keywords: list<string>, answer: string}  $faq
     */
    private function matchesFaq(string $normalizedMessage, array $faq): bool
    {
        foreach ($faq['keywords'] as $keyword) {
            $keyword = $this->normalize((string) $keyword);

            if ($keyword !== '' && str_contains($normalizedMessage, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $message): string
    {
        return mb_strtolower(trim($message));
    }

    /**
     * @return list<array{keywords: list<string>, answer: string}>
     */
    private function faqs(): array
    {
        $faqs = config('chatbot.faqs', []);

        return is_array($faqs) ? $faqs : [];
    }
}
