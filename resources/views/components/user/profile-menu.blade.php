@props(['user'])

<flux:dropdown position="bottom" align="end" {{ $attributes }}>
    <button
        type="button"
        class="flex max-w-[min(100%,16rem)] min-w-0 cursor-pointer items-center gap-2 rounded-xl border border-zinc-200/80 bg-white py-1.5 pl-1.5 pr-2.5 text-left shadow-sm shadow-zinc-950/[0.04] transition hover:border-zinc-300/90 hover:bg-zinc-50 dark:border-white/10 dark:bg-zinc-900/60 dark:shadow-black/20 dark:hover:border-white/15 dark:hover:bg-zinc-900"
    >
        @if ($user->profile_photo_path)
            <img
                src="{{ route('settings.trust-profile.photo', [], false) }}"
                alt=""
                class="size-8 shrink-0 rounded-lg object-cover"
            />
        @else
            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-teal-500 to-emerald-600 text-xs font-bold text-white">
                {{ $user->initials() }}
            </span>
        @endif
        <span class="hidden min-w-0 truncate text-sm font-semibold text-zinc-800 sm:inline dark:text-zinc-100">
            {{ $user->name }}
        </span>
        <flux:icon name="chevrons-up-down" class="size-4 shrink-0 text-zinc-400" />
    </button>

    <flux:menu class="w-[260px]">
        <flux:menu.radio.group>
            <div class="p-0 text-base font-normal">
                <x-user.profile-dropdown-header :user="$user" />
            </div>
        </flux:menu.radio.group>

        <flux:menu.separator />

        <flux:menu.radio.group>
            <flux:menu.item :href="route('settings.trust-profile', [], false)" icon="shield-check">{{ __('Trust profile') }}</flux:menu.item>
            <flux:menu.item :href="route('settings.profile')" icon="cog-6-tooth" wire:navigate>{{ __('Settings') }}</flux:menu.item>
        </flux:menu.radio.group>

        <flux:menu.separator />

        <form method="POST" action="{{ route('logout') }}" class="w-full">
            @csrf
            <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full text-rose-600 dark:text-rose-400">
                {{ __('Log Out') }}
            </flux:menu.item>
        </form>
    </flux:menu>
</flux:dropdown>
