@php
    use App\Enums\UserRole;
    $navRole = auth()->user()->role;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="app-shell min-h-screen bg-zinc-100 dark:bg-zinc-950">
        <flux:sidebar
            sticky
            collapsible
            class="[:where(&)]:w-[240px] data-flux-sidebar-collapsed-desktop:w-[68px] border-r border-zinc-200/60 bg-white px-2 py-3 shadow-none transition-[width,padding] duration-300 ease-out dark:border-white/5 dark:bg-[#0f1117]"
        >
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            {{-- ── Brand ── --}}
            <div class="mb-1 flex items-center">
                <a
                    href="{{ route(auth()->user()->homeRouteName()) }}"
                    wire:navigate
                    class="flex min-w-0 flex-1 items-center gap-3 rounded-xl px-2 py-2 transition hover:bg-zinc-50 in-data-flux-sidebar-collapsed-desktop:justify-center in-data-flux-sidebar-collapsed-desktop:gap-0 in-data-flux-sidebar-collapsed-desktop:px-0 dark:hover:bg-white/5"
                >
                    <div class="flex aspect-square size-9 shrink-0 items-center justify-center rounded-lg border border-zinc-200 bg-transparent dark:border-zinc-700">
                        <x-app-logo-icon class="size-full fill-zinc-700 text-zinc-700 dark:fill-zinc-200 dark:text-zinc-200" />
                    </div>
                    <span class="truncate text-[15px] font-bold tracking-tight text-zinc-900 in-data-flux-sidebar-collapsed-desktop:hidden dark:text-zinc-100">
                        {{ config('app.name') }}
                    </span>
                </a>
            </div>

            {{-- ── Nav ── --}}
            <flux:sidebar.nav
                class="overflow-visible px-1 py-1
                    [&_[data-flux-sidebar-item]]:my-0.5
                    [&_[data-flux-sidebar-item]]:h-10
                    [&_[data-flux-sidebar-item]]:rounded-xl
                    [&_[data-flux-sidebar-item]]:px-3
                    [&_[data-flux-sidebar-item]]:py-2
                    [&_[data-flux-sidebar-item]]:transition-all
                    [&_[data-flux-sidebar-item]]:duration-150
                    [&_[data-flux-sidebar-item][data-current]]:bg-teal-50
                    [&_[data-flux-sidebar-item][data-current]]:text-teal-700
                    [&_[data-flux-sidebar-item][data-current]]:shadow-none
                    dark:[&_[data-flux-sidebar-item][data-current]]:bg-teal-500/10
                    dark:[&_[data-flux-sidebar-item][data-current]]:text-teal-400
                    [&_[data-content]]:text-[13.5px]
                    [&_[data-content]]:font-semibold
                    [&_[data-flux-icon]]:size-[18px]
                    data-flux-sidebar-collapsed-desktop:[&_[data-flux-sidebar-item]]:mx-auto
                    data-flux-sidebar-collapsed-desktop:[&_[data-flux-sidebar-item]]:w-10
                    data-flux-sidebar-collapsed-desktop:[&_[data-flux-sidebar-item]]:justify-center
                    data-flux-sidebar-collapsed-desktop:[&_[data-flux-sidebar-item]]:px-0"
            >
                {{-- Section label --}}
                <div class="mb-1 px-3 pt-1 text-[10px] font-bold uppercase tracking-widest text-zinc-400 in-data-flux-sidebar-collapsed-desktop:hidden dark:text-zinc-600">
                    {{ __('Workspace') }}
                </div>

                @if (in_array($navRole, [UserRole::SuperAdmin, UserRole::NotaryAdmin], true))
                    <flux:sidebar.item
                        icon="home"
                        :href="route('dashboard')"
                        :current="request()->routeIs('dashboard')"
                        :tooltip="__('e-Notary Dashboard')"
                        wire:navigate
                    >{{ __('e-Notary') }}</flux:sidebar.item>

                    <flux:sidebar.item
                        icon="chart-bar"
                        :href="route('admin.signing.dashboard')"
                        :current="request()->routeIs('admin.signing.dashboard')"
                        :tooltip="__('Signing Dashboard')"
                        wire:navigate
                    >{{ __('Signing') }}</flux:sidebar.item>

                    <flux:sidebar.item
                        icon="shield-check"
                        :href="route('admin.compliance.dashboard')"
                        :current="request()->routeIs('admin.compliance.*')"
                        :tooltip="__('Signature Compliance')"
                        wire:navigate
                    >{{ __('Compliance') }}</flux:sidebar.item>
                @endif

                @if ($navRole === UserRole::SuperAdmin)
                    <flux:sidebar.item
                        icon="users"
                        :href="route('admin.users.index')"
                        :current="request()->routeIs('admin.users.*')"
                        :tooltip="__('Platform Users')"
                        wire:navigate
                    >{{ __('Users') }}</flux:sidebar.item>
                @endif

                @if ($navRole === UserRole::Notary)
                    <flux:sidebar.item
                        icon="scale"
                        :href="route('notary.dashboard')"
                        :current="request()->routeIs('notary.dashboard')"
                        :tooltip="__('e-Notary Dashboard')"
                        wire:navigate
                    >{{ __('e-Notary Dashboard') }}</flux:sidebar.item>

                    <flux:sidebar.item
                        icon="clipboard-document-list"
                        :href="route('notary.requests.index')"
                        :current="request()->routeIs('notary.requests.*')"
                        :tooltip="__('Notary Requests')"
                        wire:navigate
                    >{{ __('Notary Requests') }}</flux:sidebar.item>
                @endif

                @if (auth()->user()->canAccessWorkspaceTools())
                    <flux:sidebar.item
                        icon="clipboard-document-list"
                        :href="route('notary-requests.index')"
                        :current="request()->routeIs('notary-requests.*')"
                        :tooltip="__('Notary Requests')"
                        wire:navigate
                    >{{ __('Notary Requests') }}</flux:sidebar.item>

                    <flux:sidebar.item
                        icon="layout-grid"
                        :href="route('documents.index')"
                        :current="request()->routeIs('documents.*')"
                        :tooltip="__('Documents')"
                        wire:navigate
                    >{{ __('Documents') }}</flux:sidebar.item>

                    <flux:sidebar.item
                        icon="user-group"
                        :href="route('contacts.index')"
                        :current="request()->routeIs('contacts.*')"
                        :tooltip="__('Contacts')"
                        wire:navigate
                    >{{ __('Contacts') }}</flux:sidebar.item>

                    <flux:sidebar.item
                        icon="clipboard-document"
                        :href="route('templates.index')"
                        :current="request()->routeIs('templates.*')"
                        :tooltip="__('Templates')"
                        wire:navigate
                    >{{ __('Templates') }}</flux:sidebar.item>

                    {{-- Divider before Verify --}}
                    <div class="my-2 mx-3 border-t border-zinc-100 in-data-flux-sidebar-collapsed-desktop:mx-auto in-data-flux-sidebar-collapsed-desktop:w-6 dark:border-zinc-800/80"></div>

                    <flux:sidebar.item
                        icon="magnifying-glass"
                        :href="route('verify.index')"
                        :current="request()->routeIs('verify.*')"
                        :tooltip="__('Verify')"
                        wire:navigate
                    >{{ __('Verify') }}</flux:sidebar.item>
                @endif
            </flux:sidebar.nav>

        </flux:sidebar>

        {{ $slot }}

        @include('partials.idle-session')
        @stack('scripts')
        @fluxScripts
    </body>
</html>
