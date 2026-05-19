<?php

namespace Tests\Unit\TrustProfile;

use App\Enums\EkycStatus;
use App\Models\User;
use App\Services\TrustProfile\TrustProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrustProfileServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_trust_score_reaches_maximum_for_fully_verified_user(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'mobile_verified_at' => now(),
            'mfa_enabled' => true,
            'ekyc_status' => EkycStatus::Verified,
            'address' => '123 Main St',
            'nationality' => 'Filipino',
            'date_of_birth' => '1990-01-01',
            'government_id_type' => 'national_id',
            'government_id_number' => 'ID-12345',
            'selfie_verified_at' => now(),
            'gps_permission_granted_at' => now(),
        ]);

        $service = app(TrustProfileService::class);

        $this->assertSame(100, $service->trustScore($user));
        $this->assertTrue($service->isEnotaryReady($user));
    }

    public function test_completion_percent_reflects_partial_profile(): void
    {
        $user = User::factory()->create([
            'profile_photo_path' => null,
            'first_name' => 'Juan',
            'last_name' => 'Cruz',
            'email_verified_at' => now(),
            'mobile_verified_at' => null,
        ]);

        $service = app(TrustProfileService::class);

        $this->assertGreaterThan(0, $service->completionPercent($user));
        $this->assertLessThan(100, $service->completionPercent($user));
    }
}
