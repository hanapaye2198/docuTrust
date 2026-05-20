<?php

namespace Database\Seeders;

use App\Enums\EkycStatus;
use App\Enums\OnboardingStep;
use App\Enums\OrganizationRole;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()->firstOrCreate(
            ['slug' => 'docutrust-platform'],
            [
                'name' => 'DocuTrust Platform',
                'plan' => 'enterprise',
                'subscription_status' => 'active',
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'superadmin@docutrust.tech'],
            [
                'organization_id' => $organization->id,
                'name' => 'Super Administrator',
                'first_name' => 'Super',
                'last_name' => 'Administrator',
                'password' => 'password',
                'email_verified_at' => now(),
                'role' => UserRole::SuperAdmin,
                'organization_role' => OrganizationRole::Admin,
                'onboarding_step' => OnboardingStep::Completed,
                'ekyc_status' => EkycStatus::Verified,
                'mfa_enabled' => true,
                'two_factor_enabled' => false,
                'two_factor_onboarding_completed_at' => now(),
                'deactivated_at' => null,
            ],
        );
    }
}
