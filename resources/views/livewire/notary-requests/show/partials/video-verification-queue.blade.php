@php
    use App\Services\NotarySignerVideoInvitationService;

    $videoService = app(NotarySignerVideoInvitationService::class);
    $total = (int) ($videoVerificationQueue['total'] ?? 0);
    $verifiedCount = (int) ($videoVerificationQueue['verified_count'] ?? 0);
    $pendingCount = (int) ($videoVerificationQueue['pending_count'] ?? 0);
    $nextParty = is_array($videoVerificationQueue['next_party'] ?? null) ? $videoVerificationQueue['next_party'] : null;
    $progressPercent = $total > 0 ? (int) round(($verifiedCount / $total) * 100) : 0;
@endphp

@if ($total > 0)
    <div class="mb-6 rounded-2xl border border-indigo-200/80 bg-indigo-50/60 p-5 dark:border-indigo-900/40 dark:bg-indigo-950/20">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wider text-indigo-700 dark:text-indigo-300">{{ __('Verification queue') }}</div>
                <div class="mt-1 text-lg font-semibold text-indigo-950 dark:text-indigo-100">
                    {{ trans_choice(':verified of :total party verified|:verified of :total parties verified', $total, [
                        'verified' => $verifiedCount,
                        'total' => $total,
                    ]) }}
                </div>
            </div>
            @if ($videoVerificationQueue['complete'] ?? false)
                <flux:badge color="emerald">{{ __('All verified') }}</flux:badge>
            @elseif ($pendingCount > 0)
                <flux:badge color="sky">{{ trans_choice(':count remaining|:count remaining', $pendingCount, ['count' => $pendingCount]) }}</flux:badge>
            @endif
        </div>

        <div class="mt-4 h-2 overflow-hidden rounded-full bg-indigo-100 dark:bg-indigo-950/50">
            <div class="h-full rounded-full bg-indigo-500 transition-all duration-300 dark:bg-indigo-400" style="width: {{ $progressPercent }}%"></div>
        </div>

        @if ($nextParty && ! ($videoVerificationQueue['complete'] ?? false))
            <div class="mt-4 flex flex-col gap-3 rounded-xl border border-indigo-200 bg-white/80 p-4 dark:border-indigo-900/40 dark:bg-zinc-900/60 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-300">{{ __('Next up') }}</div>
                    <div class="mt-1 text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $nextParty['full_name'] ?? __('Signer') }}</div>
                    <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ $videoService->sessionStatusLabel($nextParty['session_status'] ?? null) }}
                    </div>
                </div>
                @if (($nextParty['session_id'] ?? null) && in_array($nextParty['session_status'] ?? '', ['scheduled', 'in_progress'], true))
                    @include('livewire.notary-requests.show.partials.video-join-link', [
                        'notaryRequest' => $notaryRequest,
                        'sessionId' => $nextParty['session_id'],
                        'label' => __('Join call'),
                        'size' => 'md',
                    ])
                @endif
            </div>
        @endif
    </div>
@endif
