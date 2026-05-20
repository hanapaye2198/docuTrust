<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->isSuperAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->isSuperAdmin();
    }

    public function delete(User $actor, User $user): bool
    {
        if (! $actor->isSuperAdmin()) {
            return false;
        }

        if ($actor->id === $user->id) {
            return false;
        }

        if ($user->role === UserRole::SuperAdmin) {
            return false;
        }

        return true;
    }

    public function deactivate(User $actor, User $user): bool
    {
        if (! $actor->isSuperAdmin()) {
            return false;
        }

        if ($actor->id === $user->id) {
            return false;
        }

        if ($user->role === UserRole::SuperAdmin) {
            return false;
        }

        return true;
    }
}
