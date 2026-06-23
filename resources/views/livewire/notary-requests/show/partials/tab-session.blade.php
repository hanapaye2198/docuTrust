@php
    use App\Enums\NotaryRequestStatus;

    $allPartiesSigned = (bool) ($signingProgress['all_client_signatures_complete'] ?? false);
    $signedParties = collect($partiesForVideo ?? [])->filter(fn (array $party): bool => $party['has_signed'] ?? false);
    $videoVerificationComplete = (bool) ($signingProgress['video_verification_complete'] ?? false);
    $queueTotal = (int) ($videoVerificationQueue['total'] ?? $signedParties->count());
    $queueVerified = (int) ($videoVerificationQueue['verified_count'] ?? 0);
    $queuePending = (int) ($videoVerificationQueue['pending_count'] ?? max(0, $queueTotal - $queueVerified));
    $videoProgressPercent = $queueTotal > 0 ? (int) round(($queueVerified / $queueTotal) * 100) : 0;
    $videoPollingEnabled = $isNotary
        && in_array($notaryRequest->status, [NotaryRequestStatus::SessionScheduled, NotaryRequestStatus::SessionInProgress], true);
    $videoTrackerSteps = [
        [
            'label' => __('Client signing'),
            'description' => __('All required client signatures must be completed first.'),
            'state' => $allPartiesSigned ? 'complete' : 'current',
        ],
        [
            'label' => __('Send video links'),
            'description' => __('Create a private video room for each signed party.'),
            'state' => match (true) {
                $videoVerificationComplete => 'complete',
                ($usesPerSignerVideo ?? false) && ($partiesForVideo ?? []) !== [] => 'complete',
                $allPartiesSigned => 'current',
                default => 'upcoming',
            },
        ],
        [
            'label' => __('Video verification'),
            'description' => __('Complete identity verification for every signed party.'),
            'state' => match (true) {
                $videoVerificationComplete => 'complete',
                $allPartiesSigned => 'current',
                default => 'upcoming',
            },
        ],
        [
            'label' => __('Attorney signing'),
            'description' => __('After verification, the attorney signs the instrument.'),
            'state' => $videoVerificationComplete ? 'current' : 'upcoming',
        ],
    ];
