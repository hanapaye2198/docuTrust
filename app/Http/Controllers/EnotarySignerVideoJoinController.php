<?php

namespace App\Http\Controllers;

use App\Models\NotarySession;
use App\Services\NotarySchedulingService;
use App\Services\NotarySignerVideoInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class EnotarySignerVideoJoinController extends Controller
{
    public function __construct(
        private readonly NotarySignerVideoInvitationService $signerVideoInvitationService,
        private readonly NotarySchedulingService $schedulingService,
    ) {}

    public function show(string $token): View
    {
        $session = $this->signerVideoInvitationService->resolveSessionByToken($token);

        if ($session === null) {
            return view('enotary.video-link-invalid');
        }

        if (! in_array($session->status, ['completed', 'cancelled'], true)) {
            $session = $this->schedulingService->confirmSession($session);
        }

        NotarySession::query()
            ->whereKey($session->id)
            ->whereNull('joined_at')
            ->update(['joined_at' => now()]);

        $session->refresh();

        if (is_string($session->meeting_url) && $session->meeting_url !== '') {
            return view('enotary.video-waiting-room', [
                'session' => $session->loadMissing(['notaryRequest', 'notarySigner']),
                'meetingUrl' => $session->meeting_url,
            ]);
        }

        return view('enotary.video-link-invalid');
    }

    public function status(string $token): JsonResponse
    {
        $session = NotarySession::query()
            ->where('access_token', $token)
            ->whereIn('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])
            ->first();

        abort_if($session === null, 404);

        $status = (string) $session->status;

        return response()->json([
            'status' => $status,
            'ended' => in_array($status, ['completed', 'cancelled'], true),
            'completed' => $status === 'completed',
            'cancelled' => $status === 'cancelled',
            'ended_at' => $session->ended_at?->toIso8601String(),
        ]);
    }
}
