<?php

namespace App\Services;

use App\Enums\NotaryRequestStatus;
use App\Mail\NotarySignerVideoInvitationMail;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\NotaryRequest;
use App\Models\NotarySession;
use App\Models\NotarySigner;
use App\Models\User;
use Illuminate\Support\Collection;
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

    public function usesPerSignerSessions(): bool
    {
        return (bool) config('docutrust.notary.require_video_session', true)
            && $this->shouldAutoInviteAfterSigning();
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

        $this->inviteAllSignersWhenReady($request->fresh(['signers', 'sessions', 'notary', 'documents.documentSigners']));
    }

    /**
     * Create one video session per signed party and optionally email personal join links.
     */
    public function inviteAllSignersWhenReady(NotaryRequest $request, bool $forceResend = false, bool $deliverSynchronously = false): int
    {
        if (! config('docutrust.notary.require_video_session', true)) {
            return 0;
        }

        $request->loadMissing(['signers', 'sessions', 'notary', 'documents.documentSigners']);

        $this->ensureRequestSubmitted($request);

        if (! $this->notaryRequestWorkflowService->documentsReadyForSession($request)) {
            throw new RuntimeException(__('All parties must finish signing before video invitations can be sent.'));
        }

        $this->ensureIndividualSessionsExist($request);

        $scheduledFor = now()->addHours((int) config('docutrust.notary.signer_video_default_hours_ahead', 24));
        $invited = 0;

        foreach ($this->signedPartiesForVideo($request) as $signer) {
            $session = $this->sessionForSigner($request, $signer);

            if (! $session instanceof NotarySession) {
                $session = $this->createSignerSession($request, $signer, $scheduledFor);
                $request->load('sessions');
            }

            if ($session->invitation_sent_at !== null && ! $forceResend) {
                continue;
            }

            $this->sendInvitation($request, $signer, $session, $deliverSynchronously);
            $invited++;
        }

        $this->markSessionScheduledIfNeeded($request->fresh());

        return $invited;
    }

    /**
     * @return list<NotarySigner>
     */
    public function signedPartiesForVideo(NotaryRequest $request): array
    {
        $request->loadMissing(['signers', 'documents.documentSigners']);

        if (! $this->notaryRequestWorkflowService->documentsReadyForSession($request)) {
            return [];
        }

        return $this->resolveSignedParties($request);
    }

    /**
     * @return list<array{
     *   notary_signer_id: int,
     *   full_name: string,
     *   email: string,
     *   has_signed: bool,
     *   signed_at: string|null,
     *   session_id: int|null,
     *   session_status: string|null,
     *   join_url: string|null,
     *   meeting_url: string|null,
     *   room_name: string|null,
     *   invitation_sent_at: string|null,
     *   invitation_sent_label: string|null
     * }>
     */
    public function partiesForVideoVerification(NotaryRequest $request): array
    {
        $request->loadMissing(['signers', 'sessions.notarySigner', 'documents.documentSigners', 'notary']);

        $documentSigners = $this->clientDocumentSigners($request);
        $allPartiesSigned = $this->notaryRequestWorkflowService->documentsReadyForSession($request);

        if ($allPartiesSigned) {
            $this->ensureIndividualSessionsExist($request);
            $request->load(['signers', 'sessions']);
        }

        return $documentSigners
            ->filter(fn (DocumentSigner $signer): bool => is_string($signer->email) && trim($signer->email) !== '')
            ->unique(fn (DocumentSigner $signer): string => strtolower(trim($signer->email)))
            ->map(function (DocumentSigner $documentSigner) use ($request, $allPartiesSigned): array {
                $party = $allPartiesSigned
                    ? $this->resolveNotarySignerForDocumentSigner($request, $documentSigner)
                    : $request->signers->first(
                        fn (NotarySigner $signer): bool => strtolower(trim((string) $signer->email)) === strtolower(trim((string) $documentSigner->email))
                    );

                $session = $party instanceof NotarySigner
                    ? $this->sessionForSigner($request, $party)
                    : null;

                $hasSigned = $documentSigner->status->isCompleted();

                return [
                    'notary_signer_id' => $party?->id,
                    'full_name' => $party?->full_name ?? trim((string) $documentSigner->name),
                    'email' => trim((string) $documentSigner->email),
                    'has_signed' => $hasSigned,
                    'signed_at' => $documentSigner->signed_at?->timezone(
                        config('docutrust.notary.timezone', 'Asia/Manila')
                    )?->format('M j, Y g:i A'),
                    'session_id' => $session?->id,
                    'session_status' => $session?->status,
                    'join_url' => $session instanceof NotarySession ? $this->signerVideoJoinUrl($session) : null,
                    'meeting_url' => $session?->meeting_url,
                    'room_name' => $session?->room_name,
                    'invitation_sent_at' => $session?->invitation_sent_at?->toDateTimeString(),
                    'invitation_sent_label' => $session?->invitation_sent_at?->diffForHumans(),
                ];
            })
            ->values()
            ->all();
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

    private function ensureIndividualSessionsExist(NotaryRequest $request): void
    {
        $scheduledFor = now()->addHours((int) config('docutrust.notary.signer_video_default_hours_ahead', 24));

        foreach ($this->signedPartiesForVideo($request) as $signer) {
            if ($this->sessionForSigner($request, $signer) === null) {
                $this->createSignerSession($request, $signer, $scheduledFor);
                $request->load('sessions');
            }
        }
    }

    /**
     * @return list<NotarySigner>
     */
    private function resolveSignedParties(NotaryRequest $request): array
    {
        return $this->clientDocumentSigners($request)
            ->filter(fn (DocumentSigner $signer): bool => $signer->status->isCompleted())
            ->filter(fn (DocumentSigner $signer): bool => is_string($signer->email) && trim($signer->email) !== '')
            ->unique(fn (DocumentSigner $signer): string => strtolower(trim($signer->email)))
            ->map(fn (DocumentSigner $documentSigner): NotarySigner => $this->resolveNotarySignerForDocumentSigner($request, $documentSigner))
            ->values()
            ->all();
    }

    private function resolveNotarySignerForDocumentSigner(NotaryRequest $request, DocumentSigner $documentSigner): NotarySigner
    {
        $email = strtolower(trim((string) $documentSigner->email));

        $existing = $request->signers->first(
            fn (NotarySigner $signer): bool => strtolower(trim((string) $signer->email)) === $email
        );

        if ($existing instanceof NotarySigner) {
            if (trim((string) $existing->full_name) === '' && is_string($documentSigner->name) && trim($documentSigner->name) !== '') {
                $existing->forceFill(['full_name' => trim($documentSigner->name)])->save();
            }

            return $existing->fresh();
        }

        $created = $request->signers()->create([
            'full_name' => trim((string) $documentSigner->name) !== '' ? trim((string) $documentSigner->name) : __('Signer'),
            'email' => trim((string) $documentSigner->email),
            'role' => 'signer',
        ]);

        $request->load('signers');

        return $created;
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

    private function sendInvitation(
        NotaryRequest $request,
        NotarySigner $signer,
        NotarySession $session,
        bool $deliverSynchronously = false,
    ): void {
        $mailable = new NotarySignerVideoInvitationMail(
            $request,
            $signer,
            $session,
            $this->signerVideoJoinUrl($session),
        );

        if ($deliverSynchronously) {
            Mail::to($signer->email)->sendNow($mailable);
        } else {
            Mail::to($signer->email)->queue($mailable);
        }

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

    private function markSessionScheduledIfNeeded(NotaryRequest $request): void
    {
        $request->loadMissing(['signers', 'sessions', 'documents.documentSigners']);

        $signedParties = $this->signedPartiesForVideo($request);

        if ($signedParties === []) {
            return;
        }

        $allPartiesHaveSessions = collect($signedParties)
            ->every(fn (NotarySigner $signer): bool => $this->sessionForSigner($request, $signer) !== null);

        if (! $allPartiesHaveSessions) {
            return;
        }

        if (! in_array($request->status, [
            NotaryRequestStatus::Submitted,
            NotaryRequestStatus::IdentityReviewRequired,
            NotaryRequestStatus::IdentityVerified,
            NotaryRequestStatus::LocationReviewRequired,
            NotaryRequestStatus::LocationVerified,
        ], true)) {
            return;
        }

        $request->forceFill([
            'status' => NotaryRequestStatus::SessionScheduled,
        ])->save();
    }

    /**
     * @return Collection<int, DocumentSigner>
     */
    private function clientDocumentSigners(NotaryRequest $request): Collection
    {
        return $request->documents
            ->flatMap(fn (Document $document) => $document->documentSigners)
            ->filter(function (DocumentSigner $signer) use ($request): bool {
                if (! $signer->requiresAction()) {
                    return false;
                }

                return (int) $signer->user_id !== (int) $request->notary_user_id;
            });
    }

    /**
     * @return array{
     *     total: int,
     *     verified_count: int,
     *     pending_count: int,
     *     complete: bool,
     *     next_party: ?array<string, mixed>,
     *     parties: list<array<string, mixed>>
     * }
     */
    public function videoVerificationQueue(NotaryRequest $request): array
    {
        $parties = collect($this->partiesForVideoVerification($request))
            ->filter(fn (array $party): bool => (bool) ($party['has_signed'] ?? false))
            ->values();

        $verifiedCount = $parties
            ->filter(fn (array $party): bool => ($party['session_status'] ?? '') === 'completed')
            ->count();

        $total = $parties->count();

        $nextParty = $parties
            ->filter(fn (array $party): bool => ($party['session_status'] ?? '') !== 'completed')
            ->sortBy(fn (array $party): int => match ($party['session_status'] ?? '') {
                'in_progress' => 0,
                'scheduled' => 1,
                default => 2,
            })
            ->first();

        return [
            'total' => $total,
            'verified_count' => $verifiedCount,
            'pending_count' => max(0, $total - $verifiedCount),
            'complete' => $total > 0 && $verifiedCount === $total,
            'next_party' => is_array($nextParty) ? $nextParty : null,
            'parties' => $parties->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function viewerVideoParty(NotaryRequest $request, User $user): ?array
    {
        $request->loadMissing('signers');
        $userEmail = strtolower(trim($user->email));

        foreach ($this->partiesForVideoVerification($request) as $party) {
            if (strtolower(trim((string) ($party['email'] ?? ''))) === $userEmail) {
                return $party;
            }

            $signerId = $party['notary_signer_id'] ?? null;

            if ($signerId !== null && $request->signers->contains(
                fn (NotarySigner $signer): bool => (int) $signer->id === (int) $signerId
                    && (int) $signer->user_id === (int) $user->id
            )) {
                return $party;
            }
        }

        return null;
    }

    public function sessionStatusLabel(?string $status): string
    {
        return match ($status) {
            'completed' => __('Verified'),
            'in_progress' => __('In progress'),
            'scheduled' => __('Link sent'),
            'cancelled' => __('Cancelled'),
            default => $status !== null && $status !== ''
                ? str($status)->replace('_', ' ')->title()->toString()
                : __('Pending'),
        };
    }
}
