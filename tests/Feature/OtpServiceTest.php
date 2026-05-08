<?php

namespace Tests\Feature;

use App\Models\Otp;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtpServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_otp_invalidates_previous_active_otp(): void
    {
        $user = User::factory()->create();
        Otp::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'otp_code' => bcrypt('111111'),
            'expires_at' => now()->addMinutes(5),
            'verified_at' => null,
            'attempts' => 0,
        ]);

        config()->set('otp.resend_cooldown_seconds', 0);

        $service = app(OtpService::class);
        $result = $service->generateOtp($user, $user->email, null, 'onboarding', 'email');

        $this->assertTrue($result['success']);
        $this->assertSame(2, Otp::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, Otp::query()->where('user_id', $user->id)->whereNull('verified_at')->count());
    }

    public function test_verify_otp_increments_attempts_for_invalid_code(): void
    {
        $user = User::factory()->create();
        Otp::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'otp_code' => bcrypt('111111'),
            'expires_at' => now()->addMinutes(5),
            'verified_at' => null,
            'attempts' => 0,
        ]);

        $service = app(OtpService::class);
        $result = $service->verifyOtp('999999', $user, $user->email, null);

        $this->assertFalse($result['success']);
        $this->assertSame('otp_invalid', $result['code']);
        $this->assertSame(1, Otp::query()->where('user_id', $user->id)->value('attempts'));
    }

    public function test_verify_otp_fails_when_expired(): void
    {
        $user = User::factory()->create();
        Otp::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'otp_code' => bcrypt('111111'),
            'expires_at' => now()->subMinute(),
            'verified_at' => null,
            'attempts' => 0,
        ]);

        $service = app(OtpService::class);
        $result = $service->verifyOtp('111111', $user, $user->email, null);

        $this->assertFalse($result['success']);
        $this->assertSame('otp_expired', $result['code']);
    }
}
