<div class="ui-panel max-h-[calc(100vh-7rem)] overflow-y-auto overscroll-y-contain p-5 sm:p-6 [scrollbar-gutter:stable]">
    <flux:heading size="lg" class="mb-3">{{ __('Case workflow') }}</flux:heading>
    <ol class="space-y-3">
        @foreach ($workflowSteps as $step)
            @php
                $state = (string) ($step['state'] ?? 'upcoming');
                $isCurrent = $state === 'current';
                $isComplete = $state === 'complete';
            @endphp
            <li class="flex gap-3">
                <span @class([
                    'mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full',
                    'bg-emerald-500' => $isComplete,
                    'bg-sky-500 ring-2 ring-sky-200 dark:ring-sky-900' => $isCurrent,
                    'bg-zinc-300 dark:bg-zinc-600' => ! $isComplete && ! $isCurrent,
                ])></span>
                <div class="min-w-0">
                    <p @class([
                        'text-sm font-medium',
                        'text-sky-700 dark:text-sky-300' => $isCurrent,
                        'text-emerald-700 dark:text-emerald-400' => $isComplete && ! $isCurrent,
                        'text-zinc-600 dark:text-zinc-400' => ! $isComplete && ! $isCurrent,
                    ])>
                        {{ $step['label'] }}
                        @if ($isCurrent)
                            <span class="ms-1 text-[10px] font-semibold uppercase tracking-wider text-sky-600 dark:text-sky-400">{{ __('Current') }}</span>
                        @endif
                    </p>
                    @if (! empty($step['description']))
                        <p @class([
                            'text-xs leading-relaxed',
                            'text-sky-600/90 dark:text-sky-400/90' => $isCurrent,
                            'text-zinc-500 dark:text-zinc-500' => ! $isCurrent,
                        ])>{{ $step['description'] }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
</div>
