@php
    $progress = $signingProgress ?? null;
@endphp

@if (is_array($progress) && ($progress['visible'] ?? false))
    <div
        class="overflow-hidden rounded-3xl border border-sky-200/80 bg-white shadow-sm dark:border-sky-900/40 dark:bg-zinc-950"
        wire:poll.5s="refreshSigningStatus"
        wire:key="notary-signing-progress-{{ $notaryRequest->id }}"
        data-live-signing-progress
    >
        @error('resendEmail')
            <div class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-300">
                {{ $message }}
            </div>
        @enderror

        <div class="bg-gradient-to-br from-sky-50 via-white to-teal-50 p-5 dark:from-sky-950/30 dark:via-zinc-950 dark:to-teal-950/20 sm:p-6 lg:p-8">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-semibold uppercase tracking-wider text-sky-700 dark:text-sky-300">
                            {{ __('Signing progress') }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                            <span class="relative flex size-2">
                                <span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-70"></span>
                                <span class="relative inline-flex size-2 rounded-full bg-emerald-500"></span>
                            </span>
                            {{ __('Live polling') }}
                        </span>
                        @if ($progress['is_sequential'] ?? false)
                            <flux:badge size="sm" color="zinc">{{ __('Sequential') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc">{{ __('Parallel') }}</flux:badge>
                        @endif
                    </div>

                    <h3 class="mt-3 text-2xl font-bold tracking-tight text-zinc-950 dark:text-white sm:text-3xl">
                        {{ $progress['phase_label'] ?? __('Document tracker') }}
                    </h3>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ $progress['summary'] }}</p>
                    @if (($progress['phase'] ?? '') === 'awaiting_video')
                        <div class="mt-4">
                            <a
                                href="{{ route($caseShowRoute, [$notaryRequest, 'signers']) }}"
                                wire:navigate
                                class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                            >
                                {{ __('Go to video verification') }}
                            </a>
                        </div>
                    @endif
                </div>

                <div class="w-full rounded-2xl border border-white/70 bg-white/85 p-4 shadow-sm backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/80 lg:max-w-xs">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Completed') }}</div>
                            <div class="mt-1 text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                {{ (int) ($progress['completed'] ?? 0) }}/{{ (int) ($progress['total'] ?? 0) }} {{ __('parties') }}
                            </div>
                        </div>
                        <div class="text-4xl font-bold tabular-nums text-sky-700 dark:text-sky-300">
                            {{ max(0, min(100, (int) ($progress['percent'] ?? 0))) }}%
                        </div>
                    </div>
                    <div class="mt-4 h-3 overflow-hidden rounded-full bg-sky-100 dark:bg-sky-950/50">
                        <div
                            class="h-full rounded-full bg-gradient-to-r from-sky-500 via-teal-500 to-emerald-500 transition-all duration-500"
                            style="width: {{ max(0, min(100, (int) ($progress['percent'] ?? 0))) }}%"
                            role="progressbar"
                            aria-valuenow="{{ (int) ($progress['percent'] ?? 0) }}"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            aria-label="{{ __('Signing progress') }}"
                        ></div>
                    </div>
                </div>
            </div>
        </div>

        @if (($progress['tracker_steps'] ?? []) !== [])
            <div class="border-y border-zinc-200 bg-zinc-50/70 px-4 py-4 dark:border-zinc-800 dark:bg-zinc-900/50 sm:px-6 lg:px-8">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                    @foreach ($progress['tracker_steps'] as $index => $step)
                        @php
                            $stepState = (string) ($step['state'] ?? 'upcoming');
                            $stepTone = match ($stepState) {
                                'complete' => 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/25 dark:text-emerald-100',
                                'current' => 'border-sky-300 bg-sky-50 text-sky-950 ring-2 ring-sky-100 dark:border-sky-800 dark:bg-sky-950/30 dark:text-sky-100 dark:ring-sky-950/60',
                                default => 'border-zinc-200 bg-white text-zinc-600 dark:border-zinc-800 dark:bg-zinc-950/60 dark:text-zinc-400',
                            };
                            $dotTone = match ($stepState) {
                                'complete' => 'bg-emerald-500 text-white',
                                'current' => 'bg-sky-500 text-white',
                                default => 'bg-zinc-200 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400',
                            };
                        @endphp
                        <div
                            wire:key="tracker-step-{{ $step['key'] ?? $index }}"
                            class="rounded-2xl border p-3 {{ $stepTone }}"
                        >
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
        @endif

        <div class="space-y-4 p-4 sm:p-6 lg:p-8">
            @if (($progress['current_turn_name'] ?? null) !== null)
                <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-900/40 dark:bg-sky-950/25 dark:text-sky-100">
                    <span class="font-semibold">{{ __('Current signer') }}:</span>
                    {{ $progress['current_turn_name'] }}
                </div>
            @endif

            @foreach ($progress['documents'] ?? [] as $documentProgress)
                <section
                    class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900/60"
                    wire:key="tracker-document-{{ $documentProgress['document_id'] }}"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="truncate text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ $documentProgress['title'] }}</div>
                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                <span>{{ ucfirst((string) $documentProgress['status']) }}</span>
                                <span class="text-zinc-300 dark:text-zinc-700">•</span>
                                <span>{{ (int) $documentProgress['completed'] }}/{{ (int) $documentProgress['total'] }} {{ __('parties complete') }}</span>
                                @if ($documentProgress['is_sequential'] ?? false)
                                    <span class="text-zinc-300 dark:text-zinc-700">•</span>
                                    <span>{{ __('Sequential') }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="w-full sm:max-w-44">
                            <div class="flex items-center justify-between gap-2 text-xs">
                                <span class="font-semibold text-zinc-600 dark:text-zinc-300">{{ __('Document completion') }}</span>
                                <span class="font-bold tabular-nums text-sky-700 dark:text-sky-300">{{ (int) $documentProgress['percent'] }}%</span>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                <div class="h-full rounded-full bg-sky-500 transition-all duration-500" style="width: {{ max(0, min(100, (int) $documentProgress['percent'])) }}%"></div>
                            </div>
                        </div>
                    </div>

                    <ul class="mt-4 grid gap-3">
                        @foreach ($documentProgress['signers'] as $signerProgress)
                            <li
                                class="rounded-2xl border border-zinc-200/80 bg-zinc-50/60 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-950/50"
                                wire:key="tracker-signer-{{ $documentProgress['document_id'] }}-{{ $signerProgress['signer_id'] }}"
                                data-tracker-signer-row
                            >
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                    <div class="flex min-w-0 items-start gap-3">
                                        <span @class([
                                            'mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' => $signerProgress['is_completed'],
                                            'bg-amber-100 text-amber-700 ring-2 ring-amber-300 dark:bg-amber-950/50 dark:text-amber-300 dark:ring-amber-700' => $signerProgress['can_act_now'],
                                            'bg-zinc-200 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400' => ! $signerProgress['is_completed'] && ! $signerProgress['can_act_now'],
                                        ])>
                                            @if ($signerProgress['is_completed'])
                                                <flux:icon.check variant="mini" class="size-4" />
                                            @elseif ($signerProgress['can_act_now'])
                                                <span class="relative flex size-2.5">
                                                    <span class="absolute inline-flex size-full animate-ping rounded-full bg-amber-400 opacity-75"></span>
                                                    <span class="relative inline-flex size-2.5 rounded-full bg-amber-500"></span>
                                                </span>
                                            @else
                                                {{ $signerProgress['signing_order'] ?? '·' }}
                                            @endif
                                        </span>
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="truncate text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $signerProgress['name'] }}</span>
                                                <flux:badge size="sm" color="zinc">{{ $signerProgress['role_label'] }}</flux:badge>
                                                <flux:badge
                                                    size="sm"
                                                    :color="$signerProgress['is_completed'] ? 'emerald' : 'amber'"
                                                >
                                                    {{ $signerProgress['status_label'] }}
                                                </flux:badge>
                                            </div>
                                            <div class="mt-0.5 truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $signerProgress['email'] }}</div>
                                            @if ($signerProgress['completed_at'])
                                                <div class="mt-1 text-xs font-medium text-emerald-700 dark:text-emerald-300">
                                                    {{ __('Completed :date', ['date' => $signerProgress['completed_at']]) }}
                                                </div>
                                            @elseif (is_string($signerProgress['waiting_label'] ?? null) && $signerProgress['waiting_label'] !== '')
                                                <div class="mt-1 text-xs font-medium text-amber-700 dark:text-amber-300">
                                                    {{ $signerProgress['waiting_label'] }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    @if ($isNotary && ($signerProgress['can_resend'] || $signerProgress['can_remind']))
                                        <div class="flex shrink-0 flex-wrap gap-1.5 lg:justify-end">
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
                </section>
            @endforeach
        </div>
    </div>
@endif
