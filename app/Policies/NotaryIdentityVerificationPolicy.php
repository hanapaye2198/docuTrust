<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\NotaryIdentityVerification;
use App\Models\User;

class NotaryIdentityVerificationPolicy
{
    public function review(User $user, NotaryIdentityVerification $verification): bool
    {
        $request = $verification->notaryRequest;

        return $user->can('view', $request)
            && $user->role === UserRole::Notary
            && $request->notary_user_id === $user->id;
    }
}
