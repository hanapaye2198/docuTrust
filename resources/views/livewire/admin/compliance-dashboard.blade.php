<?php

use App\Services\Compliance\SignatureComplianceService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    /**
     * @var array<string, mixed>|null
     */
    public ?array $report = null;

    public function mount(SignatureComplianceService $complianceService): void
    {
        $this->report = $complianceService->assess();
    }

    public function refreshReport(SignatureComplianceService $complianceService): void
    {
        $this->report = $complianceService->assess();
    }
}; ?>

<x-admin.page>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ __('Signature Compliance') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Legal-grade readiness audit. HSM, KMS, and PKCS#11 are disabled for early production.') }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <flux:button size="sm" variant="outline" wire:click="refreshReport">{{ __('Refresh') }}</flux:button>
            <flux:button size="sm" variant="outline" :href="route('admin.compliance.report.json')" target="_blank">
                {{ __('JSON') }}
            </flux:button>
            <flux:button size="sm" variant="primary" :href="route('admin.compliance.report.pdf')">
                {{ __('Download PDF') }}
            </flux:button>
        </div>
    </div>

    @if ($report !== null)
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('Overall score') }}</p>
                <p class="mt-2 text-3xl font-bold tabular-nums text-teal-600 dark:text-teal-400">{{ $report['overall_score'] }}%</p>
            </div>
            <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900 sm:col-span-2">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('Trust level') }}</p>
                <p class="mt-2 text-lg font-semibold text-zinc-900 dark:text-white">
                    {{ __('Level :level', ['level' => $report['trust_level']['level'] ?? 1]) }} —
                    {{ $report['trust_level']['label'] ?? '' }}
                </p>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $report['trust_level']['description'] ?? '' }}</p>
                @if (! empty($report['trust_level']['cap_reason']))
                    <p class="mt-2 text-sm text-amber-600 dark:text-amber-400">{{ $report['trust_level']['cap_reason'] }}</p>
                @endif
            </div>
            <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('Phase') }}</p>
                <p class="mt-2 text-lg font-semibold capitalize text-zinc-900 dark:text-white">{{ str_replace('_', ' ', $report['phase'] ?? '') }}</p>
                <p class="mt-1 text-xs text-zinc-500">{{ $report['assessed_at'] ?? '' }}</p>
            </div>
        </div>

        <div class="flex flex-col gap-4">
            @foreach ($report['categories'] as $category)
                @php
                    $status = $category['status'] ?? 'MISSING';
                    $statusColor = match ($status) {
                        'READY' => 'text-emerald-600 dark:text-emerald-400',
                        'PARTIAL' => 'text-amber-600 dark:text-amber-400',
                        'DISABLED' => 'text-zinc-400 dark:text-zinc-500',
                        default => 'text-red-600 dark:text-red-400',
                    };
                @endphp
                <div class="rounded-xl border border-zinc-200/80 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-zinc-900">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold text-zinc-900 dark:text-white">{{ $category['title'] ?? '' }}</h2>
                            <p class="mt-1 text-sm font-medium {{ $statusColor }}">{{ $status }}</p>
                            @if (! empty($category['note']))
                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $category['note'] }}</p>
                            @endif
                        </div>
                        <div class="text-right">
                            @if ($status === 'DISABLED')
                                <span class="text-sm text-zinc-400">{{ __('Excluded from score') }}</span>
                            @else
                                <span class="text-2xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ $category['score_percentage'] ?? 0 }}%</span>
                            @endif
                        </div>
                    </div>

                    @if (! empty($category['missing_requirements']))
                        <div class="mt-4">
                            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('Missing requirements') }}</p>
                            <ul class="mt-2 list-inside list-disc text-sm text-zinc-600 dark:text-zinc-300">
                                @foreach ($category['missing_requirements'] as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (! empty($category['implementation_recommendations']))
                        <div class="mt-4">
                            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('Recommendations') }}</p>
                            <ul class="mt-2 list-inside list-disc text-sm text-zinc-600 dark:text-zinc-300">
                                @foreach ($category['implementation_recommendations'] as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-400">{{ __('Supported standards') }}</h2>
                <ul class="mt-3 list-inside list-disc text-sm text-zinc-700 dark:text-zinc-300">
                    @foreach ($report['standards_supported'] as $standard)
                        <li>{{ $standard }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-400">{{ __('Missing standards') }}</h2>
                <ul class="mt-3 list-inside list-disc text-sm text-zinc-700 dark:text-zinc-300">
                    @foreach ($report['standards_missing'] as $standard)
                        <li>{{ $standard }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
</x-admin.page>
