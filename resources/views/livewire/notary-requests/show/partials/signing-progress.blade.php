@php
    $progress = $signingProgress ?? null;
@endphp

@if (is_array($progress) && ($progress['visible'] ?? false))
    <div class="rounded-2xl border border-sky-200/80 bg-gradient-to-br from-sky-50/90 via-white to-white p-4 shadow-sm dark:border-sky-900/40 dark:from-sky-950/30 dark:via-zinc-900 dark:to-zinc-900 sm:p-5">
        @error('resendEmail')
            <div class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-300">
                {{ $message }}
            </div>
        @enderror
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 flex-1 space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <flux:heading size="sm" class="!mb-0">{{ __('Signing progress') }}</flux:heading>
                    @if ($progress['is_sequential'] ?? false)
                        <flux:badge size="sm" color="zinc">{{ __('Sequential') }}</flux:badge>
                    @else
                        <flux:badge size="sm" color="zinc">{{ __('Parallel') }}</flux:badge>
                    @endif
                </div>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $progress['summary'] }}</p>
                <div class="flex items-center gap-3">
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-sky-100 dark:bg-sky-950/50">
                        <div
                            class="h-full rounded-full bg-gradient-to-r from-sky-500 to-teal-500 transition-all duration-500"
                            style="width: {{ max(0, min(100, (int) ($progress['percent'] ?? 0))) }}%"
                            role="progressbar"
                            aria-valuenow="{{ (int) ($progress['percent'] ?? 0) }}"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            aria-label="{{ __('Signing progress') }}"
                        ></div>
                    </div>
                    <span class="shrink-0 text-xs font-semibold tabular-nums text-sky-800 dark:text-sky-300">
                        {{ (int) ($progress['completed'] ?? 0) }}/{{ (int) ($progress['total'] ?? 0) }}
                    </span>
                </div>
            </div>

            @php
                $casePhase = $progress['phase'] ?? '';
            @endphp
            @if ($casePhase === 'awaiting_video')
                <flux:badge color="indigo" class="shrink-0">{{ __('Video verification') }}</flux:badge>
            @elseif ($casePhase === 'awaiting_attorney_signature')
                <flux:badge color="violet" class="shrink-0">{{ __('Attorney signature') }}</flux:badge>
            @elseif ($casePhase === 'finalizing')
                <flux:badge color="amber" class="shrink-0">{{ __('Generating artifacts') }}</flux:badge>
            @elseif ($casePhase === 'document_ready')
                <flux:badge color="emerald" class="shrink-0">{{ __('Instrument ready') }}</flux:badge>
            @elseif (($progress['current_turn_name'] ?? null) !== null)
                <flux:badge color="sky" class="shrink-0">{{ __('Current: :name', ['name' => $progress['current_turn_name']]) }}</flux:badge>
            @endif
        </div>

        @foreach ($progress['documents'] ?? [] as $documentProgress)
            <div class="mt-4 border-t border-sky-100 pt-4 dark:border-sky-900/40">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <div class="min-w-0">
                        <div class="truncate text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $documentProgress['title'] }}</div>
                        <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ (int) $documentProgress['completed'] }}/{{ (int) $documentProgress['total'] }} {{ __('parties') }}
                            @if ($documentProgress['is_sequential'] ?? false)
                                · {{ __('Sequential') }}
                            @endif
                        </div>
                    </div>
                    <span class="text-xs font-medium tabular-nums text-sky-700 dark:text-sky-300">{{ (int) $documentProgress['percent'] }}%</span>
                </div>

                <ul class="space-y-2">
                    @foreach ($documentProgress['signers'] as $signerProgress)
                        <li class="rounded-xl border border-zinc-200/80 bg-white px-3 py-2.5 dark:border-zinc-700/80 dark:bg-zinc-950/40">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex min-w-0 items-start gap-3">
                                    <span @class([
                                        'mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold',
                                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' => $signerProgress['is_completed'],
                                        'bg-sky-100 text-sky-700 ring-2 ring-sky-300 dark:bg-sky-950/50 dark:text-sky-300 dark:ring-sky-700' => $signerProgress['can_act_now'],
                                        'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400' => ! $signerProgress['is_completed'] && ! $signerProgress['can_act_now'],
                                    ])>
                                        @if ($signerProgress['is_completed'])
                                            <flux:icon.check variant="mini" class="size-3.5" />
                                        @elseif ($signerProgress['can_act_now'])
                                            <span class="relative flex size-2">
                                                <span class="absolute inline-flex size-full animate-ping rounded-full bg-sky-400 opacity-75"></span>
                                                <span class="relative inline-flex size-2 rounded-full bg-sky-500"></span>
                                            </span>
                                        @else
                                            {{ $signerProgress['signing_order'] ?? '·' }}
                                        @endif
                                    </span>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $signerProgress['name'] }}</span>
                                            <flux:badge size="sm" color="zinc">{{ $signerProgress['role_label'] }}</flux:badge>
                                            <flux:badge
                                                size="sm"
                                                :color="$signerProgress['is_completed'] ? 'emerald' : ($signerProgress['can_act_now'] ? 'sky' : 'amber')"
                                            >
                                                {{ $signerProgress['status_label'] }}
                                            </flux:badge>
                                        </div>
                                        <div class="mt-0.5 truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $signerProgress['email'] }}</div>
                                        @if ($signerProgress['completed_at'])
                                            <div class="mt-0.5 text-[11px] text-emerald-700 dark:text-emerald-300">
                                                {{ __('Completed :date', ['date' => $signerProgress['completed_at']]) }}
                                            </div>
                                        @elseif (is_string($signerProgress['waiting_label'] ?? null) && $signerProgress['waiting_label'] !== '')
                                            <div class="mt-0.5 text-[11px] text-amber-700 dark:text-amber-300">
                                                {{ $signerProgress['waiting_label'] }}
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                @if ($isNotary && ($signerProgress['can_resend'] || $signerProgress['can_remind']))
                                    <div class="flex shrink-0 flex-wrap gap-1.5 sm:justify-end">
                                        @if ($signerProgress['can_resend'])
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                type="button"
                                                wire:click="resendSignerEmail({{ $documentProgress['document_id'] }}, {{ $signerProgress['signer_id'] }})"
                                                wire:confirm="{{ __('Resend signing email to :name?', ['name' => $signerProgress['name']]) }}"
                                            >
                                                {{ __('Resend') }}
                                            </flux:button>
                                        @endif
                                        @if ($signerProgress['can_remind'])
                                            <flux:button
                                                size="sm"
                                                variant="outline"
                                                type="button"
                                                wire:click="sendSignerReminder({{ $documentProgress['document_id'] }}, {{ $signerProgress['signer_id'] }})"
                                            >
                                                {{ __('Reminder') }}
                                            </flux:button>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
@endif
