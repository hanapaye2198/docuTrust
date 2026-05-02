@props([
    /**
     * @var list<array{label: string, href?: string|null}>
     */
    'items' => [],
])

@if ($items !== [])
    <flux:breadcrumbs {{ $attributes->class('mb-2 shrink-0 text-sm text-zinc-600 dark:text-zinc-400') }}>
        @foreach ($items as $item)
            @if (! empty($item['href']))
                <flux:breadcrumbs.item :href="$item['href']" wire:navigate>
                    {{ $item['label'] }}
                </flux:breadcrumbs.item>
            @else
                <flux:breadcrumbs.item>
                    {{ $item['label'] }}
                </flux:breadcrumbs.item>
            @endif
        @endforeach
    </flux:breadcrumbs>
@endif
