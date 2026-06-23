@php
    $total = (int) ($videoVerificationQueue['total'] ?? 0);
    $verifiedCount = (int) ($videoVerificationQueue['verified_count'] ?? 0);
    $pendingCount = (int) ($videoVerificationQueue['pending_count'] ?? 0);
    $progressPercent = $total > 0 ? (int) round(($verifiedCount / $total) * 100) : 0;
@endphp

@if ($total > 0)
    <div class="rounded-2xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-950 dark:border-indigo-900/40 dark:bg-indigo-950/25 dark:text-indigo-100">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wider text-indigo-700 dark:text-indigo-300">{{ __('Verification queue') }}</div>
                <div class="mt-1 font-semibold">
                    {{ trans_choice(':verified of :total party verified|:verified of :total parties verified', $total, [
                        'verified' => $verifiedCount,
                        'total' => $total,
                    ]) }}
                </div>
            </div>
            @if ($videoVerificationQueue['complete'] ?? false)
                <flux:badge color="emerald">{{ __('All verified') }}</flux:badge>
            @elseif ($pendingCount > 0)
                <flux:badge color="sky">{{ trans_choice(':count remaining|:count remaining', $pendingCount, ['count' => $pendingCount]) }}</flux:badge>
            @endif
        </div>

        <div class="mt-3 h-2 overflow-hidden rounded-full bg-indigo-100 dark:bg-indigo-950/50">
            <div class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-sky-500 transition-all duration-300" style="width: {{ $progressPercent }}%"></div>
        </div>
    </div>
@endif
