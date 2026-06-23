@props([
    'heading' => '',
    'subheading' => '',
    'wide' => true,
])

<div class="w-full">
    <div class="min-w-0 self-stretch">
        @if ($heading !== '')
            <flux:heading>{{ $heading }}</flux:heading>
        @endif
        @if ($subheading !== '')
            <flux:subheading>{{ $subheading }}</flux:subheading>
        @endif

        <div @class([
            'w-full',
            $wide ? 'max-w-none' : 'max-w-3xl',
            'mt-5' => $heading !== '' || $subheading !== '',
        ])>
            {{ $slot }}
        </div>
    </div>
</div>
