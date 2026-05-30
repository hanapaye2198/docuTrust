@php
    $currentStep = collect($settlementSteps)->first(fn (array $step): bool => ($step['state'] ?? '') === 'current');
@endphp

<div class="ui-panel p-5 sm:p-6">
    <flux:heading size="lg" class="!mb-2">{{ __('Settlement checklist') }}</flux:heading>
    <p class="text-sm text-zinc-600 dark:text-zinc-400">
        @if ($isNotary)
            {{ __('Complete each step in order. The highlighted row is your next action.') }}
        @else
            {{ __('Your attorney is finalizing notarization. Complete payment when it becomes available.') }}
        @endif
    </p>

    @if ($currentStep)
        <div class="mt-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-900/40 dark:bg-sky-950/30 dark:text-sky-100">
            <div class="font-semibold">{{ __('Next step') }}: {{ $currentStep['label'] }}</div>
            <p class="mt-1 text-sky-800 dark:text-sky-200">{{ $currentStep['description'] }}</p>
        </div>
    @endif

    <ol class="mt-5 space-y-2">
        @foreach ($settlementSteps as $index => $step)
            @php
                $stepState = $step['state'] ?? 'upcoming';
                $isClientStep = ($step['actor'] ?? '') === 'client';
                $showToViewer = $isNotary
                    || ($isClientStep && ($canPayNotaryFee || $isRequester))
                    || ($stepState === 'complete' && ! $isNotary);

                if (! $showToViewer && ! $isNotary) {
                    continue;
                }

                $isCollapsed = $stepState === 'complete';
                $waitingOn = $step['waiting_on'] ?? null;
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
            @endphp
            <li @class([
                'flex gap-3 rounded-xl border border-zinc-200/80 dark:border-zinc-700/80',
                'px-4 py-2' => $isCollapsed,
                'px-4 py-3' => ! $isCollapsed,
            ])>
                <span class="mt-1.5 inline-flex size-2.5 shrink-0 rounded-full {{ $dotClass }}"></span>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm {{ $labelClass }}">{{ $index + 1 }}. {{ $step['label'] }}</span>
                        @if ($isClientStep)
                            <flux:badge size="sm" color="zinc">{{ __('Client') }}</flux:badge>
                        @elseif (($step['actor'] ?? '') === 'attorney')
                            <flux:badge size="sm" color="zinc">{{ __('Attorney') }}</flux:badge>
                        @endif
                        @if ($stepState === 'complete')
                            <flux:badge size="sm" color="emerald">{{ __('Done') }}</flux:badge>
                        @elseif ($stepState === 'current')
                            <flux:badge size="sm" color="sky">{{ __('Now') }}</flux:badge>
                        @elseif ($waitingOn !== null)
                            @php
                                $viewerRole = $isNotary ? 'attorney' : 'client';
                            @endphp
                            @if ($waitingOn !== $viewerRole)
                                <flux:badge size="sm" color="amber">
                                    {{ $waitingOn === 'client' ? __('Waiting on client') : __('Waiting on attorney') }}
                                </flux:badge>
                            @endif
                        @endif
                    </div>

                    @unless ($isCollapsed)
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $step['description'] }}</p>
                        @if ($stepState === 'current' && $isNotary && ! empty($step['href']))
                            <div class="mt-2">
                                <flux:button size="sm" variant="primary" :href="$step['href']" wire:navigate>
                                    {{ __('Open') }}
                                </flux:button>
                            </div>
                        @elseif ($stepState === 'current' && ! empty($step['section_id']))
                            <div class="mt-2">
                                <button
                                    type="button"
                                    wire:click="$dispatch('scroll-to-section', { id: '{{ $step['section_id'] }}' })"
                                    class="inline-flex items-center justify-center rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
                                >
                                    {{ __('Go to section') }}
                                </button>
                            </div>
                        @elseif ($stepState === 'current' && ! $isNotary && ($step['key'] ?? '') === 'payment')
                            <div class="mt-2">
                                <button
                                    type="button"
                                    wire:click="$dispatch('scroll-to-section', { id: 'section-payment' })"
                                    class="inline-flex items-center justify-center rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
                                >
                                    {{ __('Go to payment') }}
                                </button>
                            </div>
                        @endif
                    @endunless
                </div>
            </li>
        @endforeach
    </ol>
</div>
