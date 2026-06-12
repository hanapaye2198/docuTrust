<?php

namespace App\Http\Controllers;

use App\Services\NotarySchedulingService;
use App\Services\NotarySignerVideoInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EnotarySignerVideoJoinController extends Controller
{
    public function __construct(
        private readonly NotarySignerVideoInvitationService $signerVideoInvitationService,
        private readonly NotarySchedulingService $schedulingService,
    ) {}

    public function show(string $token): View|RedirectResponse
    {
        $session = $this->signerVideoInvitationService->resolveSessionByToken($token);

        if ($session === null) {
            return view('enotary.video-link-invalid');
        }

        if (! in_array($session->status, ['completed', 'cancelled'], true)) {
            $session = $this->schedulingService->confirmSession($session);
        }

        $session->refresh();

        if (is_string($session->meeting_url) && $session->meeting_url !== '') {
            return redirect()->away($session->meeting_url);
        }

        return view('enotary.video-link-invalid');
    }
}
