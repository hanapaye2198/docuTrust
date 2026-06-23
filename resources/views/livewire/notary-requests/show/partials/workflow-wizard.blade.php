@php
    $sourceSteps = collect($workflowSteps)->values();
    $resolveGroupState = function (array $indexes) use ($sourceSteps): string {
        $steps = collect($indexes)
            ->map(fn (int $index) => $sourceSteps->get($index))
            ->filter();

        if ($steps->isEmpty()) {
            return 'upcoming';
        }

        if ($steps->every(fn (array $step): bool => ($step['state'] ?? '') === 'complete')) {
            return 'complete';
        }

        if ($steps->contains(fn (array $step): bool => ($step['state'] ?? '') === 'current')) {
            return 'current';
        }

        if ($steps->contains(fn (array $step): bool => ($step['state'] ?? '') === 'blocked')) {
            return 'blocked';
        }

        return 'upcoming';
    };

    $wizardSteps = collect([
        [
            'label' => __('Document'),
            'description' => __('Prepare and send the instrument.'),
            'state' => $resolveGroupState([0, 1]),
            'page' => 'document',
        ],
        [
            'label' => __('Signers & video'),
            'description' => __('Signer signatures and video verification.'),
            'state' => $resolveGroupState([2]),
            'page' => 'signers',
        ],
        [
            'label' => __('Attorney signature'),
            'description' => __('Attorney signs after video verification.'),
            'state' => $resolveGroupState([3]),
            'page' => 'document',
        ],
        [
            'label' => __('Fees & register'),
            'description' => ($hasAttorneySealOnFile ?? false)
                ? __('Seal uploaded. Finish payment, register, and finalization.')
                : __('Payment, register, seal, and finalization.'),
            'state' => $resolveGroupState([4, 5, 6, 7, 8, 9, 10]),
            'page' => 'fees',
            'meta' => ($hasAttorneySealOnFile ?? false) ? __('Seal uploaded') : null,
        ],
    ]);

    $currentStepIndex = $wizardSteps->search(fn (array $step): bool => ($step['state'] ?? '') === 'current');

    if ($currentStepIndex === false) {
        $currentStepIndex = $wizardSteps->search(fn (array $step): bool => ($step['state'] ?? '') !== 'complete');
    }

    $currentStep = $currentStepIndex !== false ? $wizardSteps->get($currentStepIndex) : $wizardSteps->last();
@endphp

@if ($wizardSteps->isNotEmpty())
    <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-950" aria-label="{{ __('Case workflow steps') }}">
        <div class="border-b border-zinc-200 px-4 py-4 dark:border-zinc-800 sm:px-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-zinc-500 dark:text-zinc-400">{{ __('Workflow steps') }}</p>
                    @if (is_array($currentStep))
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $currentStep['description'] }}</p>
                    @endif
                </div>

                @if ($currentStepIndex !== false)
                    <flux:badge color="sky">
                        {{ __('Step :current of :total', ['current' => $currentStepIndex + 1, 'total' => $wizardSteps->count()]) }}
                    </flux:badge>
                @endif
            </div>
        </div>

        <nav class="px-4 py-5 sm:px-6" aria-label="{{ __('Case workflow progress') }}">
            <ol class="mx-auto flex max-w-5xl items-center justify-between gap-2">
                @foreach ($wizardSteps as $index => $step)
                    @php
                        $state = (string) ($step['state'] ?? 'upcoming');
                        $isComplete = $state === 'complete';
                        $isCurrent = $state === 'current';
                        $stepUrl = route($caseShowRoute, [$notaryRequest, $step['page']]);
                    @endphp

                    <li class="flex min-w-0 flex-1 items-center gap-2">
                        <a
                            href="{{ $stepUrl }}"
                            wire:navigate
                            class="group flex min-w-0 items-center gap-2"
                            title="{{ $step['description'] }}"
                        >
                            <span @class([
                                'flex size-7 shrink-0 items-center justify-center rounded-full border text-xs font-semibold transition',
                                'border-blue-600 bg-blue-600 text-white shadow-sm shadow-blue-600/25' => $isCurrent,
                                'border-emerald-600 bg-emerald-600 text-white' => $isComplete && ! $isCurrent,
                                'border-zinc-200 bg-white text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400' => ! $isComplete && ! $isCurrent,
                            ])>
                                @if ($isComplete && ! $isCurrent)
                                    <flux:icon.check class="size-3.5" />
                                @else
                                    {{ $index + 1 }}
                                @endif
                            </span>

                            <span @class([
                                'hidden truncate text-sm font-semibold sm:block',
                                'text-zinc-950 dark:text-white' => $isCurrent,
                                'text-zinc-500 dark:text-zinc-400' => ! $isCurrent,
                            ])>{{ $step['label'] }}</span>
                            @if (! empty($step['meta']))
                                <span class="hidden rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 lg:inline-flex">
                                    {{ $step['meta'] }}
                                </span>
                            @endif
                        </a>

                        @if (! $loop->last)
                            <span class="h-px flex-1 bg-zinc-200 dark:bg-zinc-700" aria-hidden="true"></span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </nav>
    </section>
@endif
