@php
    $progress = $this->notaryCaseProgress;
@endphp

<div class="ui-panel p-5 sm:p-6">
    <flux:heading size="lg" class="mb-1">{{ __('Case progress') }}</flux:heading>
    <p class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
        {{ __('Follow the highlighted step. Details for each phase are in the main tabs.') }}
    </p>
    <ol class="space-y-3">
        @foreach ($workflowSteps as $index => $step)
            @php
                $state = (string) ($step['state'] ?? 'upcoming');
                $isCurrent = $state === 'current';
                $isComplete = $state === 'complete';
            @endphp
            <li class="flex gap-3">
                <span @class([
                    'mt-2 flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                    'bg-emerald-500 text-white' => $isComplete,
                    'bg-sky-500 text-white ring-2 ring-sky-200 dark:ring-sky-900' => $isCurrent,
                    'bg-zinc-200 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300' => ! $isComplete && ! $isCurrent,
                ])>
                    @if ($isComplete)
                        <flux:icon.check class="size-3.5" />
                    @else
                        {{ $index + 1 }}
                    @endif
                </span>
                <div class="min-w-0">
                    <p @class([
                        'text-sm font-medium leading-snug',
                        'text-sky-800 dark:text-sky-200' => $isCurrent,
                        'text-emerald-800 dark:text-emerald-300' => $isComplete && ! $isCurrent,
                        'text-zinc-600 dark:text-zinc-400' => ! $isComplete && ! $isCurrent,
                    ])>
                        {{ $step['label'] }}
                    </p>
                    @if ($isCurrent && ! empty($step['description']))
                        <p class="mt-1 text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">{{ $step['description'] }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
</div>
