<?php

namespace Database\Seeders;

use App\Enums\OnboardingStep;
use App\Enums\OrganizationRole;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds fully-verified test accounts for production/staging testing.
 *
 * Run: php artisan db:seed --class=ProductionTestAccountSeeder
 */
class ProductionTestAccountSeeder extends Seeder
{
    public function run(): void
    {
        // Get the organization from the existing demo account, or use org 1
        $organizationId = User::where('email', 'adminsigner@docutrust.tech')
            ->value('organization_id')
            ?? User::first()?->organization_id
            ?? 1;

        // ─── Notary (Attorney) Account ───────────────────────────────────
        $notary = User::query()->updateOrCreate([
            'email' => 'atty.test@docutrust.tech',
        ], [
            'name' => 'Atty. Test Notary',
            'password' => DatabaseSeeder::DEMO_PASSWORD,
            'email_verified_at' => now(),
            'mobile_number' => '+639170000001',
            'mobile_verified_at' => now(),
            'role' => UserRole::Notary,
            'organization_id' => $organizationId,
            'organization_role' => OrganizationRole::Member,
            'onboarding_step' => OnboardingStep::Completed,
            'mfa_enabled' => true,
            'two_factor_enabled' => false,
            'two_factor_onboarding_completed_at' => now(),
        ]);

        $this->command->info('Notary: atty.test@docutrust.tech / '.DatabaseSeeder::DEMO_PASSWORD." (org: {$notary->organization_id})");

        // ─── Client Account ──────────────────────────────────────────────
        $client = User::query()->updateOrCreate([
            'email' => 'client.test@docutrust.tech',
        ], [
            'name' => 'Test Client',
            'password' => DatabaseSeeder::DEMO_PASSWORD,
            'email_verified_at' => now(),
            'mobile_number' => '+639170000002',
            'mobile_verified_at' => now(),
            'role' => UserRole::Client,
            'organization_id' => $organizationId,
            'organization_role' => OrganizationRole::Member,
            'onboarding_step' => OnboardingStep::Completed,
            'mfa_enabled' => true,
            'two_factor_enabled' => false,
            'two_factor_onboarding_completed_at' => now(),
        ]);

        $this->command->info('Client: client.test@docutrust.tech / '.DatabaseSeeder::DEMO_PASSWORD." (org: {$client->organization_id})");

        // ─── Notary Admin Account ────────────────────────────────────────
        $admin = User::query()->updateOrCreate([
            'email' => 'admin.test@docutrust.tech',
        ], [
            'name' => 'Test Admin',
            'password' => DatabaseSeeder::DEMO_PASSWORD,
            'email_verified_at' => now(),
            'mobile_number' => '+639170000003',
            'mobile_verified_at' => now(),
            'role' => UserRole::NotaryAdmin,
            'organization_id' => $organizationId,
            'organization_role' => OrganizationRole::Admin,
            'onboarding_step' => OnboardingStep::Completed,
            'mfa_enabled' => true,
            'two_factor_enabled' => false,
            'two_factor_onboarding_completed_at' => now(),
        ]);

        $this->command->info('Admin: admin.test@docutrust.tech / '.DatabaseSeeder::DEMO_PASSWORD." (org: {$admin->organization_id})");

        $this->command->newLine();
        $this->command->info('All accounts: password = "'.DatabaseSeeder::DEMO_PASSWORD.'", MFA bypassed, fully onboarded.');
    }
}
