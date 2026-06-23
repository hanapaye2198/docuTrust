@php
    use App\Services\NotarySignerVideoInvitationService;

    $videoService = app(NotarySignerVideoInvitationService::class);
    $sessionStatus = $party['session_status'] ?? '';
    $sessionId = $party['session_id'] ?? null;
    $isVerified = $sessionStatus === 'completed';
    $isWaiting = (bool) ($party['signer_waiting'] ?? false);
    $isCurrent = ! $isVerified && is_array($videoVerificationQueue['next_party'] ?? null)
        && (int) ($videoVerificationQueue['next_party']['session_id'] ?? 0) === (int) $sessionId;
    $statusLabel = $videoService->sessionStatusLabel(
        $sessionStatus,
        $isWaiting,
    );
    $statusColor = match (true) {
        $isWaiting => 'emerald',
        $sessionStatus === 'completed' => 'emerald',
        $sessionStatus === 'in_progress' => 'sky',
        $sessionStatus === 'scheduled' => 'amber',
        default => 'zinc',
    };
    $initial = str($party['full_name'] ?? '?')->trim()->substr(0, 1)->upper()->toString();
    $joinedAtLabel = $party['joined_at_label'] ?? null;
@endphp

<div
    @class([
        'rounded-2xl border px-4 py-3 transition-all',
        'border-emerald-300 bg-emerald-50 ring-2 ring-emerald-100 dark:border-emerald-800 dark:bg-emerald-950/25 dark:ring-emerald-950/60' => $isWaiting,
        'border-indigo-300 bg-zinc-50/60 ring-2 ring-indigo-100 dark:border-indigo-800 dark:bg-zinc-950/50 dark:ring-indigo-950/60' => $isCurrent && ! $isWaiting,
        'border-zinc-200/80 bg-zinc-50/60 dark:border-zinc-800 dark:bg-zinc-950/50' => ! $isCurrent && ! $isWaiting,
    ])
    wire:key="video-party-{{ $party['notary_signer_id'] ?? $party['email'] }}"
>
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex min-w-0 items-start gap-3">
            <div class="relative mt-0.5 shrink-0">
                <span @class([
                    'flex size-9 items-center justify-center rounded-full text-xs font-bold',
                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' => $isWaiting || $isVerified,
                    'bg-indigo-100 text-indigo-700 ring-2 ring-indigo-300 dark:bg-indigo-950/50 dark:text-indigo-300 dark:ring-indigo-700' => $isCurrent && ! $isWaiting,
                    'bg-zinc-200 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400' => ! $isVerified && ! $isCurrent && ! $isWaiting,
                ])>
                    @if ($isVerified)
                        <flux:icon.check variant="mini" class="size-4" />
                    @elseif ($isCurrent && ! $isWaiting)
                        <span class="relative flex size-2.5">
                            <span class="absolute inline-flex size-full animate-ping rounded-full bg-indigo-400 opacity-75"></span>
                            <span class="relative inline-flex size-2.5 rounded-full bg-indigo-500"></span>
                        </span>
                    @else
                        {{ $initial !== '' ? $initial : __('•') }}
                    @endif
                </span>
                @if ($isWaiting)
                    <span class="absolute -right-0.5 -top-0.5 flex size-3">
                        <span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex size-3 rounded-full bg-emerald-500"></span>
                    </span>
                @endif
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $party['full_name'] }}</span>
                    <flux:badge size="sm" :color="$statusColor">{{ $statusLabel }}</flux:badge>
                    @if ($isCurrent)
                        <flux:badge size="sm" color="indigo">{{ __('Next') }}</flux:badge>
                    @endif
                    @if ($isWaiting)
                        <span class="inline-flex items-center gap-1 rounded-full border border-emerald-300 bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:border-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                            <span class="size-1.5 animate-pulse rounded-full bg-emerald-500"></span>
                            {{ __('Waiting in room') }}
                        </span>
                    @elseif ($isVerified)
                        <span class="inline-flex items-center gap-1 text-xs font-medium text-zinc-500 dark:text-zinc-400">
                            {{ __('Verified') }}
                        </span>
                    @endif
                </div>
                <p class="mt-0.5 truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $party['email'] }}</p>
                @if ($party['signed_at'])
                    <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-300">
                        {{ __('Signed :date', ['date' => $party['signed_at']]) }}
                    </p>
                @endif
                @if ($isWaiting && is_string($joinedAtLabel) && $joinedAtLabel !== '')
                    <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-300">
                        {{ __('Joined :time', ['time' => $joinedAtLabel]) }}
                    </p>
                @endif
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @include('livewire.notary-requests.show.partials.video-party-actions', [
                'party' => $party,
                'notaryRequest' => $notaryRequest,
                'isNotary' => $isNotary,
                'isWaiting' => $isWaiting,
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
