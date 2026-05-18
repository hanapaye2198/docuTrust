<?php

namespace App\Services;

use App\Enums\NotaryRequestStatus;
use App\Events\NotarySessionScheduled;
use App\Models\NotaryJournal;
use App\Models\NotaryRequest;
use App\Models\NotarySession;
use App\Models\User;
use RuntimeException;

class NotarySchedulingService
{
    public function __construct(
        private readonly NotaryJitsiRoomService $jitsiRoomService,
    ) {}

    public function schedule(
        NotaryRequest $request,
        \DateTimeInterface $scheduledFor,
        string $providerName,
        ?string $meetingUrl = null,
        ?string $roomName = null,
        ?User $attorney = null,
    ): NotarySession {
        if (! in_array($request->status, [
            NotaryRequestStatus::Draft,
            NotaryRequestStatus::Submitted,
            NotaryRequestStatus::IdentityVerified,
            NotaryRequestStatus::LocationVerified,
        ], true)) {
            throw new RuntimeException(__('This notary request cannot be scheduled right now.'));
        }

        $normalizedProvider = strtolower(trim($providerName));
        $resolvedRoom = $roomName;
        $resolvedUrl = $meetingUrl;

        if ($normalizedProvider === 'jitsi' && ($resolvedUrl === null || $resolvedUrl === '')) {
            $resolvedRoom ??= $this->jitsiRoomService->buildRoomName($request);
            $resolvedUrl = $this->jitsiRoomService->meetingUrl($resolvedRoom);
        }

        $session = $request->sessions()->create([
            'notary_user_id' => $attorney?->id ?? $request->notary_user_id,
            'provider_name' => $providerName,
            'status' => 'scheduled',
            'room_name' => $resolvedRoom,
            'meeting_url' => $resolvedUrl,
            'scheduled_for' => $scheduledFor,
        ]);

        $request->forceFill([
            'status' => NotaryRequestStatus::SessionScheduled,
        ])->save();

        NotaryJournal::query()->create([
            'notary_request_id' => $request->id,
            'notary_user_id' => $request->notary_user_id,
            'entry_type' => 'session_scheduled',
            'summary' => __('Video session scheduled for :date via :provider.', [
                'date' => $scheduledFor->format('M j, Y g:i A'),
                'provider' => $providerName,
            ]),
            'legal_assertions' => [
                'scheduled_for' => $scheduledFor->format('Y-m-d H:i:s'),
                'provider_name' => $providerName,
                'meeting_url' => $meetingUrl,
            ],
            'recorded_at' => now(),
        ]);

        event(new NotarySessionScheduled($request, $session));

        return $session;
    }

    public function confirmSession(NotarySession $session): NotarySession
    {
        $session->forceFill([
            'signer_confirmed' => true,
            'signer_confirmed_at' => now(),
        ])->save();

        NotaryJournal::query()->create([
            'notary_request_id' => $session->notary_request_id,
            'notary_user_id' => null,
            'entry_type' => 'session_confirmed',
            'summary' => __('Signer confirmed attendance for the scheduled session.'),
            'legal_assertions' => [
                'confirmed_at' => now()->toDateTimeString(),
            ],
            'recorded_at' => now(),
        ]);

        return $session->fresh();
    }

    public function start(NotarySession $session): NotarySession
    {
        $session->forceFill([
            'status' => 'in_progress',
            'started_at' => now(),
        ])->save();

        $session->notaryRequest()->update([
            'status' => NotaryRequestStatus::SessionInProgress,
        ]);

        NotaryJournal::query()->create([
            'notary_request_id' => $session->notary_request_id,
            'notary_user_id' => $session->notaryRequest?->notary_user_id,
            'entry_type' => 'session_started',
            'summary' => __('Live video verification session started.'),
            'legal_assertions' => [
                'started_at' => now()->toDateTimeString(),
                'provider_name' => $session->provider_name,
            ],
            'recorded_at' => now(),
        ]);

        return $session->fresh();
    }

    /**
     * Complete the session with verification checklist and evidence.
     *
     * @param  array{
     *   face_matches_id?: bool,
     *   id_valid_not_expired?: bool,
     *   signer_conscious_aware?: bool,
     *   signer_agrees_voluntarily?: bool,
     *   signer_in_philippines?: bool,
     *   id_shown_on_camera?: bool,
     *   session_recorded?: bool,
     * }  $verificationChecklist
     * @param  array<string, mixed>  $evidence
     */
    public function complete(NotarySession $session, array $verificationChecklist = [], array $evidence = []): NotarySession
    {
        $session->forceFill([
            'status' => 'completed',
            'ended_at' => now(),
            'evidence' => $evidence,
            'verification_checklist' => $verificationChecklist,
        ])->save();

        NotaryJournal::query()->create([
            'notary_request_id' => $session->notary_request_id,
            'notary_user_id' => $session->notaryRequest?->notary_user_id,
            'entry_type' => 'session_completed',
            'summary' => __('Live video verification session completed.'),
            'legal_assertions' => [
                'ended_at' => now()->toDateTimeString(),
                'verification_checklist' => $verificationChecklist,
                'all_checks_passed' => $this->allChecksPassed($verificationChecklist),
            ],
            'recorded_at' => now(),
        ]);

        return $session->fresh();
    }

    /**
     * @param  array<string, bool>  $checklist
     */
    private function allChecksPassed(array $checklist): bool
    {
        $requiredChecks = [
            'face_matches_id',
            'id_valid_not_expired',
            'signer_conscious_aware',
            'signer_agrees_voluntarily',
            'signer_in_philippines',
            'id_shown_on_camera',
        ];

        foreach ($requiredChecks as $check) {
            if (! ($checklist[$check] ?? false)) {
                return false;
            }
        }

        return true;
    }
}
