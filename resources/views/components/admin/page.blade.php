{{-- Full-width content shell for platform admin dashboards. --}}
@props([
    'gap' => 'gap-8',
    'wide' => false,
])

<div {{ $attributes->class([
    'flex w-full min-w-0 max-w-none flex-col',
    $gap,
    $wide ? 'px-0 py-4 sm:px-2 lg:px-4' : 'px-2 py-4 sm:px-4 lg:px-6',
]) }}>
    {{ $slot }}
</div>
