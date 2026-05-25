<?php

namespace App\Services;

use App\Enums\NotaryRequestStatus;
use App\Mail\NotarySignerVideoInvitationMail;
use App\Models\Document;
use App\Models\NotaryRequest;
use App\Models\NotarySession;
use App\Models\NotarySigner;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

class NotarySignerVideoInvitationService
{
    public function __construct(
        private readonly NotaryRequestWorkflowService $notaryRequestWorkflowService,
        private readonly NotaryJitsiRoomService $jitsiRoomService,
    ) {}

    public function shouldAutoInviteAfterSigning(): bool
    {
        return (bool) config('docutrust.notary.auto_invite_signers_to_video', true);
    }

    public function handleDocumentCompleted(Document $document): void
    {
        if ($document->notary_request_id === null || ! $this->shouldAutoInviteAfterSigning()) {
            return;
        }

        $request = NotaryRequest::query()
            ->with(['documents.documentSigners', 'signers', 'sessions'])
            ->find($document->notary_request_id);

        if ($request === null) {
            return;
        }

        $this->ensureRequestSubmitted($request);

        if (! $this->notaryRequestWorkflowService->documentsReadyForSession($request->fresh(['documents.documentSigners']))) {
            return;
        }

        $this->inviteAllSignersWhenReady($request->fresh(['signers', 'sessions', 'notary']));
    }

    public function inviteAllSignersWhenReady(NotaryRequest $request): int
    {
        if (! config('docutrust.notary.require_video_session', true)) {
            return 0;
        }

        $request->loadMissing(['signers', 'sessions', 'notary']);

        if (! $this->notaryRequestWorkflowService->documentsReadyForSession($request)) {
            throw new RuntimeException(__('All parties must finish signing before video invitations can be sent.'));
        }

        $scheduledFor = now()->addHours((int) config('docutrust.notary.signer_video_default_hours_ahead', 24));
        $invited = 0;

        foreach ($this->eligibleSigners($request) as $signer) {
            $session = $this->sessionForSigner($request, $signer);

            if ($session === null) {
                $session = $this->createSignerSession($request, $signer, $scheduledFor);
            }

            if ($session->invitation_sent_at !== null) {
                continue;
            }

            $this->sendInvitation($request, $signer, $session);
            $invited++;
        }

        if ($invited > 0 && in_array($request->status, [
            NotaryRequestStatus::Submitted,
            NotaryRequestStatus::IdentityReviewRequired,
            NotaryRequestStatus::IdentityVerified,
            NotaryRequestStatus::LocationReviewRequired,
            NotaryRequestStatus::LocationVerified,
        ], true)) {
            $request->forceFill([
                'status' => NotaryRequestStatus::SessionScheduled,
            ])->save();
        }

        return $invited;
    }

    public function signerVideoJoinUrl(NotarySession $session): string
    {
        if (! is_string($session->access_token) || $session->access_token === '') {
            return (string) $session->meeting_url;
        }

        return route('enotary.video.join', ['token' => $session->access_token]);
    }

    public function resolveSessionByToken(string $token): ?NotarySession
    {
        return NotarySession::query()
            ->where('access_token', $token)
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->with(['notaryRequest', 'notarySigner'])
            ->first();
    }

    /**
     * @return list<NotarySigner>
     */
    private function eligibleSigners(NotaryRequest $request): array
    {
        return $request->signers
            ->filter(fn (NotarySigner $signer): bool => is_string($signer->email) && $signer->email !== '')
            ->values()
            ->all();
    }

    private function sessionForSigner(NotaryRequest $request, NotarySigner $signer): ?NotarySession
    {
        return $request->sessions
            ->first(fn (NotarySession $session): bool => (int) $session->notary_signer_id === (int) $signer->id);
    }

    private function createSignerSession(
        NotaryRequest $request,
        NotarySigner $signer,
        \DateTimeInterface $scheduledFor,
    ): NotarySession {
        $roomName = $this->jitsiRoomService->buildRoomNameForSigner($request, $signer);

        return $request->sessions()->create([
            'notary_user_id' => $request->notary_user_id,
            'notary_signer_id' => $signer->id,
            'provider_name' => 'jitsi',
            'status' => 'scheduled',
            'room_name' => $roomName,
            'meeting_url' => $this->jitsiRoomService->meetingUrl($roomName),
            'access_token' => (string) Str::uuid(),
            'scheduled_for' => $scheduledFor,
        ]);
    }

    private function sendInvitation(NotaryRequest $request, NotarySigner $signer, NotarySession $session): void
    {
        Mail::to($signer->email)->queue(
            new NotarySignerVideoInvitationMail(
                $request,
                $signer,
                $session,
                $this->signerVideoJoinUrl($session),
            ),
        );

        $session->forceFill(['invitation_sent_at' => now()])->save();
    }

    private function ensureRequestSubmitted(NotaryRequest $request): void
    {
        if ($request->status !== NotaryRequestStatus::Draft) {
            return;
        }

        try {
            $this->notaryRequestWorkflowService->submit($request);
        } catch (RuntimeException) {
            // Request may not be submittable yet; video invites wait until submitted.
        }
    }
}
