@props([
    'heading' => '',
    'subheading' => '',
    'wide' => true,
])

<div class="flex items-start max-md:flex-col">
    <div class="mr-8 w-full shrink-0 pb-4 md:w-[200px] lg:mr-10">
        <flux:navlist>
            <flux:navlist.item
                :href="route('settings.trust-profile')"
                :current="request()->routeIs('settings.trust-profile', 'settings.attorney-application')"
                wire:navigate
            >
                {{ __('Trust profile') }}
            </flux:navlist.item>
            <flux:navlist.item
                :href="route('settings.profile')"
                :current="request()->routeIs('settings.profile', 'settings.password', 'settings.security', 'settings.appearance')"
                wire:navigate
            >
                {{ __('Settings') }}
            </flux:navlist.item>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="min-w-0 flex-1 self-stretch max-md:pt-6">
        @if ($heading !== '')
            <flux:heading>{{ $heading }}</flux:heading>
        @endif
        @if ($subheading !== '')
            <flux:subheading>{{ $subheading }}</flux:subheading>
        @endif

        <div @class([
            'w-full',
            $wide ? 'max-w-7xl' : 'max-w-3xl',
            'mt-5' => $heading !== '' || $subheading !== '',
        ])>
            {{ $slot }}
        </div>
    </div>
</div>
