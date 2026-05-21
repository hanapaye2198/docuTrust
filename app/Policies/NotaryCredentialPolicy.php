<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\NotaryCredential;
use App\Models\User;

class NotaryCredentialPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::SuperAdmin, UserRole::NotaryAdmin], true);
    }

    public function view(User $user, NotaryCredential $credential): bool
    {
        if (in_array($user->role, [UserRole::SuperAdmin, UserRole::NotaryAdmin], true)) {
            return true;
        }

        return $credential->user_id === $user->id;
    }

    public function review(User $user, NotaryCredential $credential): bool
    {
        return in_array($user->role, [UserRole::SuperAdmin, UserRole::NotaryAdmin], true)
            && $credential->isPending();
    }

    public function downloadDocument(User $user, NotaryCredential $credential): bool
    {
        return $this->view($user, $credential);
    }
}
