<?php

namespace App\Policies;

use App\Models\NotarySigner;
use App\Models\User;

class NotarySignerPolicy
{
    public function view(User $user, NotarySigner $signer): bool
    {
        return $user->can('view', $signer->notaryRequest);
    }

    public function update(User $user, NotarySigner $signer): bool
    {
        return $user->can('update', $signer->notaryRequest);
    }

    public function delete(User $user, NotarySigner $signer): bool
    {
        return $user->can('delete', $signer->notaryRequest);
    }
}
