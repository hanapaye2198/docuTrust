{{-- Full-width content shell for platform admin dashboards. --}}
@props([
    'gap' => 'gap-8',
])

<div {{ $attributes->class([
    'flex w-full min-w-0 flex-col',
    $gap,
    'px-2 py-4 sm:px-4 lg:px-6',
]) }}>
    {{ $slot }}
</div>
