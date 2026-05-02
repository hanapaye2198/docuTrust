@props(['current' => 1])

@php
    $steps = [
        1 => __('Template'),
        2 => __('Prepare'),
    ];
@endphp

<nav aria-label="{{ __('Template steps') }}" class="flex justify-center">
    <ol class="flex flex-wrap items-center justify-center gap-1 sm:gap-2">
        @foreach ($steps as $num => $label)
            <li class="flex items-center gap-2">
                <span
                    @class([
                        'flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                        'bg-teal-600 text-white shadow-sm dark:bg-teal-500' => (int) $current === $num,
                        'bg-zinc-200 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400' => (int) $current !== $num,
                    ])
                >
                    {{ $num }}
                </span>
                <span
                    @class([
                        'hidden text-sm font-medium sm:inline',
                        'text-zinc-900 dark:text-zinc-50' => (int) $current === $num,
                        'text-zinc-500 dark:text-zinc-400' => (int) $current !== $num,
                    ])
                >
                    {{ $label }}
                </span>
                @if ($num < count($steps))
                    <span class="hidden text-zinc-300 sm:inline dark:text-zinc-600" aria-hidden="true">→</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
