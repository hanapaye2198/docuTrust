<?php

namespace App\Services;

use App\Models\OnboardingAuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class OnboardingAuditLogger
{
    public function log(User $user, string $action, ?Request $request = null): void
    {
        OnboardingAuditLog::query()->create([
            'user_id' => $user->id,
            'action' => $action,
            'ip_address' => ($request ?? request())->ip(),
        ]);
    }
}
