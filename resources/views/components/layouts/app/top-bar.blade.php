@props([
    /**
     * @var list<array{label: string, href?: string|null}>
     */
    'breadcrumbs' => [],
])

@php
    use App\View\Breadcrumbs;
    $authUser = auth()->user();
    $notifications = $authUser->appNotifications()->limit(8)->get();
    $unreadCount = $authUser->appNotifications()->whereNull('read_at')->count();
    $hasBreadcrumbs = $breadcrumbs !== [];
    $canWorkspaceTools = $authUser->canAccessWorkspaceTools();
    $pageTitle = Breadcrumbs::currentLabel() ?? config('app.name');
    $breadcrumbParents = $hasBreadcrumbs ? array_slice($breadcrumbs, 0, -1) : [];
@endphp

<div class="flex w-full shrink-0 flex-col border-b border-zinc-200/80 bg-white/92 shadow-sm shadow-zinc-950/[0.04] backdrop-blur-xl dark:border-white/[0.08] dark:bg-zinc-950/90 dark:shadow-black/25">
    <div
        class="h-px w-full bg-gradient-to-r from-teal-500/0 via-teal-500/40 to-indigo-500/0 dark:from-teal-400/0 dark:via-teal-400/30 dark:to-indigo-400/0"
        aria-hidden="true"
    ></div>

    <div class="flex min-h-[4rem] items-center gap-3 px-4 sm:px-6 lg:px-8">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <div class="hidden lg:block">
            <flux:sidebar.collapse
                class="!relative !opacity-100"
                :tooltip="__('Collapse sidebar')"
                tooltip-position="bottom"
            />
        </div>

        <div class="min-w-0 flex-1">
            <div class="flex min-w-0 items-center gap-2">
                <a
                    href="{{ route($authUser->homeRouteName()) }}"
                    class="truncate text-sm font-semibold text-zinc-500 transition hover:text-teal-700 lg:hidden dark:text-zinc-400 dark:hover:text-teal-400"
                    wire:navigate
                >
                    {{ config('app.name') }}
                </a>
                @if ($hasBreadcrumbs)
                    <span class="text-zinc-300 lg:hidden dark:text-zinc-600" aria-hidden="true">/</span>
                @endif
                <h1 class="truncate text-lg font-semibold tracking-tight text-zinc-900 sm:text-xl dark:text-zinc-50">
                    {{ $pageTitle }}
                </h1>
            </div>

            @if ($hasBreadcrumbs)
                <nav
                    class="mt-0.5 hidden min-w-0 lg:block"
                    aria-label="{{ __('Breadcrumb') }}"
                >
                    <ol class="flex min-w-0 flex-wrap items-center gap-1 text-xs text-zinc-500 dark:text-zinc-400">
                        @foreach ($breadcrumbParents as $item)
                            <li class="flex min-w-0 items-center gap-1">
                                @if (! empty($item['href']))
                                    <a
                                        href="{{ $item['href'] }}"
                                        class="truncate font-medium transition hover:text-teal-700 dark:hover:text-teal-400"
                                        wire:navigate
                                    >
                                        {{ $item['label'] }}
                                    </a>
                                @else
                                    <span class="truncate font-medium">{{ $item['label'] }}</span>
                                @endif
                                <flux:icon.chevron-right variant="mini" class="size-3 shrink-0 text-zinc-300 dark:text-zinc-600" />
                            </li>
                        @endforeach
                    </ol>
                </nav>
            @else
                <p class="mt-0.5 hidden truncate text-xs text-zinc-500 lg:block dark:text-zinc-500">
                    {{ __('Signed in as :name', ['name' => $authUser->buildFullName() ?: $authUser->name]) }}
                </p>
            @endif
        </div>

        <flux:spacer />

        <div
            class="flex shrink-0 items-center gap-1 rounded-2xl border border-zinc-200/90 bg-zinc-50/80 p-1 shadow-sm shadow-zinc-950/[0.04] dark:border-white/10 dark:bg-zinc-900/50 dark:shadow-black/20"
        >
            @if ($canWorkspaceTools)
                <flux:tooltip content="{{ __('Documents') }}" position="bottom">
                    <flux:button
                        variant="subtle"
                        size="sm"
                        class="!size-9 !rounded-xl transition-colors duration-200 [&[data-current]]:bg-white [&[data-current]]:text-teal-800 [&[data-current]]:shadow-sm dark:[&[data-current]]:bg-white/10 dark:[&[data-current]]:text-teal-300"
                        :href="route('documents.index')"
                        icon="document-check"
                        icon:variant="outline"
                        wire:navigate
                        :data-current="request()->routeIs('documents.*')"
                        :aria-label="__('Documents')"
                    />
                </flux:tooltip>

                <flux:tooltip content="{{ __('Verify') }}" position="bottom">
                    <flux:button
                        variant="subtle"
                        size="sm"
                        class="!size-9 !rounded-xl transition-colors duration-200 [&[data-current]]:bg-white [&[data-current]]:text-teal-800 [&[data-current]]:shadow-sm dark:[&[data-current]]:bg-white/10 dark:[&[data-current]]:text-teal-300"
                        :href="route('verify.index')"
                        icon="magnifying-glass"
                        icon:variant="outline"
                        wire:navigate
                        :data-current="request()->routeIs('verify.*')"
                        :aria-label="__('Verify')"
                    />
                </flux:tooltip>
            @endif

            <flux:dropdown position="bottom" align="end">
                <div class="relative">
                    <flux:button
                        variant="subtle"
                        size="sm"
                        class="!size-9 !rounded-xl transition-colors duration-200"
                        icon="bell"
                        icon:variant="outline"
                        :aria-label="__('Notifications')"
                    />
                    @if ($unreadCount > 0)
                        <span class="pointer-events-none absolute -end-0.5 -top-0.5 inline-flex min-w-[1.125rem] items-center justify-center rounded-full bg-red-500 px-1 py-0.5 text-[10px] font-bold leading-none text-white ring-2 ring-white dark:ring-zinc-900">
                            {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                        </span>
                    @endif
                </div>

                <flux:menu
                    class="w-80 max-w-[calc(100vw-2rem)] overflow-hidden border-zinc-200/90 bg-white/95 p-0 shadow-lg shadow-zinc-950/10 backdrop-blur-md dark:border-zinc-600/60 dark:bg-zinc-900/95 dark:shadow-black/40"
                >
                    <div
                        class="flex items-center justify-between gap-3 border-b border-zinc-200/90 bg-zinc-50/90 px-4 py-3 dark:border-white/10 dark:bg-zinc-950/50"
                    >
                        <flux:heading size="lg">{{ __('Notifications') }}</flux:heading>
                        @if ($unreadCount > 0)
                            <flux:badge size="sm" color="red">{{ __(':count unread', ['count' => $unreadCount]) }}</flux:badge>
                        @endif
                    </div>
                    @if ($notifications->isEmpty())
                        <div
                            class="max-h-72 overflow-y-auto bg-white/50 p-8 text-center text-sm text-zinc-600 dark:bg-zinc-900/30 dark:text-zinc-400"
                        >
                            <flux:icon.bell variant="outline" class="mx-auto mb-2 size-8 text-zinc-300 dark:text-zinc-600" />
                            {{ __('No notifications yet.') }}
                        </div>
                    @else
                        <div class="max-h-72 overflow-y-auto divide-y divide-zinc-200/70 dark:divide-zinc-700/70">
                            @foreach ($notifications as $notification)
                                <div class="px-4 py-3 text-sm {{ $notification->read_at === null ? 'bg-teal-50/60 dark:bg-teal-500/10' : 'bg-white/30 dark:bg-zinc-900/20' }}">
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

            <div x-data="{
                isDark: document.documentElement.classList.contains('dark'),
                toggle() {
                    this.isDark = !this.isDark;
                    const theme = this.isDark ? 'dark' : 'light';

                    if (window.Flux?.applyAppearance) {
                        window.Flux.applyAppearance(theme);
                    } else {
                        document.documentElement.classList.toggle('dark', this.isDark);
                    }

                    localStorage.setItem('docutrust-theme', theme);
                    localStorage.setItem('theme', theme);
                }
            }">
                <flux:tooltip content="{{ __('Switch to Dark Mode') }}" position="bottom">
                    <flux:button
                        x-show="! isDark"
                        variant="subtle"
                        size="sm"
                        class="!size-9 !rounded-xl transition-colors duration-200"
                        x-on:click="toggle()"
                        icon="moon"
                        icon:variant="outline"
                        aria-label="{{ __('Switch to Dark Mode') }}"
                    />
                </flux:tooltip>

                <flux:tooltip content="{{ __('Switch to Light Mode') }}" position="bottom">
                    <flux:button
                        x-show="isDark"
                        variant="subtle"
                        size="sm"
                        class="!size-9 !rounded-xl transition-colors duration-200"
                        x-on:click="toggle()"
                        icon="sun"
                        icon:variant="outline"
                        aria-label="{{ __('Switch to Light Mode') }}"
                    />
                </flux:tooltip>
            </div>
        </div>

        <x-user.profile-menu :user="$authUser" class="shrink-0" />

    </div>

    @if ($hasBreadcrumbs)
        <div class="border-t border-zinc-200/60 bg-zinc-50/70 px-4 py-2 lg:hidden dark:border-white/10 dark:bg-zinc-950/40 sm:px-6">
            <x-app-breadcrumbs :items="$breadcrumbs" class="mb-0" />
        </div>
    @endif
</div>
