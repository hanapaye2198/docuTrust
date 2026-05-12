<?php

namespace Database\Seeders;

use App\Enums\OnboardingStep;
use App\Enums\OrganizationRole;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate([
            'email' => 'demo@docutrust.com',
        ], [
            'name' => 'Demo Admin',
            'password' => 'password',
            'email_verified_at' => now(),
            'role' => UserRole::NotaryAdmin,
            'organization_role' => OrganizationRole::Admin,
            'onboarding_step' => OnboardingStep::Completed,
            'mfa_enabled' => true,
            'two_factor_enabled' => false,
            'two_factor_onboarding_completed_at' => now(),
        ]);

        $this->call([
            ENotarySeeder::class,
            DocumentSeeder::class,
            SignerSeeder::class,
            TemplateSeeder::class,
        ]);
    }
}
