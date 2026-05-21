<?php

namespace Database\Seeders;

use App\Enums\EkycStatus;
use App\Enums\NotaryRequestStatus;
use App\Enums\OnboardingStep;
use App\Enums\OrganizationRole;
use App\Enums\UserRole;
use App\Enums\UserWorkspace;
use App\Models\NotaryRequest;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo accounts for the e-Notary client workspace only.
 *
 * Requires ENotarySeeder (notaryatty@docutrust.tech) to run first.
 *
 * Run: php artisan db:seed --class=ENotarySignerAccountSeeder
 */
class ENotarySignerAccountSeeder extends Seeder
{
    /**
     * @var list<array{email: string, name: string, mobile: string, request_title: string}>
     */
    private const ACCOUNTS = [
        [
            'email' => 'enotarysigner1@docutrust.tech',
            'name' => 'eNotary Signer One',
            'mobile' => '+639172000201',
            'request_title' => 'Affidavit of Loss — Demo Signer One',
        ],
        [
            'email' => 'enotarysigner2@docutrust.tech',
            'name' => 'eNotary Signer Two',
            'mobile' => '+639172000202',
            'request_title' => 'Special Power of Attorney — Demo Signer Two',
        ],
    ];

    public function run(): void
    {
        $notary = User::query()->where('email', 'notaryatty@docutrust.tech')->first();

        if ($notary === null) {
            $this->command?->warn('Skipping eNotary signer accounts: run ENotarySeeder first (notaryatty@docutrust.tech).');

            return;
        }

        foreach (self::ACCOUNTS as $account) {
            $user = User::query()->updateOrCreate([
                'email' => $account['email'],
            ], [
                'organization_id' => $notary->organization_id,
                'name' => $account['name'],
                'password' => 'password',
                'email_verified_at' => now(),
                'role' => UserRole::Client,
                'workspace' => UserWorkspace::Enotary,
                'organization_role' => OrganizationRole::Member,
                'onboarding_step' => OnboardingStep::Completed,
                'ekyc_status' => EkycStatus::Verified,
                'mfa_enabled' => true,
                'two_factor_enabled' => false,
                'two_factor_onboarding_completed_at' => now(),
                'mobile_number' => $account['mobile'],
                'mobile_verified_at' => now(),
            ]);

            NotaryRequest::query()->updateOrCreate([
                'title' => $account['request_title'],
            ], [
                'organization_id' => $notary->organization_id,
                'user_id' => $user->id,
                'notary_user_id' => $notary->id,
                'request_type' => 'acknowledgment',
                'status' => NotaryRequestStatus::Draft,
                'metadata' => [
                    'notes' => 'Demo e-Notary signer account seeded for workspace testing.',
                    'created_from' => 'enotary_signer_account_seeder',
                ],
            ]);

            $this->command?->info("eNotary signer: {$account['email']} / password (assigned to {$notary->email})");
        }
    }
}
