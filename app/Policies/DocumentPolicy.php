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
        if ($user->isSuperAdmin() || $user->isNotaryAdmin()) {
            return true;
        }

        // Allow the assigned attorney to view eNOTARY documents
        if ($document->notary_request_id !== null) {
            $notaryRequest = $document->notaryRequest;
            if ($notaryRequest !== null) {
                // Attorney assigned to the request
                if ($notaryRequest->notary_user_id === $user->id) {
                    return true;
                }
                // Client who requested notarization can view their documents
                if ($notaryRequest->user_id === $user->id) {
                    return true;
                }
            }
        }

        if ($user->organization_id === null || $document->organization_id === null) {
            return false;
        }

        if ($user->organization_id !== $document->organization_id) {
            return false;
        }

        if ($user->id === $document->user_id || $user->isOrganizationAdmin()) {
            return true;
        }

        return $document->documentSigners()
            ->where('user_id', $user->id)
            ->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Document $document): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Allow the assigned attorney to update eNOTARY documents (for field preparation)
        if ($document->notary_request_id !== null) {
            $notaryRequest = $document->notaryRequest;
            if ($notaryRequest !== null && $notaryRequest->notary_user_id === $user->id) {
                return true;
            }
        }

        return $this->view($user, $document);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }
}
