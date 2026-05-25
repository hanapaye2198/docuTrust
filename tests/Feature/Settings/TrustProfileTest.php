<?php

namespace Tests\Feature\Settings;

use App\Enums\EkycStatus;
use App\Models\User;
use App\Services\TrustProfile\TrustProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TrustProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_trust_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.trust-profile'))
            ->assertOk()
            ->assertSee('Trust & verification');
    }

    public function test_trust_profile_shows_verified_mobile_badge_when_mobile_is_verified(): void
    {
        $user = User::factory()->create([
            'mobile_number' => '+639171234567',
            'mobile_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('settings.trust-profile'))
            ->assertOk()
            ->assertSee('Mobile verified')
            ->assertSee('+639171234567');
    }

    public function test_trust_profile_prompts_mobile_verification_when_number_is_unverified(): void
    {
        $user = User::factory()->create([
            'mobile_number' => '+639171234567',
            'mobile_verified_at' => null,
        ]);

        $this->actingAs($user)
            ->get(route('settings.trust-profile'))
            ->assertOk()
            ->assertSee('Mobile not verified')
            ->assertSee('Verify now');
    }

    public function test_legal_identity_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Volt::test('settings.trust-profile')
            ->set('address', '123 Rizal Avenue, Manila')
            ->set('nationality', 'Filipino')
            ->set('date_of_birth', '1992-05-10')
            ->set('government_id_type', 'national_id')
            ->set('government_id_number', 'PH-998877')
            ->call('updateLegalIdentity')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertSame('123 Rizal Avenue, Manila', $user->address);
        $this->assertSame('Filipino', $user->nationality);
        $this->assertSame('PH-998877', $user->government_id_number);
    }

    public function test_gps_permission_can_be_granted(): void
    {
        $user = User::factory()->create(['gps_permission_granted_at' => null]);

        $this->actingAs($user);

        Volt::test('settings.trust-profile')
            ->call('grantGpsPermission')
            ->assertHasNoErrors();

        $this->assertNotNull($user->refresh()->gps_permission_granted_at);
    }

    public function test_trust_profile_service_reports_enotary_readiness(): void
    {
        $user = User::factory()->create([
            'mobile_verified_at' => now(),
            'ekyc_status' => EkycStatus::Verified,
            'address' => 'Addr',
            'nationality' => 'PH',
            'date_of_birth' => '1990-01-01',
            'government_id_type' => 'national_id',
            'government_id_number' => '1',
            'selfie_verified_at' => now(),
            'gps_permission_granted_at' => now(),
        ]);

        $this->assertTrue(app(TrustProfileService::class)->isEnotaryReady($user));
    }
}
