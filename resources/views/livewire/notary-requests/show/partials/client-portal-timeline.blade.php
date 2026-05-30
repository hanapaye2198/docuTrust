<ol class="mt-4 space-y-2">
    @foreach ($clientPortalTimeline as $index => $step)
        @php
            $stepState = $step['state'] ?? 'upcoming';
            $dotClass = match ($stepState) {
                'complete' => 'bg-emerald-500',
                'current' => 'bg-sky-500 ring-2 ring-sky-200 dark:ring-sky-900',
                'blocked' => 'bg-amber-400',
                default => 'bg-zinc-300 dark:bg-zinc-600',
            };
            $labelClass = match ($stepState) {
                'current' => 'font-semibold text-zinc-900 dark:text-zinc-100',
                'complete' => 'text-zinc-700 dark:text-zinc-300',
                default => 'text-zinc-500 dark:text-zinc-400',
            };
            $stateLabel = match ($stepState) {
                'complete' => __('Complete'),
                'current' => __('Current'),
                default => __('Upcoming'),
            };
        @endphp
        <li @class([
            'flex gap-3 rounded-xl border border-zinc-200/80 px-4 py-3 dark:border-zinc-700/80',
            'border-sky-200 bg-sky-50/60 dark:border-sky-900/40 dark:bg-sky-950/20' => $stepState === 'current',
            'opacity-80' => $stepState === 'complete',
        ])>
            <div class="flex flex-col items-center pt-0.5">
                <span @class(['size-2.5 shrink-0 rounded-full', $dotClass])></span>
                @if (! $loop->last)
                    <span class="mt-1 w-px flex-1 bg-zinc-200 dark:bg-zinc-700"></span>
                @endif
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-[10px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ $index + 1 }}</span>
                    <span @class(['text-sm', $labelClass])>{{ $step['label'] }}</span>
                    <flux:badge size="sm" :color="$stepState === 'complete' ? 'emerald' : ($stepState === 'current' ? 'sky' : 'zinc')">
                        {{ $stateLabel }}
                    </flux:badge>
                </div>
                <p class="mt-1 text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">{{ $step['description'] }}</p>
            </div>
        </li>
    @endforeach
</ol>
