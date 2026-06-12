@php
    $currentStep = collect($settlementSteps)->first(fn (array $step): bool => ($step['state'] ?? '') === 'current');
@endphp

<div class="ui-panel p-5 sm:p-6">
    <flux:heading size="lg" class="!mb-2">{{ __('Fees & register steps') }}</flux:heading>
    <p class="text-base text-zinc-600 dark:text-zinc-400">
        @if ($isNotary)
            {{ __('Work through these steps in order. Green means done. Blue is your turn.') }}
        @else
            {{ __('Your attorney is finishing this case. Complete payment when it becomes available.') }}
        @endif
    </p>

    @if ($currentStep && ($currentStep['waiting_on'] ?? null) === 'client' && $isNotary)
        <div class="mt-4 rounded-xl border-2 border-amber-300 bg-amber-50 px-4 py-4 text-base text-amber-950 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100">
            <div class="font-semibold">{{ __('Waiting for your client') }}</div>
            <p class="mt-1">{{ $currentStep['description'] }}</p>
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
                            <flux:badge size="sm" color="zinc">{{ __('You') }}</flux:badge>
                        @endif
                        @if ($stepState === 'complete')
                            <flux:badge size="sm" color="emerald">{{ __('Done') }}</flux:badge>
                        @elseif ($stepState === 'current')
                            <flux:badge size="sm" color="sky">{{ __('Your turn') }}</flux:badge>
                        @elseif ($waitingOn === 'client' && $isNotary)
                            <flux:badge size="sm" color="amber">{{ __('Waiting on client') }}</flux:badge>
                        @endif
                    </div>

                    @unless ($isCollapsed)
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $step['description'] }}</p>
                        @if ($stepState === 'current' && $isNotary && ! empty($step['href']))
                            <div class="mt-2">
                                <flux:button size="sm" variant="primary" :href="$step['href']" wire:navigate>
                                    {{ __('Continue') }}
                                </flux:button>
                            </div>
                        @elseif ($stepState === 'current' && ! empty($step['section_id']))
                            <div class="mt-2">
                                <button
                                    type="button"
                                    wire:click="$dispatch('scroll-to-section', { id: '{{ $step['section_id'] }}' })"
                                    class="inline-flex min-h-10 items-center justify-center rounded-lg border border-zinc-200 bg-white px-4 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
                                >
                                    {{ __('Open this step') }}
                                </button>
                            </div>
                        @elseif ($stepState === 'current' && ! $isNotary && ($step['key'] ?? '') === 'payment')
                            <div class="mt-2">
                                <button
                                    type="button"
                                    wire:click="$dispatch('scroll-to-section', { id: 'section-payment' })"
                                    class="inline-flex min-h-10 items-center justify-center rounded-lg border border-zinc-200 bg-white px-4 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
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
