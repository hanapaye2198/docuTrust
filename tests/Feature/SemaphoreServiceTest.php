<?php

namespace Tests\Feature;

use App\Services\SemaphoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SemaphoreServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_otp_returns_structured_success_response(): void
    {
        config()->set('services.semaphore.api_key', 'test-key');
        config()->set('services.semaphore.sender_name', 'DocuTrust');

        Http::fake([
            'https://api.semaphore.co/api/v4/otp' => Http::response([
                [
                    'message_id' => 99,
                    'code' => '482991',
                ],
            ], 200),
        ]);

        $result = app(SemaphoreService::class)->sendOtp('09171234567', 'DocuTrust OTP: {otp}', '482991');

        $this->assertTrue($result['success']);
        $this->assertSame(99, $result['message_id']);
        $this->assertSame('semaphore', $result['provider']);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.semaphore.co/api/v4/otp'
                && $request['number'] === '639171234567'
                && $request['code'] === '482991'
                && $request['sendername'] === 'DocuTrust';
        });
    }

    public function test_send_otp_returns_failure_when_api_key_missing(): void
    {
        config()->set('services.semaphore.api_key', '');

        $this->expectException(\RuntimeException::class);

        app(SemaphoreService::class)->sendOtp('09171234567', 'DocuTrust OTP: 123456', '123456');
    }
}
