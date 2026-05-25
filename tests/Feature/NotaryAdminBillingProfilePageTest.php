<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\BillingProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt as LivewireVolt;
use Tests\TestCase;

class NotaryAdminBillingProfilePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_notary_admin_can_create_billing_profile_from_page(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::NotaryAdmin,
        ]);

        $this->actingAs($admin);

        LivewireVolt::test('notary-admin.billing-profile')
            ->set('registeredName', 'DocuTrust Test Seller')
            ->set('tin', '123-456-789-000')
            ->set('branchCode', '000')
            ->set('email', 'billing@example.test')
            ->set('phone', '+639171234567')
            ->set('addressLine', '123 Test Street')
            ->set('city', 'Davao City')
            ->set('state', 'Davao del Sur')
            ->set('postalCode', '8000')
            ->set('countryCode', 'PH')
            ->set('eisEnvironment', 'sandbox')
            ->set('eisAccreditationId', 'ACCRED-1')
            ->set('eisApplicationId', 'APP-1')
            ->set('eisUsername', 'eis-user')
            ->set('eisPassword', 'eis-pass')
            ->set('eisCertificateId', 'CERT-1')
            ->set('isActive', true)
            ->call('save')
            ->assertHasNoErrors();

        $profile = BillingProfile::query()->where('organization_id', $admin->organization_id)->first();

        $this->assertNotNull($profile);
        $this->assertSame('DocuTrust Test Seller', $profile->registered_name);
        $this->assertSame('ACCRED-1', $profile->eis_accreditation_id);
        $this->assertTrue($profile->is_active);
        $this->assertSame('eis-pass', $profile->eis_password);
    }

    public function test_notary_admin_can_update_existing_billing_profile_without_overwriting_password(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::NotaryAdmin,
        ]);

        $profile = BillingProfile::query()->create([
            'organization_id' => $admin->organization_id,
            'registered_name' => 'Old Seller',
            'tin' => '000-000-000-000',
            'branch_code' => '001',
            'email' => 'old@example.test',
            'eis_environment' => 'sandbox',
            'eis_accreditation_id' => 'OLD-ACCRED',
            'eis_application_id' => 'OLD-APP',
            'eis_username' => 'old-user',
            'eis_password' => 'keep-me',
            'eis_certificate_id' => 'OLD-CERT',
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        LivewireVolt::test('notary-admin.billing-profile')
            ->set('registeredName', 'Updated Seller')
            ->set('tin', '123-456-789-000')
            ->set('branchCode', '000')
            ->set('email', 'updated@example.test')
            ->set('eisAccreditationId', 'NEW-ACCRED')
            ->set('eisApplicationId', 'NEW-APP')
            ->set('eisUsername', 'new-user')
            ->set('eisPassword', '')
            ->set('eisCertificateId', 'NEW-CERT')
            ->call('save')
            ->assertHasNoErrors();

        $profile->refresh();

        $this->assertSame('Updated Seller', $profile->registered_name);
        $this->assertSame('NEW-ACCRED', $profile->eis_accreditation_id);
        $this->assertSame('keep-me', $profile->eis_password);
    }

    public function test_client_cannot_access_billing_profile_page(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)
            ->get(route('notary-admin.billing-profile'))
            ->assertRedirect(route($client->homeRouteName()));
    }
}
