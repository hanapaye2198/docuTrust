<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SemaphoreTestCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_fails_without_api_key(): void
    {
        config()->set('services.semaphore.api_key', '');

        $exitCode = $this->artisan('semaphore:test', ['number' => '09171234567']);

        $this->assertSame(1, $exitCode);
    }

    public function test_command_fails_for_invalid_mobile_number(): void
    {
        config()->set('services.semaphore.api_key', 'test-key');

        $exitCode = $this->artisan('semaphore:test', ['number' => '12345']);

        $this->assertSame(1, $exitCode);
    }

    public function test_command_sends_otp_when_configured(): void
    {
        config()->set('services.semaphore.api_key', 'test-key');
        config()->set('services.semaphore.sender_name', 'DocuTrust');

        Http::fake([
            'https://api.semaphore.co/api/v4/otp' => Http::response([
                ['message_id' => 42, 'status' => 'Pending', 'network' => 'Globe'],
            ], 200),
        ]);

        $exitCode = $this->artisan('semaphore:test', [
            'number' => '09171234567',
            '--code' => '654321',
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode);
    }
}
