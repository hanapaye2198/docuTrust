@props([
    'status',
])

@php
    $statusEnum = $status instanceof \App\Enums\DocumentStatus
        ? $status
        : \App\Enums\DocumentStatus::from((string) $status);

    $classes = match ($statusEnum) {
        \App\Enums\DocumentStatus::Draft => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200',
        \App\Enums\DocumentStatus::Pending => 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
        \App\Enums\DocumentStatus::Completed => 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100',
        \App\Enums\DocumentStatus::Declined => 'bg-rose-100 text-rose-900 dark:bg-rose-900/40 dark:text-rose-100',
        \App\Enums\DocumentStatus::Cancelled => 'bg-orange-100 text-orange-900 dark:bg-orange-900/40 dark:text-orange-100',
        \App\Enums\DocumentStatus::Archived => 'bg-slate-200 text-slate-800 dark:bg-slate-700 dark:text-slate-100',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium capitalize shadow-sm ring-1 ring-black/5 dark:ring-white/10 '.$classes]) }}>
    {{ $statusEnum->value }}
</span>
