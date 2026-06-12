@php
    $items = $prerequisites ?? [];
    $allComplete = $items !== [] && collect($items)->every(fn (array $item): bool => (bool) ($item['complete'] ?? false));
@endphp

@if ($items !== [])
    <div @class([
        'rounded-xl border px-4 py-3',
        'border-emerald-200 bg-emerald-50 dark:border-emerald-900/40 dark:bg-emerald-950/30' => $allComplete,
        'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900/40' => ! $allComplete,
    ])>
        <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
            {{ $allComplete ? __('Ready to apply seal') : __('Before you can apply the seal') }}
        </p>
        <ul class="mt-3 space-y-2">
            @foreach ($items as $item)
                <li class="flex items-start gap-2 text-sm">
                    @if ($item['complete'] ?? false)
                        <flux:icon.check class="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                        <span class="text-zinc-700 dark:text-zinc-300">{{ $item['label'] }}</span>
                    @else
                        <span class="mt-1 inline-flex size-2.5 shrink-0 rounded-full bg-amber-400"></span>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item['label'] }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
