@props([
    'notaryRequest',
    'sessionId',
    'label' => null,
    'size' => 'sm',
])

@php
    $liveSessionRoute = auth()->user()?->role->value === 'notary'
        ? 'notary.requests.session.live'
        : 'notary-requests.session.live';

    $sizeClasses = $size === 'md'
        ? 'px-4 py-2.5 text-sm'
        : 'px-3 py-2 text-xs';
@endphp

<a
    href="{{ route($liveSessionRoute, [$notaryRequest, $sessionId]) }}"
    target="_blank"
    {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-lg bg-zinc-900 font-semibold text-white shadow-sm hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200 {$sizeClasses}"]) }}
>
    <svg class="{{ $size === 'md' ? 'h-4 w-4' : 'h-3.5 w-3.5' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z"/>
    </svg>
    {{ $label ?? __('Join video call') }}
</a>
