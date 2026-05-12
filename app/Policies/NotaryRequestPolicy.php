<?php

namespace App\Policies;

use App\Enums\NotaryRequestStatus;
use App\Enums\UserRole;
use App\Models\NotaryRequest;
use App\Models\User;

class NotaryRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [
            UserRole::SuperAdmin,
            UserRole::NotaryAdmin,
            UserRole::Client,
            UserRole::Notary,
        ], true);
    }

    public function view(User $user, NotaryRequest $notaryRequest): bool
    {
        if ($user->role === UserRole::SuperAdmin) {
            return true;
        }

        if ($user->role === UserRole::Notary) {
            return $notaryRequest->notary_user_id === $user->id;
        }

        return $user->organization_id === $notaryRequest->organization_id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [
            UserRole::SuperAdmin,
            UserRole::NotaryAdmin,
            UserRole::Client,
            UserRole::Notary,
        ], true);
    }

    public function update(User $user, NotaryRequest $notaryRequest): bool
    {
        if (in_array($notaryRequest->status, [NotaryRequestStatus::Notarized, NotaryRequestStatus::Cancelled], true)) {
            return false;
        }

        if ($user->role === UserRole::SuperAdmin) {
            return true;
        }

        if ($user->role === UserRole::Notary) {
            return $notaryRequest->notary_user_id === $user->id;
        }

        return $user->organization_id === $notaryRequest->organization_id;
    }

    public function approve(User $user, NotaryRequest $notaryRequest): bool
    {
        if ($user->role !== UserRole::Notary) {
            return false;
        }

        return $notaryRequest->notary_user_id === $user->id;
    }

    public function reject(User $user, NotaryRequest $notaryRequest): bool
    {
        return $this->approve($user, $notaryRequest);
    }

    public function finalize(User $user, NotaryRequest $notaryRequest): bool
    {
        if ($notaryRequest->status !== NotaryRequestStatus::AttorneyApproved) {
            return false;
        }

        if ($user->role === UserRole::SuperAdmin) {
            return true;
        }

        return $user->organization_id === $notaryRequest->organization_id
            && in_array($user->role, [UserRole::NotaryAdmin, UserRole::Client], true);
    }

    public function cancel(User $user, NotaryRequest $notaryRequest): bool
    {
        return $this->update($user, $notaryRequest);
    }

    public function delete(User $user, NotaryRequest $notaryRequest): bool
    {
        if (in_array($notaryRequest->status, [NotaryRequestStatus::Notarized, NotaryRequestStatus::Cancelled], true)) {
            return false;
        }

        if ($user->role === UserRole::SuperAdmin) {
            return true;
        }

        return $notaryRequest->user_id === $user->id
            && $notaryRequest->status === NotaryRequestStatus::Draft;
    }
}