@endphp

            <div
                class="relative overflow-hidden rounded-3xl border border-indigo-200/80 bg-white shadow-sm dark:border-indigo-900/40 dark:bg-zinc-950"
                @if ($videoPollingEnabled) wire:poll.5s="refreshVideoStatus" @endif
                x-data="{ videoJoinToast: '' }"
                @signer-joined-video-room.window="
                    videoJoinToast = ($event.detail.name || '{{ __('A signer') }}') + ' {{ __('is waiting in the video room') }}';
                    setTimeout(() => videoJoinToast = '', 5000);
                "
            >
                <div
                    x-cloak
                    x-show="videoJoinToast"
                    x-transition
                    class="fixed right-4 top-4 z-50 rounded-2xl border border-emerald-200 bg-white px-4 py-3 text-sm font-semibold text-emerald-800 shadow-lg dark:border-emerald-900/50 dark:bg-zinc-900 dark:text-emerald-300"
                >
                    <span class="mr-2 inline-flex size-2 rounded-full bg-emerald-500"></span>
                    <span x-text="videoJoinToast"></span>
                </div>
                <div class="bg-gradient-to-br from-indigo-50 via-white to-sky-50 p-5 dark:from-indigo-950/30 dark:via-zinc-950 dark:to-sky-950/20 sm:p-6 lg:p-8">
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-xs font-semibold uppercase tracking-wider text-indigo-700 dark:text-indigo-300">
                                    {{ __('Video verification') }}
                                </span>
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                                    <span class="relative flex size-2">
                                        <span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-70"></span>
                                        <span class="relative inline-flex size-2 rounded-full bg-emerald-500"></span>
                                    </span>
                                    {{ __('Live tracking') }}
                                </span>
                                @if ($videoVerificationComplete)
                                    <flux:badge size="sm" color="emerald">{{ __('Complete') }}</flux:badge>
                                @elseif ($allPartiesSigned)
                                    <flux:badge size="sm" color="indigo">{{ __('Ready') }}</flux:badge>
                                @else
                                    <flux:badge size="sm" color="zinc">{{ __('Waiting') }}</flux:badge>
                                @endif
                            </div>

                            <h2 class="mt-3 text-2xl font-bold tracking-tight text-zinc-950 dark:text-white sm:text-3xl">
                                @if ($videoVerificationComplete)
                                    {{ __('Video verification completed') }}
                                @elseif ($allPartiesSigned)
                                    {{ __('Waiting for video verification') }}
                                @else
                                    {{ __('Waiting for client signatures') }}
                                @endif
                            </h2>
                            <p class="mt-2 max-w-3xl text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                                @if ($usesPerSignerVideo ?? false)
                                    {{ __('After everyone signs, each party gets their own private video room. Complete a separate verification call with every signed party before you sign as attorney.') }}
                                @else
                                    {{ __('Schedule a live session after all parties have signed.') }}
                                @endif
                            </p>
                        </div>

                        <div class="w-full rounded-2xl border border-white/70 bg-white/85 p-4 shadow-sm backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/80 lg:max-w-xs">
                            <div class="flex items-end justify-between gap-4">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Verified') }}</div>
                                    <div class="mt-1 text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ $queueVerified }}/{{ $queueTotal }} {{ __('parties') }}
                                    </div>
                                </div>
                                <div class="text-4xl font-bold tabular-nums text-indigo-700 dark:text-indigo-300">
                                    {{ $videoProgressPercent }}%
                                </div>
                            </div>
                            <div class="mt-4 h-3 overflow-hidden rounded-full bg-indigo-100 dark:bg-indigo-950/50">
                                <div class="h-full rounded-full bg-gradient-to-r from-indigo-500 via-sky-500 to-teal-500 transition-all duration-500" style="width: {{ $videoProgressPercent }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-y border-zinc-200 bg-zinc-50/70 px-4 py-4 dark:border-zinc-800 dark:bg-zinc-900/50 sm:px-6 lg:px-8">
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        @foreach ($videoTrackerSteps as $index => $step)
                            @php
                                $stepState = (string) $step['state'];
                                $stepTone = match ($stepState) {
                                    'complete' => 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/25 dark:text-emerald-100',
                                    'current' => 'border-indigo-300 bg-indigo-50 text-indigo-950 ring-2 ring-indigo-100 dark:border-indigo-800 dark:bg-indigo-950/30 dark:text-indigo-100 dark:ring-indigo-950/60',
                                    default => 'border-zinc-200 bg-white text-zinc-600 dark:border-zinc-800 dark:bg-zinc-950/60 dark:text-zinc-400',
                                };
                                $dotTone = match ($stepState) {
                                    'complete' => 'bg-emerald-500 text-white',
                                    'current' => 'bg-indigo-500 text-white',
                                    default => 'bg-zinc-200 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400',
                                };
                            @endphp
                            <div class="rounded-2xl border p-3 {{ $stepTone }}">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="inline-flex size-7 items-center justify-center rounded-full text-xs font-bold {{ $dotTone }}">
                                        @if ($stepState === 'complete')
                                            <flux:icon.check variant="mini" class="size-4" />
                                        @else
                                            {{ $index + 1 }}
                                        @endif
                                    </span>
                                    <span class="text-[10px] font-semibold uppercase tracking-wider opacity-70">
                                        {{ $stepState === 'complete' ? __('Done') : ($stepState === 'current' ? __('Now') : __('Next')) }}
                                    </span>
                                </div>
                                <div class="mt-3 text-sm font-semibold leading-tight">{{ $step['label'] }}</div>
                                <p class="mt-1 line-clamp-2 text-xs leading-relaxed opacity-75">{{ $step['description'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="space-y-4 p-4 sm:p-6 lg:p-8">

                @if ($isNotary && ($videoWaitingParties ?? []) !== [])
                    <div
                        data-video-waiting-banner
                        class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-950 dark:border-sky-900/40 dark:bg-sky-950/25 dark:text-sky-100"
                    >
                        <div class="font-semibold">
                            {{ trans_choice(':count signer is|:count signers are', count($videoWaitingParties), ['count' => count($videoWaitingParties)]) }}
                            {{ __('waiting in the video room') }}
                        </div>
                        <p class="mt-1">
                            <span data-video-waiting-names>{{ collect($videoWaitingParties)->pluck('full_name')->join(', ') }}</span>
                        </p>
                    </div>
                @endif

                @if (($usesPerSignerVideo ?? false) && $isNotary && ! $videoVerificationComplete)
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            wire:click="openVideoSessionWorkspace"
                            wire:loading.attr="disabled"
                            wire:target="openVideoSessionWorkspace,sendSignerVideoInvitations,syncVideoPartiesIfReady"
                            class="inline-flex items-center justify-center rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-teal-600 dark:hover:bg-teal-500"
                        >
                            {{ __('Send video links to all signers') }}
                        </button>
                        <button
                            type="button"
                            wire:click="sendSignerVideoInvitations(true)"
                            wire:loading.attr="disabled"
                            wire:target="openVideoSessionWorkspace,sendSignerVideoInvitations,syncVideoPartiesIfReady"
                            class="inline-flex items-center justify-center rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-sm font-semibold text-zinc-700 shadow-sm transition hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
                        >
                            {{ __('Resend by email') }}
                        </button>
                        @error('sendSignerVideoInvitations')
                            <p class="w-full text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                @elseif (($usesPerSignerVideo ?? false) && $isNotary && $videoVerificationComplete)
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/25 dark:text-emerald-100">
                        <div class="font-semibold">{{ __('Video verification completed') }}</div>
                        <p class="mt-1 text-emerald-800/90 dark:text-emerald-200/90">
                            {{ __('All required sessions are complete. Next: sign the instrument as attorney.') }}
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button
                                type="button"
                                wire:click="setActiveTab('documents')"
                                class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 dark:bg-emerald-600 dark:hover:bg-emerald-500"
                            >
                                {{ __('Go to Documents') }}
                            </button>
                            <button
                                type="button"
                                wire:click="setActiveTab('closing')"
                                class="inline-flex items-center justify-center rounded-xl border border-emerald-200 bg-white px-4 py-2.5 text-sm font-semibold text-emerald-800 shadow-sm transition hover:bg-emerald-50 dark:border-emerald-900/50 dark:bg-zinc-900 dark:text-emerald-200 dark:hover:bg-emerald-950/20"
                            >
                                {{ __('Go to fees & register') }}
                            </button>
                        </div>
                    </div>
                @endif

                @if ($allPartiesSigned && ($usesPerSignerVideo ?? false))
                    @if (($partiesForVideo ?? []) !== [])
                        @if ($isNotary)
                            @include('livewire.notary-requests.show.partials.video-verification-queue')
                        @endif

                        <div class="space-y-3">
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">
                                {{ __('Parties') }}
                            </h3>

                            @foreach ($videoVerificationQueue['parties'] ?? $partiesForVideo as $party)
                                @if ($party['has_signed'] ?? false)
                                    @include('livewire.notary-requests.show.partials.video-party-queue-row', [
                                        'party' => $party,
                                    ])
                                @endif
                            @endforeach

                            @foreach ($partiesForVideo as $party)
                                @if (! ($party['has_signed'] ?? false))
                                    @include('livewire.notary-requests.show.partials.video-party-queue-row', [
                                        'party' => $party,
                                    ])
                                @endif
                            @endforeach
                        </div>
                    @else
                        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                            {{ __('All parties have signed. Click “Send video links to all signers” above to create a personal link for each party.') }}
                        </div>
                    @endif
                @endif

                @if ($canScheduleSession && ! ($usesPerSignerVideo ?? false))
                    <div class="mt-4 space-y-4">
                        <flux:field>
                            <flux:label>{{ __('Scheduled for') }}</flux:label>
                            <flux:input wire:model="scheduleAt" type="datetime-local" />
                            <flux:error name="scheduleAt" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('Provider') }}</flux:label>
                            <select wire:model="providerName" class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                                <option value="jitsi">{{ __('Jitsi Meet (auto-generated room)') }}</option>
                                <option value="manual">{{ __('Manual (paste URL below)') }}</option>
                            </select>
                            <flux:error name="providerName" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('Meeting URL') }}</flux:label>
                            <flux:input wire:model="meetingUrl" type="url" placeholder="https://..." />
                            <flux:error name="meetingUrl" />
                        </flux:field>
                        <flux:button variant="outline" type="button" wire:click="scheduleSession">{{ __('Schedule session') }}</flux:button>
                        <flux:error name="scheduleSession" />
                    </div>
                @elseif (! ($usesPerSignerVideo ?? false))
                    <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300">
                        @if (!$isNotary)
                            {{ __('Only the assigned notary can schedule video sessions.') }}
                        @else
                            {{ __('Video session scheduling becomes available after all signers have completed signing.') }}
                        @endif
                    </div>
                @elseif ($isNotary && ! $allPartiesSigned)
                    <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300">
                        {{ __('Video links appear here after all parties finish signing.') }}
                    </div>
                @endif

                @php
                    $legacySessions = $recentSessions->filter(fn ($session) => $session->notary_signer_id === null);
                @endphp
                @if ($legacySessions->isNotEmpty())
                    <div class="mt-5 space-y-3 border-t border-zinc-100 pt-5 dark:border-zinc-800">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Shared sessions') }}</h3>
                        @foreach ($legacySessions as $session)
                            @include('livewire.notary-requests.show.partials.tab-session-card', ['session' => $session])
                        @endforeach
                    </div>
                @endif
                </div>
            </div>
