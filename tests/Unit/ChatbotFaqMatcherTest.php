<?php

namespace Tests\Unit;

use App\Services\ChatbotFaqMatcher;
use Tests\TestCase;

class ChatbotFaqMatcherTest extends TestCase
{
    public function test_matches_keywords_case_insensitively(): void
    {
        $matcher = app(ChatbotFaqMatcher::class);

        $answer = $matcher->match('BLOCKCHAIN verification please');

        $this->assertNotNull($answer);
        $this->assertStringContainsString('blockchain', strtolower($answer));
    }

    public function test_returns_null_when_no_keyword_matches(): void
    {
        $matcher = app(ChatbotFaqMatcher::class);

        $this->assertNull($matcher->match('xyzzy completely unrelated topic 999'));
    }
}
