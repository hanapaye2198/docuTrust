@php
    $allPartiesSigned = (bool) ($signingProgress['all_client_signatures_complete'] ?? false);
    $signedParties = collect($partiesForVideo ?? [])->filter(fn (array $party): bool => $party['has_signed'] ?? false);
    $videoVerificationComplete = (bool) ($signingProgress['video_verification_complete'] ?? false);
@endphp

            <div class="ui-panel p-6 sm:p-8">
                <h2 class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-50">{{ __('Video verification') }}</h2>
                <p class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
                    @if ($usesPerSignerVideo ?? false)
                        {{ __('After everyone signs, each party gets their own private video room. Complete a separate verification call with every signed party before you sign as attorney.') }}
                    @else
                        {{ __('Schedule a live session after all parties have signed.') }}
                    @endif
                </p>

                @if ($isNotary && ($videoWaitingParties ?? []) !== [])
                    <div
                        data-video-waiting-banner
                        class="mb-4 rounded-xl border-2 border-sky-400 bg-sky-100 px-4 py-3 text-sm text-sky-950 dark:border-sky-600 dark:bg-sky-950/50 dark:text-sky-50"
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
                    <div class="mb-4 flex flex-wrap items-center gap-2">
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
                    <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/25 dark:text-emerald-100">
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
                                {{ __('Go to Settlement') }}
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
