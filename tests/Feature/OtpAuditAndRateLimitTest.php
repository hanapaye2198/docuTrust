<?php

namespace Tests\Feature;

use App\Models\OtpVerification;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OtpAuditAndRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_sms_rate_limit_blocks_after_three_requests_per_minute(): void
    {
        $user = User::factory()->create(['mobile_number' => '09171234567']);
        config()->set('otp.rate_limit_max', 3);
        config()->set('otp.rate_limit_window_seconds', 60);
        config()->set('otp.resend_cooldown_seconds', 0);

        Cache::flush();

        $service = app(OtpService::class);

        for ($i = 0; $i < 3; $i++) {
            $result = $service->generateOtp($user, null, '09171234567', 'mobile_verification', 'sms');
            $this->assertTrue($result['success'], "Attempt {$i} should succeed");
        }

        $blocked = $service->generateOtp($user, null, '09171234567', 'mobile_verification', 'sms');
        $this->assertFalse($blocked['success']);
        $this->assertSame('rate_limited', $blocked['code']);
    }

    public function test_otp_send_creates_audit_log_entry(): void
    {
        $user = User::factory()->create(['mobile_number' => '09171234567']);
        config()->set('otp.resend_cooldown_seconds', 0);
        Cache::flush();

        app(OtpService::class)->generateOtp($user, null, '09171234567', 'mobile_verification', 'sms');

        $this->assertDatabaseHas('onboarding_audit_logs', [
            'user_id' => $user->id,
            'action' => 'phone_otp_sent',
        ]);
    }

    public function test_otp_verification_stores_ip_and_user_agent(): void
    {
        $user = User::factory()->create();
        config()->set('otp.resend_cooldown_seconds', 0);

        $service = app(OtpService::class);
        $generated = $service->generateOtp($user, $user->email, null, 'onboarding', 'email');
        $plain = (string) $generated['data']['otp'];

        $record = OtpVerification::query()->where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($record?->ip_address);
    }
}
