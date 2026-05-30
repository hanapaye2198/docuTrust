@php
    use App\Enums\NotaryRequestStatus;
    use App\Services\NotarySignerVideoInvitationService;

    $videoService = app(NotarySignerVideoInvitationService::class);
    $party = is_array($viewerVideoParty ?? null) ? $viewerVideoParty : null;
    $queueComplete = (bool) ($videoVerificationQueue['complete'] ?? false);
    $allSigned = (bool) ($signingProgress['all_client_signatures_complete'] ?? false);
@endphp

@if ($party)
    @php
        $sessionStatus = $party['session_status'] ?? '';
        $sessionId = $party['session_id'] ?? null;
        $canJoin = ($party['has_signed'] ?? false)
            && $sessionId
            && in_array($sessionStatus, ['scheduled', 'in_progress'], true);
        $statusLabel = $videoService->sessionStatusLabel($sessionStatus);
        $statusColor = match ($sessionStatus) {
            'completed' => 'emerald',
            'in_progress' => 'sky',
            'scheduled' => 'amber',
            default => 'zinc',
        };
    @endphp

    <div class="mt-4 space-y-4">
        <flux:callout variant="info" icon="information-circle">
            <flux:callout.heading>{{ __('Your video verification') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Join a private video call with your attorney to confirm your identity. Keep your government ID ready.') }}
            </flux:callout.text>
        </flux:callout>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900/50">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $party['full_name'] }}</span>
                <flux:badge size="sm" :color="$statusColor">{{ $statusLabel }}</flux:badge>
            </div>

            @if ($sessionStatus === 'completed')
                <p class="mt-3 text-sm text-emerald-700 dark:text-emerald-300">
                    {{ __('Your identity verification is complete. Your attorney will continue notarization.') }}
                </p>
            @elseif ($canJoin)
                <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">
                    @if ($sessionStatus === 'in_progress')
                        {{ __('Your attorney is ready. Join the call now and stay on camera until verification is finished.') }}
                    @else
                        {{ __('Your personal video room is ready. Join when your attorney starts the call.') }}
                    @endif
                </p>
                <div class="mt-4 flex flex-wrap gap-2">
                    @include('livewire.notary-requests.show.partials.video-join-link', [
                        'notaryRequest' => $notaryRequest,
                        'sessionId' => $sessionId,
                        'label' => __('Join your video call'),
                        'size' => 'md',
                    ])
                </div>
            @elseif (! ($party['has_signed'] ?? false))
                <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('Complete your document signature first. Your video link will appear here afterward.') }}
                </p>
            @elseif ($allSigned)
                <p class="mt-3 text-sm text-amber-800 dark:text-amber-200">
                    {{ __('Your attorney is preparing your video link. Check back shortly or watch for an email invitation.') }}
                </p>
            @else
                <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('Video verification will be available after all parties finish signing.') }}
                </p>
            @endif
        </div>
    </div>
@elseif ($queueComplete || $notaryRequest->status === NotaryRequestStatus::SessionCompleted)
    <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-100">
        {{ __('Video verification for this case is complete.') }}
    </div>
@elseif ($allSigned)
    <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300">
        {{ __('The signing parties are completing video verification with the attorney.') }}
    </div>
@else
    <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300">
        {{ __('Video verification begins after all parties finish signing the document.') }}
    </div>
@endif
