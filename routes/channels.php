<?php

use App\Models\NotaryRequest;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('notary-request.{notaryRequestId}', function ($user, int $notaryRequestId): bool {
    $request = NotaryRequest::query()->find($notaryRequestId);

    return $request !== null && $user->can('view', $request);
});
