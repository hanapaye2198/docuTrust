<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Document $document): bool
    {
        if ($user->organization_id === null || $document->organization_id === null) {
            return false;
        }

        if ($user->organization_id !== $document->organization_id) {
            return false;
        }

        return $user->id === $document->user_id || $user->isOrganizationAdmin();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }
}
