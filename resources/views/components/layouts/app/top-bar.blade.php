@props([
    /**
     * @var list<array{label: string, href?: string|null}>
     */
    'breadcrumbs' => [],
])

@php
    use App\Enums\UserRole;
    $notifications = auth()->user()->appNotifications()->limit(8)->get();
    $unreadCount = auth()->user()->appNotifications()->whereNull('read_at')->count();
    $hasBreadcrumbs = $breadcrumbs !== [];
    $canWorkspaceTools = auth()->user()->canAccessWorkspaceTools();
@endphp

<div class="flex w-full shrink-0 flex-col border-b border-zinc-200/80 bg-white/90 shadow-sm shadow-zinc-950/5 backdrop-blur-xl dark:border-white/10 dark:bg-zinc-950/85 dark:shadow-black/30">
    <div
        class="h-px w-full bg-gradient-to-r from-teal-500/0 via-teal-500/35 to-indigo-500/0 dark:from-teal-400/0 dark:via-teal-400/25 dark:to-indigo-400/0"
        aria-hidden="true"
    ></div>
    <div
        class="{{ $hasBreadcrumbs ? 'border-b border-zinc-200/50 dark:border-white/10' : '' }} flex h-16 items-center gap-3 px-6 lg:px-8"
    >
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
        <flux:sidebar.collapse class="hidden lg:-ml-2 lg:inline-flex" />

        <a
            href="{{ route(auth()->user()->homeRouteName()) }}"
            class="min-w-0 truncate text-xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100"
            wire:navigate
        >
            {{ config('app.name') }}
        </a>

        <flux:spacer />

        <div
            class="flex shrink-0 items-center gap-0.5 rounded-2xl border border-zinc-200/90 bg-white/70 p-0.5 shadow-sm shadow-zinc-950/5 backdrop-blur-sm transition-[background-color,border-color,box-shadow] duration-200 dark:border-white/10 dark:bg-zinc-900/45 dark:shadow-black/20"
        >
            @if ($canWorkspaceTools)
                <flux:tooltip content="{{ __('Documents') }}" position="bottom">
                    <flux:button
                        variant="subtle"
                        size="base"
                        class="rounded-xl transition-colors duration-200 [&[data-current]]:bg-zinc-950/[0.08] [&[data-current]]:text-zinc-950 [&[data-current]]:shadow-inner dark:[&[data-current]]:bg-white/[0.12] dark:[&[data-current]]:text-white"
                        :href="route('documents.index')"
                        icon="document-check"
                        icon:variant="outline"
                        wire:navigate
                        :data-current="request()->routeIs('documents.*')"
                    />
                </flux:tooltip>
            @endif

            <flux:dropdown position="bottom" align="end">
                <flux:button
                    variant="subtle"
                    size="base"
                    class="rounded-xl transition-colors duration-200"
                    icon="bell"
                    icon:variant="outline"
                    :aria-label="__('Notifications')"
                />
                @if ($unreadCount > 0)
                    <span class="ml-1 inline-flex min-w-5 items-center justify-center rounded-full bg-red-500 px-1.5 py-0.5 text-[10px] font-semibold text-white">
                        {{ $unreadCount }}
                    </span>
                @endif

                <flux:menu
                    class="w-80 max-w-[calc(100vw-2rem)] overflow-hidden border-zinc-200/90 bg-white/95 p-0 shadow-lg shadow-zinc-950/10 backdrop-blur-md dark:border-zinc-600/60 dark:bg-zinc-900/95 dark:shadow-black/40"
                >
                    <div
                        class="border-b border-zinc-200/90 bg-zinc-50/90 px-4 py-3 dark:border-white/10 dark:bg-zinc-950/50"
                    >
                        <flux:heading size="lg">{{ __('Notifications') }}</flux:heading>
                    </div>
                    @if ($notifications->isEmpty())
                        <div
                            class="max-h-72 overflow-y-auto bg-white/50 p-6 text-center text-sm text-zinc-600 dark:bg-zinc-900/30 dark:text-zinc-400"
                        >
                            {{ __('No notifications yet.') }}
                        </div>
                    @else
                        <div class="max-h-72 overflow-y-auto divide-y divide-zinc-200/70 dark:divide-zinc-700/70">
                            @foreach ($notifications as $notification)
                                <div class="px-4 py-3 text-sm {{ $notification->read_at === null ? 'bg-teal-50/50 dark:bg-teal-500/10' : 'bg-white/30 dark:bg-zinc-900/20' }}">
                                    <div class="font-medium text-zinc-800 dark:text-zinc-100">{{ $notification->message }}</div>
                                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $notification->created_at?->diffForHumans() }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </flux:menu>
            </flux:dropdown>
        </div>

        <flux:dropdown class="lg:hidden" position="bottom" align="end">
            <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />

            <flux:menu>
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                            <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                <span
                                    class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                >
                                    {{ auth()->user()->initials() }}
                                </span>
                            </span>

                            <div class="grid flex-1 text-left text-sm leading-tight">
                                <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <flux:menu.radio.group>
                    <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate>Settings</flux:menu.item>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                        {{ __('Log Out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>

        <div class="hidden min-w-0 items-center gap-3 lg:flex">
            <a
                href="{{ route('settings.profile') }}"
                wire:navigate
                class="cursor-pointer truncate rounded-full border border-zinc-200/80 bg-white/80 px-3 py-1.5 text-base font-medium text-zinc-700 shadow-sm transition hover:bg-zinc-100 dark:border-white/10 dark:bg-white/5 dark:text-zinc-200 dark:hover:bg-white/10"
            >
                {{ auth()->user()->name }}
            </a>
        </div>
    </div>

    @if ($hasBreadcrumbs)
        <div class="border-b border-zinc-200/70 bg-zinc-50/80 px-6 py-2 dark:border-white/10 dark:bg-zinc-950/50 lg:px-8">
            <x-app-breadcrumbs :items="$breadcrumbs" class="mb-0" />
        </div>
    @endif
</div>
