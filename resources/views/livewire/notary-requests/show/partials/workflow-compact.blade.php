@php
    use App\Enums\NotaryRequestStatus;
@endphp

            <section id="section-workflow" class="ui-panel order-1 scroll-mt-6 p-6 sm:p-8">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:heading size="lg" class="!mb-0">{{ __('Workflow progress') }}</flux:heading>
                    <flux:badge size="sm" color="zinc">{{ trans_choice(':count stage|:count stages', count($workflowSteps), ['count' => count($workflowSteps)]) }}</flux:badge>
                </div>
                <div class="mt-5 flex gap-2 overflow-x-auto pb-2">
                    @foreach ($workflowSteps as $index => $step)
                        @php
                            $stepStyles = match ($step['state']) {
                                'complete' => 'border-emerald-200 bg-emerald-50 dark:border-emerald-900/40 dark:bg-emerald-950/30',
                                'current' => 'border-sky-200 bg-sky-50 dark:border-sky-900/40 dark:bg-sky-950/30',
                                default => 'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900',
                            };
                            $badgeStyles = match ($step['state']) {
                                'complete' => 'bg-emerald-600 text-white dark:bg-emerald-500',
                                'current' => 'bg-sky-600 text-white dark:bg-sky-500',
                                default => 'bg-zinc-200 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-100',
                            };
                            $stateLabel = match ($step['state']) {
                                'complete' => __('Complete'),
                                'current' => __('Current'),
                                default => __('Upcoming'),
                            };
                        @endphp
                        <div class="flex min-w-[8.5rem] flex-1 flex-col rounded-xl border p-3.5 {{ $stepStyles }}" title="{{ $step['description'] }}">
                            <div class="flex items-center justify-between gap-1.5">
                                <span class="inline-flex size-6 items-center justify-center rounded-full text-[10px] font-bold {{ $badgeStyles }}">{{ $index + 1 }}</span>
                                <span class="text-[9px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ $stateLabel }}</span>
                            </div>
                            <div class="mt-2 text-xs font-semibold leading-snug text-zinc-900 dark:text-zinc-100">{{ $step['label'] }}</div>
                            <p class="mt-1 line-clamp-2 text-[10px] leading-tight text-zinc-500 dark:text-zinc-400">{{ $step['description'] }}</p>
                        </div>
                    @endforeach
                </div>

                @if ($this->currentWorkflowStep && $notaryRequest->status !== NotaryRequestStatus::Notarized)
                    <flux:callout variant="info" class="mt-5" icon="information-circle">
                        <flux:callout.heading>{{ __('Focus on this step') }}</flux:callout.heading>
                        <flux:callout.text>{{ $this->currentWorkflowStep['description'] }}</flux:callout.text>
                    </flux:callout>
                @endif
            </section>
