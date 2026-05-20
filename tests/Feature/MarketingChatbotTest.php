<?php

namespace Tests\Feature;

use App\Services\ChatbotFaqMatcher;
use App\Services\MarketingChatbotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class MarketingChatbotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'chatbot.enabled' => true,
            'services.openai.key' => 'test-openai-key',
            'services.openai.api_key' => 'test-openai-key',
            'services.openai.model' => 'gpt-4o-mini',
            'services.marketing_chatbot.enabled' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_home_page_includes_marketing_chatbot_widget(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('docutrust-chatbot', false)
            ->assertSee(route('ai.chat'), false)
            ->assertSee('Are signatures legally binding?', false);
    }

    public function test_faq_matcher_returns_local_answer_for_keyword(): void
    {
        $matcher = app(ChatbotFaqMatcher::class);

        $answer = $matcher->match('Are digital signatures legally binding?');

        $this->assertNotNull($answer);
        $this->assertStringContainsString('E-Commerce Act', $answer);
    }

    public function test_ai_chat_returns_faq_without_calling_openai(): void
    {
        $this->mock(MarketingChatbotService::class, function ($mock): void {
            $mock->shouldReceive('isOpenAiConfigured')->andReturn(true);
            $mock->shouldNotReceive('reply');
        });

        $this->postJson(route('ai.chat'), [
            'message' => 'Are signatures legally binding?',
        ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'source' => 'faq',
            ])
            ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'E-Commerce Act'));
    }

    public function test_ai_chat_uses_openai_when_no_faq_matches(): void
    {
        $this->mock(MarketingChatbotService::class, function ($mock): void {
            $mock->shouldReceive('isOpenAiConfigured')->andReturn(true);
            $mock->shouldReceive('reply')
                ->once()
                ->andReturn('Custom AI generated answer.');
        });

        $this->postJson(route('ai.chat'), [
            'message' => 'Tell me something very unique about purple elephants',
        ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'source' => 'ai',
                'reply' => 'Custom AI generated answer.',
            ]);
    }

    public function test_ai_chat_returns_fallback_when_openai_fails(): void
    {
        config(['chatbot.fallback_message' => 'Fallback response for testing.']);

        $this->mock(MarketingChatbotService::class, function ($mock): void {
            $mock->shouldReceive('isOpenAiConfigured')->andReturn(true);
            $mock->shouldReceive('reply')
                ->once()
                ->andThrow(new \RuntimeException('quota exceeded'));
        });

        $this->postJson(route('ai.chat'), [
            'message' => 'Unique question with no faq keywords xyz123',
        ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'source' => 'fallback',
                'reply' => 'Fallback response for testing.',
            ]);
    }

    public function test_ai_chat_works_with_faq_only_when_openai_not_configured(): void
    {
        config([
            'services.openai.key' => '',
            'services.openai.api_key' => '',
        ]);

        $this->postJson(route('ai.chat'), [
            'message' => 'How secure is DocuTrust encryption?',
        ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'source' => 'faq',
            ]);
    }

    public function test_chatbot_requires_message(): void
    {
        $this->withoutExceptionHandling();

        $this->expectException(ValidationException::class);

        $this->postJson(route('ai.chat'), []);
    }

    public function test_chatbot_returns_unavailable_when_disabled(): void
    {
        config(['chatbot.enabled' => false]);

        $this->postJson(route('ai.chat'), [
            'message' => 'Hello',
        ])
            ->assertStatus(503)
            ->assertJson([
                'success' => false,
                'reply' => null,
            ]);
    }

    public function test_legacy_marketing_chatbot_route_still_works(): void
    {
        $this->postJson(route('marketing-chatbot.message'), [
            'message' => 'Are signatures legally binding?',
        ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'source' => 'faq',
            ]);
    }
}
