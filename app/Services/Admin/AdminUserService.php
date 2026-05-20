<?php

namespace App\Services\Admin;

use App\Enums\OnboardingStep;
use App\Enums\OrganizationRole;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AdminUserService
{
    public function __construct(
        private readonly UserDeletionImpactService $deletionImpactService,
    ) {}

    /**
     * @param  array{
     *   name: string,
     *   email: string,
     *   role: string,
     *   organization_id: int|null,
     *   password?: string|null
     * }  $attributes
     */
    public function create(array $attributes): User
    {
        $role = UserRole::from($attributes['role']);
        $organizationId = $attributes['organization_id'] ?? null;

        if ($role === UserRole::SuperAdmin) {
            throw new RuntimeException(__('Cannot create super administrator accounts from this screen.'));
        }

        if ($organizationId === null) {
            $organization = Organization::query()->create([
                'name' => $attributes['name'].' Organization',
                'slug' => str()->slug($attributes['name']).'-'.str()->lower(str()->random(6)),
                'plan' => 'free',
                'subscription_status' => 'active',
            ]);
            $organizationId = $organization->id;
        }

        return User::query()->create([
            'name' => $attributes['name'],
            'email' => strtolower($attributes['email']),
            'password' => Hash::make($attributes['password'] ?? 'password'),
            'role' => $role,
            'organization_id' => $organizationId,
            'organization_role' => OrganizationRole::Admin,
            'email_verified_at' => now(),
            'onboarding_step' => OnboardingStep::Completed,
            'mfa_enabled' => true,
            'two_factor_onboarding_completed_at' => now(),
        ]);
    }

    /**
     * @param  array{name: string, email: string, role: string, organization_id: int|null}  $attributes
     */
    public function update(User $user, array $attributes): User
    {
        if ($user->role === UserRole::SuperAdmin && UserRole::from($attributes['role']) !== UserRole::SuperAdmin) {
            throw new RuntimeException(__('Cannot change super administrator role.'));
        }

        $user->forceFill([
            'name' => $attributes['name'],
            'email' => strtolower($attributes['email']),
            'role' => UserRole::from($attributes['role']),
            'organization_id' => $attributes['organization_id'],
        ])->save();

        return $user->refresh();
    }

    public function deactivate(User $user): void
    {
        $user->forceFill(['deactivated_at' => now()])->save();

        Log::channel('audit')->info('User deactivated by platform admin', [
            'target_user_id' => $user->id,
            'actor_user_id' => auth()->id(),
        ]);
    }

    public function reactivate(User $user): void
    {
        $user->forceFill(['deactivated_at' => null])->save();

        Log::channel('audit')->info('User reactivated by platform admin', [
            'target_user_id' => $user->id,
            'actor_user_id' => auth()->id(),
        ]);
    }

    public function delete(User $user, User $actor): void
    {
        if ($actor->id === $user->id) {
            throw new RuntimeException(__('You cannot delete your own account from the admin panel.'));
        }

        $impact = $this->deletionImpactService->for($user);

        if (! $impact['can_hard_delete']) {
            throw new RuntimeException($impact['block_reason'] ?? __('This user cannot be deleted.'));
        }

        Log::channel('audit')->warning('User permanently deleted by platform admin', [
            'target_user_id' => $user->id,
            'target_email' => $user->email,
            'actor_user_id' => $actor->id,
            'impact' => $impact,
        ]);

        $user->delete();
    }
}
