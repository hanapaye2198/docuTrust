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
        return $user->canManageNotaryRequestPortal()
            || $user->role === UserRole::Notary;
    }

    public function view(User $user, NotaryRequest $notaryRequest): bool
    {
        if ($user->role === UserRole::SuperAdmin) {
            return true;
        }

        // NotaryAdmin can view ALL requests globally (single admin manages all organizations)
        if ($user->role === UserRole::NotaryAdmin) {
            return true;
        }

        if ($user->role === UserRole::Notary) {
            return $notaryRequest->notary_user_id === $user->id;
        }

        if ($user->role === UserRole::Client) {
            if ($notaryRequest->user_id === $user->id) {
                return true;
            }

            if ($user->isEnotaryPortalSigner()) {
                return $user->isNotarySignerOn($notaryRequest);
            }

            return $user->organization_id === $notaryRequest->organization_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Notary;
    }

    public function update(User $user, NotaryRequest $notaryRequest): bool
    {
        if (in_array($notaryRequest->status, [NotaryRequestStatus::Digitalized, NotaryRequestStatus::Notarized, NotaryRequestStatus::Cancelled], true)) {
            return false;
        }

        if (in_array($user->role, [UserRole::SuperAdmin, UserRole::NotaryAdmin], true)) {
            return true;
        }

        if ($user->role === UserRole::Notary) {
            return $notaryRequest->notary_user_id === $user->id;
        }

        return false;
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
        if ($notaryRequest->status !== NotaryRequestStatus::Digitalized) {
            return false;
        }

        if ($user->role === UserRole::SuperAdmin) {
            return true;
        }

        // NotaryAdmin can finalize ANY request globally (single admin manages all organizations)
        return $user->role === UserRole::NotaryAdmin;
    }

    public function cancel(User $user, NotaryRequest $notaryRequest): bool
    {
        return $this->update($user, $notaryRequest);
    }

    public function delete(User $user, NotaryRequest $notaryRequest): bool
    {
        if (in_array($notaryRequest->status, [NotaryRequestStatus::Digitalized, NotaryRequestStatus::Notarized, NotaryRequestStatus::Cancelled], true)) {
            return false;
        }

        if ($user->role === UserRole::SuperAdmin) {
            return true;
        }

        return $notaryRequest->user_id === $user->id
            && $notaryRequest->status === NotaryRequestStatus::Draft;
    }
}
