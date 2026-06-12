@php
    use App\Services\NotarySignerVideoInvitationService;

    $videoService = app(NotarySignerVideoInvitationService::class);
    $sessionStatus = $party['session_status'] ?? '';
    $sessionId = $party['session_id'] ?? null;
    $isVerified = $sessionStatus === 'completed';
    $isCurrent = ! $isVerified && is_array($videoVerificationQueue['next_party'] ?? null)
        && (int) ($videoVerificationQueue['next_party']['session_id'] ?? 0) === (int) $sessionId;
    $statusLabel = $videoService->sessionStatusLabel(
        $sessionStatus,
        (bool) ($party['signer_waiting'] ?? false),
    );
    $statusColor = match ($sessionStatus) {
        'completed' => 'emerald',
        'in_progress' => 'sky',
        'scheduled' => 'amber',
        default => 'zinc',
    };
@endphp

<div
    @class([
        'rounded-xl border bg-white p-4 dark:bg-zinc-900/50',
        'border-sky-300 ring-1 ring-sky-200 dark:border-sky-800 dark:ring-sky-900/40' => $isCurrent,
        'border-zinc-200 dark:border-zinc-700' => ! $isCurrent,
    ])
    wire:key="video-party-{{ $party['notary_signer_id'] ?? $party['email'] }}"
>
    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $party['full_name'] }}</span>
                <flux:badge size="sm" :color="$statusColor">{{ $statusLabel }}</flux:badge>
                @if ($isCurrent)
                    <flux:badge size="sm" color="sky">{{ __('Next') }}</flux:badge>
                @endif
            </div>
            <p class="mt-0.5 truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $party['email'] }}</p>
            @if ($party['signed_at'])
                <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-300">
                    {{ __('Signed :date', ['date' => $party['signed_at']]) }}
                </p>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @include('livewire.notary-requests.show.partials.video-party-actions', [
                'party' => $party,
                'notaryRequest' => $notaryRequest,
                'isNotary' => $isNotary,
            ])
        </div>
    </div>

    @if ($party['has_signed'] && ! $isVerified)
        @if (is_string($party['join_url'] ?? null) && $party['join_url'] !== '')
            <details class="mt-3 group">
                <summary class="cursor-pointer text-xs font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-200">
                    {{ __('Show personal video link') }}
                </summary>
                <div class="mt-2 space-y-2">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <input
                            type="text"
                            readonly
                            value="{{ $party['join_url'] }}"
                            class="w-full min-w-0 flex-1 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 font-mono text-xs text-zinc-700 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-200"
                            onclick="this.select()"
                        />
                        <a
                            href="{{ $party['join_url'] }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex shrink-0 items-center justify-center rounded-lg border border-zinc-200 bg-white px-3 py-2 text-xs font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200"
                        >
                            {{ __('Open link') }}
                        </a>
                    </div>
                    <p class="text-[11px] text-zinc-500 dark:text-zinc-400">
                        {{ __('This link is only for this party. Do not share it with other signers.') }}
                        @if ($party['invitation_sent_label'])
                            {{ __('Email sent :time.', ['time' => $party['invitation_sent_label']]) }}
                        @endif
                    </p>
                </div>
            </details>
        @elseif ($isNotary && ($signingProgress['all_client_signatures_complete'] ?? false))
            <p class="mt-3 text-xs text-amber-700 dark:text-amber-300">
                {{ __('Video link pending. Use “Send video links to all signers” or resend by email.') }}
            </p>
        @endif

        @if ($isNotary && $sessionStatus === 'in_progress' && $sessionId)
            @include('livewire.notary-requests.show.partials.video-session-checklist', [
                'sessionId' => $sessionId,
                'class' => 'mt-4',
            ])
        @endif
    @elseif ($isVerified)
        <p class="mt-2 text-xs text-emerald-600 dark:text-emerald-400">{{ __('Video verification completed') }}</p>
    @endif
</div>
