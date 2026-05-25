            <div class="ui-panel p-6 sm:p-8">
                <flux:heading size="lg" class="!mb-1">{{ __('Video verification') }}</flux:heading>
                <p class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
                    @if ($usesPerSignerVideo ?? false)
                        {{ __('After everyone signs, each party gets their own video link for identity verification with you.') }}
                    @else
                        {{ __('Schedule a live session after all parties have signed.') }}
                    @endif
                </p>

                @if (($usesPerSignerVideo ?? false) && $isNotary)
                    <div class="mb-4 flex flex-wrap items-center gap-2">
                        <flux:button variant="outline" type="button" wire:click="sendSignerVideoInvitations">
                            {{ __('Resend video links') }}
                        </flux:button>
                        <flux:error name="sendSignerVideoInvitations" />
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
                @elseif ($isNotary && ! ($signingProgress['all_client_signatures_complete'] ?? false))
                    <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300">
                        {{ __('Video links are sent automatically after all parties finish signing.') }}
                    </div>
                @endif
                @if ($recentSessions->isNotEmpty())
                    <div class="mt-5 space-y-3 border-t border-zinc-100 pt-5 dark:border-zinc-800">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Sessions') }}</h3>
                        @foreach ($recentSessions as $session)
                            <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800/40">
                                {{-- Session header --}}
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex min-w-0 items-center gap-2.5">
                                        @if ($session->status === 'in_progress')
                                            <span class="flex h-2 w-2 rounded-full bg-red-500 animate-pulse"></span>
                                        @elseif ($session->status === 'completed')
                                            <span class="flex h-2 w-2 rounded-full bg-emerald-500"></span>
                                        @else
                                            <span class="flex h-2 w-2 rounded-full bg-zinc-300 dark:bg-zinc-600"></span>
                                        @endif
                                        <div class="min-w-0">
                                            <span class="block truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">
                                                @if ($session->notarySigner)
                                                    {{ $session->notarySigner->full_name }}
                                                @else
                                                    {{ ucfirst($session->provider_name) }}
                                                @endif
                                            </span>
                                            <span class="mt-0.5 block text-xs text-zinc-400 dark:text-zinc-500 sm:mt-0">
                                                {{ $session->scheduled_for?->format('M j, g:i A') ?? '-' }}
                                                @if ($session->invitation_sent_at)
                                                    · {{ __('invite sent') }}
                                                @endif
                                            </span>
                                        </div>
                                    </div>
                                    <span class="inline-flex w-fit rounded-md border border-zinc-200 bg-zinc-50 px-2 py-0.5 text-[11px] font-medium text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">{{ $session->status }}</span>
                                </div>

                                {{-- Scheduled: Start button (notary only) --}}
                                @if ($session->status === 'scheduled' && $isNotary)
                                    <div class="mt-3">
                                        <flux:button variant="primary" size="sm" type="button" wire:click="startSession({{ $session->id }})">{{ __('Start session') }}</flux:button>
                                        <flux:error name="startSession" />
                                    </div>
                                @endif

                                {{-- In progress: Video room link (everyone) --}}
                                @if ($session->status === 'in_progress')
                                    @if (is_string($session->meeting_url) && $session->meeting_url !== '')
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <a href="{{ auth()->user()?->role->value === 'notary' ? route('notary.requests.session.live', [$notaryRequest, $session]) : route('notary-requests.session.live', [$notaryRequest, $session]) }}"
                                               target="_blank"
                                               class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold text-white shadow-sm" style="background-color: #18181b;">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
                                                {{ __('Join video room') }}
                                            </a>
                                            <a href="{{ $session->meeting_url }}" target="_blank"
                                               class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-xs font-medium text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">
                                                {{ __('Open in new tab') }}
                                            </a>
                                        </div>
                                    @endif

                                    {{-- Attorney checklist (NOTARY ROLE ONLY) --}}
                                    @if ($isNotary)
                                        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50/50 p-4 dark:border-amber-900/40 dark:bg-amber-950/20">
                                            <div class="flex items-center gap-2">
                                                <svg class="h-4 w-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                                <span class="text-xs font-semibold text-amber-800 dark:text-amber-300">{{ __('Attorney verification checklist') }}</span>
                                            </div>
                                            <p class="mt-1 text-[11px] text-amber-700 dark:text-amber-400">{{ __('Complete all items before ending the session. Only the assigned notary can perform this step.') }}</p>
                                            <div class="mt-3 space-y-2">
                                                @foreach (config('docutrust.notary.verification_checklist', []) as $key)
                                                    <label class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 transition-colors hover:bg-amber-100/50 dark:hover:bg-amber-950/30">
                                                        <input type="checkbox" class="h-4 w-4 rounded border-amber-300 text-amber-600 focus:ring-amber-500 dark:border-amber-700" wire:model.live="sessionChecklist.{{ $key }}" />
                                                        <span class="text-xs text-zinc-700 dark:text-zinc-300">{{ __(ucfirst(str_replace('_', ' ', $key))) }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                            <flux:error name="sessionChecklist" />
                                            <div class="mt-4">
                                                <flux:button variant="primary" size="sm" type="button" wire:click="completeSession({{ $session->id }})">{{ __('Complete session') }}</flux:button>
                                                <flux:error name="completeSession" />
                                            </div>
                                        </div>
                                    @else
                                        {{-- Non-notary sees a waiting message --}}
                                        <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-xs text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800/40 dark:text-zinc-400">
                                            <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ __('Session in progress') }}</span> — {{ __('The notary is verifying your identity. Please stay on the video call.') }}
                                        </div>
                                    @endif
                                @endif

                                {{-- Completed session info --}}
                                @if ($session->status === 'completed')
                                    <div class="mt-2 text-xs text-emerald-600 dark:text-emerald-400">
                                        {{ __('Completed') }} {{ $session->ended_at?->diffForHumans() }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
