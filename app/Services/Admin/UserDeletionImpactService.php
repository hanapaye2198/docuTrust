<?php

namespace App\Services\Admin;

use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\NotaryRequest;
use App\Models\User;

class UserDeletionImpactService
{
    /**
     * @return array{
     *   role: string,
     *   documents_total: int,
     *   documents_completed: int,
     *   documents_pending: int,
     *   notary_requests_as_requester: int,
     *   assigned_notary_requests: int,
     *   templates_count: int,
     *   contacts_count: int,
     *   has_notary_credential: bool,
     *   can_hard_delete: bool,
     *   block_reason: string|null,
     *   warning_message: string
     * }
     */
    public function for(User $user): array
    {
        $documentsTotal = Document::query()->where('user_id', $user->id)->count();
        $documentsCompleted = Document::query()
            ->where('user_id', $user->id)
            ->where('status', DocumentStatus::Completed)
            ->count();
        $documentsPending = Document::query()
            ->where('user_id', $user->id)
            ->where('status', DocumentStatus::Pending)
            ->count();

        $notaryRequests = NotaryRequest::query()->where('user_id', $user->id)->count();
        $assignedNotaryRequests = NotaryRequest::query()->where('notary_user_id', $user->id)->count();

        $blockReason = null;
        $canHardDelete = true;

        if ($user->role === UserRole::SuperAdmin) {
            $canHardDelete = false;
            $blockReason = __('Super administrator accounts cannot be deleted.');
        }

        if ($user->role === UserRole::Client && $documentsCompleted > 0) {
            $canHardDelete = false;
            $blockReason = __('Clients with completed documents cannot be permanently deleted. Deactivate the account instead.');
        }

        $warning = match ($user->role) {
            UserRole::Client => __('Deleting this client permanently removes all documents, notarizations, templates, contacts, and trust data.'),
            UserRole::Notary => __('Deleting this notary removes their credentials and unlinks them from assigned notarizations.'),
            UserRole::NotaryAdmin => __('Deleting this administrator removes their access and cascades owned workspace data.'),
            UserRole::SuperAdmin => __('Super administrator accounts cannot be deleted.'),
        };

        return [
            'role' => $user->role->value,
            'documents_total' => $documentsTotal,
            'documents_completed' => $documentsCompleted,
            'documents_pending' => $documentsPending,
            'notary_requests_as_requester' => $notaryRequests,
            'assigned_notary_requests' => $assignedNotaryRequests,
            'templates_count' => $user->templates()->count(),
            'contacts_count' => $user->contacts()->count(),
            'has_notary_credential' => $user->notaryCredential()->exists(),
            'can_hard_delete' => $canHardDelete,
            'block_reason' => $blockReason,
            'warning_message' => $warning,
        ];
    }
}
