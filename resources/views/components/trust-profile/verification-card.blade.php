@props([
    'title',
    'description' => '',
    'status' => 'not_started',
    'statusLabel' => '',
    'icon' => 'shield-check',
    'actionRoute' => null,
    'actionRouteParameters' => [],
    'actionLabel' => null,
    'wireAction' => null,
])

@php
    $statusStyles = match ($status) {
        'verified' => 'border-emerald-200/80 bg-emerald-50/80 dark:border-emerald-900/40 dark:bg-emerald-950/30',
        'pending' => 'border-amber-200/80 bg-amber-50/60 dark:border-amber-900/40 dark:bg-amber-950/25',
        'rejected' => 'border-rose-200/80 bg-rose-50/60 dark:border-rose-900/40 dark:bg-rose-950/25',
        'action_required' => 'border-sky-200/80 bg-sky-50/60 dark:border-sky-900/40 dark:bg-sky-950/25',
        default => 'border-zinc-200/90 bg-white dark:border-zinc-700/80 dark:bg-zinc-900/50',
    };

    $badgeVariant = match ($status) {
        'verified' => 'success',
        'pending' => 'warning',
        'rejected' => 'danger',
        'action_required' => 'primary',
        default => 'outline',
    };

    $hasAction = filled($actionRoute) || filled($wireAction);
@endphp

<div {{ $attributes->merge(['class' => "flex h-full min-h-[9.5rem] flex-col overflow-hidden rounded-2xl border p-4 transition-shadow hover:shadow-sm sm:p-5 {$statusStyles}"]) }}>
    <div class="flex gap-3">
        <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white/80 text-teal-700 shadow-sm ring-1 ring-zinc-200/80 dark:bg-zinc-800 dark:text-teal-300 dark:ring-zinc-700 sm:size-11">
            <flux:icon :name="$icon" class="size-5" />
        </div>

        <div class="min-w-0 flex-1">
            <div class="flex items-start justify-between gap-2">
                <p class="min-w-0 flex-1 text-sm font-semibold leading-snug text-zinc-900 dark:text-zinc-50 sm:text-base">
                    {{ $title }}
                </p>
                <flux:badge :variant="$badgeVariant" size="sm" class="!shrink-0 whitespace-nowrap">
                    {{ $statusLabel }}
                </flux:badge>
            </div>

            @if ($description)
                <p class="mt-1.5 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">
                    {{ $description }}
                </p>
            @endif
        </div>
    </div>

    <div class="mt-auto flex min-h-[2.25rem] items-end justify-end pt-3">
        @if ($hasAction)
            @if ($wireAction)
                <flux:button variant="ghost" size="sm" type="button" wire:click="{{ $wireAction }}">
                    {{ $actionLabel }}
                </flux:button>
            @elseif ($actionRoute)
                <flux:button variant="ghost" size="sm" :href="route($actionRoute, $actionRouteParameters)" wire:navigate>
                    {{ $actionLabel }}
                </flux:button>
            @endif
        @endif
    </div>
</div>
