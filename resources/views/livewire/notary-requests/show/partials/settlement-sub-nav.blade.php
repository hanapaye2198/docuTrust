@php
    $shortLabels = [
        'settlement_fee' => __('Fee'),
        'payment' => __('Pay'),
        'registry_draft' => __('Register'),
        'seal' => __('Seal'),
        'register_entry' => __('Book'),
        'attorney_review' => __('Review'),
        'digital_notarization' => __('Digital'),
    ];

    $navSteps = collect($settlementSteps)
        ->filter(function (array $step) use ($isNotary, $canPayNotaryFee, $isRequester): bool {
            if (empty($step['section_id'])) {
                return false;
            }

            $isClientStep = ($step['actor'] ?? '') === 'client';
            $stepState = $step['state'] ?? 'upcoming';

            if ($isNotary) {
                return true;
            }

            return $isClientStep && ($canPayNotaryFee || $isRequester);
        })
        ->values();
@endphp

@if ($navSteps->isNotEmpty())
    <nav
        class="sticky top-0 z-20 -mx-1 mb-1 border-b border-zinc-200/90 bg-white/95 px-1 py-2 backdrop-blur-sm dark:border-zinc-800 dark:bg-zinc-950/95"
        aria-label="{{ __('Fees and register sections') }}"
        data-settlement-sub-nav
        data-settlement-section-ids="{{ $navSteps->pluck('section_id')->implode(',') }}"
    >
        <div class="flex gap-1 overflow-x-auto pb-0.5">
            @foreach ($navSteps as $step)
                @php
                    $sectionId = $step['section_id'];
                    $stepState = $step['state'] ?? 'upcoming';
                    $navLabel = $shortLabels[$step['key'] ?? ''] ?? $step['label'];
                @endphp
                <button
                    type="button"
                    data-settlement-nav-target="{{ $sectionId }}"
                    wire:click="$dispatch('scroll-to-section', { id: '{{ $sectionId }}' })"
                    @class([
                        'inline-flex shrink-0 items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold transition',
                        'border-sky-300 bg-sky-50 text-sky-900 dark:border-sky-800 dark:bg-sky-950/50 dark:text-sky-100' => $stepState === 'current',
                        'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-200' => $stepState === 'complete',
                        'border-zinc-200 bg-white text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800' => ! in_array($stepState, ['current', 'complete'], true),
                    ])
                >
                    @if ($stepState === 'complete')
                        <flux:icon.check class="size-3.5 shrink-0" />
                    @elseif ($stepState === 'current')
                        <span class="size-1.5 shrink-0 rounded-full bg-sky-500"></span>
                    @endif
                    {{ $navLabel }}
                </button>
            @endforeach
        </div>
    </nav>
@endif
