@php
    $allPartiesSigned = (bool) ($signingProgress['all_client_signatures_complete'] ?? false);
    $signedParties = collect($partiesForVideo ?? [])->filter(fn (array $party): bool => $party['has_signed'] ?? false);
    $liveSessionRoute = auth()->user()?->role->value === 'notary'
        ? 'notary.requests.session.live'
        : 'notary-requests.session.live';
@endphp

            <div class="ui-panel p-6 sm:p-8">
                <flux:heading size="lg" class="!mb-1">{{ __('Video verification') }}</flux:heading>
                <p class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
                    @if ($usesPerSignerVideo ?? false)
                        {{ __('After everyone signs, each party gets their own private video room and link. Complete a separate verification call with every signed party before you sign as attorney.') }}
                    @else
                        {{ __('Schedule a live session after all parties have signed.') }}
                    @endif
                </p>

                @if (($usesPerSignerVideo ?? false) && $isNotary)
                    <div class="mb-4 flex flex-wrap items-center gap-2">
                        <flux:button
                            variant="outline"
                            type="button"
                            wire:click="sendSignerVideoInvitations(true)"
                            wire:loading.attr="disabled"
                            wire:target="sendSignerVideoInvitations,syncVideoPartiesIfReady"
                        >
                            {{ __('Resend video links by email') }}
                        </flux:button>
                        <flux:error name="sendSignerVideoInvitations" />
                    </div>
                @endif

                @if ($allPartiesSigned && ($usesPerSignerVideo ?? false) && ($partiesForVideo ?? []) !== [])
                    <div class="mb-6 space-y-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">
                                {{ __('Parties — individual video links') }}
                            </h3>
                            <flux:badge size="sm" color="emerald">
                                {{ trans_choice(':count party signed|:count parties signed', $signedParties->count(), ['count' => $signedParties->count()]) }}
                            </flux:badge>
                        </div>

                        <div class="space-y-3">
                            @foreach ($partiesForVideo as $party)
                                <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900/50" wire:key="video-party-{{ $party['notary_signer_id'] }}">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $party['full_name'] }}</span>
                                                @if ($party['has_signed'])
                                                    <flux:badge size="sm" color="emerald">{{ __('Signed') }}</flux:badge>
                                                @else
                                                    <flux:badge size="sm" color="amber">{{ __('Awaiting signature') }}</flux:badge>
                                                @endif
                                                @if (is_string($party['session_status'] ?? null) && $party['session_status'] !== '')
                                                    <flux:badge size="sm" color="zinc">{{ str_replace('_', ' ', $party['session_status']) }}</flux:badge>
                                                @endif
                                            </div>
                                            <p class="mt-0.5 truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $party['email'] }}</p>
                                            @if ($party['signed_at'])
                                                <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-300">
                                                    {{ __('Signed :date', ['date' => $party['signed_at']]) }}
                                                </p>
                                            @endif
                                        </div>

                                        @if ($party['has_signed'] && $isNotary && ($party['session_id'] ?? null) && ($party['session_status'] ?? '') === 'scheduled')
                                            <flux:button
                                                variant="primary"
                                                size="sm"
                                                type="button"
                                                wire:click="startSession({{ $party['session_id'] }})"
                                            >
                                                {{ __('Start session') }}
                                            </flux:button>
                                        @endif
                                    </div>

                                    @if ($party['has_signed'])
                                        @if (is_string($party['join_url'] ?? null) && $party['join_url'] !== '')
                                            <div class="mt-3 space-y-2">
                                                <label class="text-[11px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">
                                                    {{ __('Personal video link for :name', ['name' => $party['full_name']]) }}
                                                </label>
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
                                                    @else
                                                        {{ __('Copy the link above if email did not arrive.') }}
                                                    @endif
                                                </p>
                                            </div>
                                        @elseif ($isNotary && $allPartiesSigned)
                                            <p class="mt-3 text-xs text-amber-700 dark:text-amber-300">
                                                {{ __('Video link pending. Use “Resend video links by email” or refresh this page.') }}
                                            </p>
                                        @endif

                                        @if (($party['session_status'] ?? '') === 'in_progress' && ($party['session_id'] ?? null))
                                            <div class="mt-3 flex flex-wrap gap-2">
                                                <a
                                                    href="{{ route($liveSessionRoute, [$notaryRequest, $party['session_id']]) }}"
                                                    target="_blank"
                                                    class="inline-flex items-center gap-1.5 rounded-lg bg-zinc-900 px-3 py-2 text-xs font-semibold text-white shadow-sm dark:bg-zinc-100 dark:text-zinc-900"
                                                >
                                                    {{ __('Join video room') }}
                                                </a>
                                            </div>
                                            @if ($isNotary)
                                                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50/50 p-4 dark:border-amber-900/40 dark:bg-amber-950/20">
                                                    <span class="text-xs font-semibold text-amber-800 dark:text-amber-300">{{ __('Attorney verification checklist') }}</span>
                                                    <div class="mt-3 space-y-2">
                                                        @foreach (config('docutrust.notary.verification_checklist', []) as $key)
                                                            <label class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 hover:bg-amber-100/50 dark:hover:bg-amber-950/30">
                                                                <input type="checkbox" class="h-4 w-4 rounded border-amber-300 text-amber-600 focus:ring-amber-500 dark:border-amber-700" wire:model.live="sessionChecklist.{{ $key }}" />
                                                                <span class="text-xs text-zinc-700 dark:text-zinc-300">{{ __(ucfirst(str_replace('_', ' ', $key))) }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                    <flux:error name="sessionChecklist" />
                                                    <div class="mt-4">
                                                        <flux:button variant="primary" size="sm" type="button" wire:click="completeSession({{ $party['session_id'] }})">{{ __('Complete session') }}</flux:button>
                                                        <flux:error name="completeSession" />
                                                    </div>
                                                </div>
                                            @endif
                                        @endif

                                        @if (($party['session_status'] ?? '') === 'completed')
                                            <p class="mt-2 text-xs text-emerald-600 dark:text-emerald-400">{{ __('Video verification completed') }}</p>
                                        @endif
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @elseif ($isNotary && $allPartiesSigned && ($usesPerSignerVideo ?? false))
                    <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                        {{ __('All parties have signed. Add party emails on the Parties tab, then resend video links.') }}
                    </div>
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
