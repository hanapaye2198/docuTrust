<?php

namespace Database\Seeders;

use App\Enums\OnboardingStep;
use App\Enums\OrganizationRole;
use App\Enums\UserRole;
use App\Models\NotaryCredential;
use App\Models\User;
use Illuminate\Database\Seeder;

class ENotarySeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->updateOrCreate([
            'email' => 'enotary@docutrust.com',
        ], [
            'name' => 'Demo E-Notary',
            'password' => 'password',
            'email_verified_at' => now(),
            'role' => UserRole::Notary,
            'organization_role' => OrganizationRole::Member,
            'onboarding_step' => OnboardingStep::Completed,
            'mfa_enabled' => true,
            'two_factor_enabled' => false,
            'two_factor_onboarding_completed_at' => now(),
        ]);

        User::query()->updateOrCreate([
            'email' => 'notaryadmin@docutrust.com',
        ], [
            'organization_id' => $user->organization_id,
            'name' => 'Demo Notary Admin',
            'password' => 'password',
            'email_verified_at' => now(),
            'role' => UserRole::NotaryAdmin,
            'organization_role' => OrganizationRole::Admin,
            'onboarding_step' => OnboardingStep::Completed,
            'mfa_enabled' => true,
            'two_factor_enabled' => false,
            'two_factor_onboarding_completed_at' => now(),
        ]);

        User::query()->updateOrCreate([
            'email' => 'client@docutrust.com',
        ], [
            'organization_id' => $user->organization_id,
            'name' => 'Demo Client',
            'password' => 'password',
            'email_verified_at' => now(),
            'role' => UserRole::Client,
            'organization_role' => OrganizationRole::Member,
            'onboarding_step' => OnboardingStep::Completed,
            'mfa_enabled' => true,
            'two_factor_enabled' => false,
            'two_factor_onboarding_completed_at' => now(),
        ]);

        NotaryCredential::query()->updateOrCreate([
            'user_id' => $user->id,
            'commission_number' => 'CN-2026-0001',
        ], [
            'commission_jurisdiction' => 'Philippines',
            'commission_issued_at' => now()->subYear()->toDateString(),
            'commission_expires_at' => now()->addYear()->toDateString(),
            'roll_number' => '12345',
            'ibp_number' => 'IBP-2026-0001',
            'ptr_number' => 'PTR-2026-0001',
            'mcle_compliance_number' => 'MCLE-2026-0001',
            'status' => 'active',
        ]);
    }
}
