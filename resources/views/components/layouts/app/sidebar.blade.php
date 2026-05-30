@php
    use App\Enums\UserRole;
    use App\Services\TrustProfile\TrustProfileService;
    $navRole = auth()->user()->role;
    $navUser = auth()->user();
    $navRoleLabel = app(TrustProfileService::class)->summary($navUser)['role_label'];
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
            class="[:where(&)]:w-[248px] !gap-0 data-flux-sidebar-collapsed-desktop:w-[72px] border-r border-zinc-200/70 bg-white px-2.5 py-3 shadow-[inset_-1px_0_0_rgb(255_255_255/0.6)] transition-[width,padding] duration-300 ease-out dark:border-white/[0.06] dark:bg-[#0f1117] dark:shadow-none"
        >
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            {{-- ── Brand ── --}}
            <div class="mb-2 shrink-0">
                <a
                    href="{{ route($navUser->homeRouteName()) }}"
                    wire:navigate
                    class="group flex min-w-0 flex-1 items-center gap-3 rounded-xl px-2 py-2 transition hover:bg-zinc-50 in-data-flux-sidebar-collapsed-desktop:justify-center in-data-flux-sidebar-collapsed-desktop:gap-0 in-data-flux-sidebar-collapsed-desktop:px-0 dark:hover:bg-white/5"
                >
                    <div class="flex aspect-square size-9 shrink-0 items-center justify-center rounded-xl border border-zinc-200/90 bg-gradient-to-br from-teal-500/10 via-white to-emerald-500/5 shadow-sm ring-1 ring-zinc-950/[0.04] transition group-hover:border-teal-200/80 group-hover:ring-teal-500/20 dark:border-zinc-700/80 dark:from-teal-500/15 dark:via-zinc-900 dark:to-emerald-500/10 dark:ring-white/5 dark:group-hover:border-teal-500/30">
                        <x-app-logo-icon class="size-5 fill-teal-700 text-teal-700 dark:fill-teal-300 dark:text-teal-300" />
                    </div>
                    <div class="min-w-0 flex-1 in-data-flux-sidebar-collapsed-desktop:hidden">
                        <span class="block truncate text-[15px] font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
                            {{ config('app.name') }}
                        </span>
                        <span class="mt-0.5 block truncate text-[11px] font-medium text-zinc-500 dark:text-zinc-500">
                            {{ $navRoleLabel }}
                        </span>
                    </div>
                </a>
            </div>

            {{-- ── Nav ── --}}
            <flux:sidebar.nav
                class="min-h-0 flex-1 overflow-y-auto overflow-x-visible px-1 py-0.5
                    [&_[data-flux-sidebar-item]]:relative
                    [&_[data-flux-sidebar-item]]:my-0.5
                    [&_[data-flux-sidebar-item]]:h-10
                    [&_[data-flux-sidebar-item]]:rounded-xl
                    [&_[data-flux-sidebar-item]]:px-3
                    [&_[data-flux-sidebar-item]]:py-2
                    [&_[data-flux-sidebar-item]]:text-zinc-600
                    [&_[data-flux-sidebar-item]]:transition-all
                    [&_[data-flux-sidebar-item]]:duration-150
                    [&_[data-flux-sidebar-item]]:hover:bg-zinc-100
                    [&_[data-flux-sidebar-item]]:hover:text-zinc-900
                    dark:[&_[data-flux-sidebar-item]]:text-zinc-400
                    dark:[&_[data-flux-sidebar-item]]:hover:bg-white/[0.06]
                    dark:[&_[data-flux-sidebar-item]]:hover:text-zinc-100
                    [&_[data-flux-sidebar-item][data-current]]:bg-teal-50
                    [&_[data-flux-sidebar-item][data-current]]:text-teal-800
                    [&_[data-flux-sidebar-item][data-current]]:shadow-none
                    [&_[data-flux-sidebar-item][data-current]]:before:absolute
                    [&_[data-flux-sidebar-item][data-current]]:before:inset-y-1.5
                    [&_[data-flux-sidebar-item][data-current]]:before:start-0
                    [&_[data-flux-sidebar-item][data-current]]:before:w-0.5
                    [&_[data-flux-sidebar-item][data-current]]:before:rounded-full
                    [&_[data-flux-sidebar-item][data-current]]:before:bg-teal-500
                    dark:[&_[data-flux-sidebar-item][data-current]]:bg-teal-500/10
                    dark:[&_[data-flux-sidebar-item][data-current]]:text-teal-300
                    dark:[&_[data-flux-sidebar-item][data-current]]:before:bg-teal-400
                    [&_[data-content]]:text-[13.5px]
                    [&_[data-content]]:font-semibold
                    [&_[data-flux-icon]]:size-[18px]
                    data-flux-sidebar-collapsed-desktop:[&_[data-flux-sidebar-item]]:mx-auto
                    data-flux-sidebar-collapsed-desktop:[&_[data-flux-sidebar-item]]:w-10
                    data-flux-sidebar-collapsed-desktop:[&_[data-flux-sidebar-item]]:justify-center
                    data-flux-sidebar-collapsed-desktop:[&_[data-flux-sidebar-item]]:px-0
                    data-flux-sidebar-collapsed-desktop:[&_[data-flux-sidebar-item][data-current]]:before:hidden"
            >
                {{-- Section label --}}
                <div class="mb-1.5 px-3 pt-0.5 text-[10px] font-bold uppercase tracking-[0.14em] text-zinc-400 in-data-flux-sidebar-collapsed-desktop:hidden dark:text-zinc-600">
                    {{ __('Workspace') }}
                </div>

                @if ($navRole === UserRole::SuperAdmin)
                    <flux:sidebar.item
                        icon="home"
                        :href="route('dashboard')"
                        :current="request()->routeIs('dashboard')"
                        :tooltip="__('Platform Dashboard')"
                        wire:navigate
                    >{{ __('Platform') }}</flux:sidebar.item>

                    <flux:sidebar.item
                        icon="scale"
                        :href="route('admin.enotary.dashboard')"
                        :current="request()->routeIs('admin.enotary.dashboard')"
                        :tooltip="__('e-Notary Dashboard')"
                        wire:navigate
                    >{{ __('e-Notary') }}</flux:sidebar.item>
                @elseif ($navRole === UserRole::NotaryAdmin)
                    <flux:sidebar.item
                        icon="home"
                        :href="route('admin.enotary.dashboard')"
                        :current="request()->routeIs('admin.enotary.dashboard')"
                        :tooltip="__('e-Notary Dashboard')"
                        wire:navigate
                    >{{ __('e-Notary') }}</flux:sidebar.item>
                @endif

                @if (in_array($navRole, [UserRole::SuperAdmin, UserRole::NotaryAdmin], true))

                    <flux:sidebar.item
                        icon="chart-bar"
                        :href="route('admin.signing.dashboard')"
                        :current="request()->routeIs('admin.signing.dashboard')"
                        :tooltip="__('Signing Dashboard')"
                        wire:navigate
                    >{{ __('Signing') }}</flux:sidebar.item>

                    <flux:sidebar.item
                        icon="clipboard-document-check"
                        :href="route('admin.attorney-applications.index')"
                        :current="request()->routeIs('admin.attorney-applications.*')"
                        :tooltip="__('Attorney applications')"
                        wire:navigate
                    >{{ __('Attorney apps') }}</flux:sidebar.item>

                    <flux:sidebar.item
                        icon="receipt-percent"
                        :href="route('notary-admin.einvoices')"
                        :current="request()->routeIs('notary-admin.einvoices')"
                        :tooltip="__('E-Invoices')"
                        wire:navigate
                    >{{ __('E-Invoices') }}</flux:sidebar.item>

                    <flux:sidebar.item
                        icon="building-office-2"
                        :href="route('notary-admin.billing-profile')"
                        :current="request()->routeIs('notary-admin.billing-profile')"
                        :tooltip="__('Billing Profile')"
                        wire:navigate
                    >{{ __('Billing Profile') }}</flux:sidebar.item>
                @endif

                @if ($navRole === UserRole::SuperAdmin)
                    <flux:sidebar.item
                        icon="shield-check"
                        :href="route('admin.compliance.dashboard')"
                        :current="request()->routeIs('admin.compliance.*')"
                        :tooltip="__('Signature Compliance')"
                        wire:navigate
                    >{{ __('Compliance') }}</flux:sidebar.item>

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
                        :current="request()->routeIs('notary.requests.*') && ! request()->routeIs('notary.attorney-registries.*')"
                        :tooltip="__('Notarizations')"
                        wire:navigate
                    >{{ __('Notarizations') }}</flux:sidebar.item>

                    <flux:sidebar.item
                        icon="book-open"
                        :href="route('notary.attorney-registries.index')"
                        :current="request()->routeIs('notary.attorney-registries.*', 'notary.attorney-registry')"
                        :tooltip="__('Notary registry')"
                        wire:navigate
                    >{{ __('Notary registry') }}</flux:sidebar.item>
                @endif

                @if ($navUser->canManageNotaryRequestPortal())
                    <flux:sidebar.item
                        icon="clipboard-document-list"
                        :href="route('notary-requests.index')"
                        :current="request()->routeIs('notary-requests.*')"
                        :tooltip="__('Notarizations')"
                        wire:navigate
                    >{{ __('Notarizations') }}</flux:sidebar.item>
                @endif

                @if ($navUser->canAccessSigningWorkspace())
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
                @endif

                @if ($navUser->canAccessWorkspaceTools())
                    {{-- Divider before Verify --}}
                    <div class="my-2.5 mx-3 border-t border-zinc-100 in-data-flux-sidebar-collapsed-desktop:mx-auto in-data-flux-sidebar-collapsed-desktop:w-6 dark:border-zinc-800/80"></div>

                    <flux:sidebar.item
                        icon="magnifying-glass"
                        :href="route('verify.index')"
                        :current="request()->routeIs('verify.*')"
                        :tooltip="__('Verify')"
                        wire:navigate
                    >{{ __('Verify') }}</flux:sidebar.item>
                @endif
            </flux:sidebar.nav>

            <flux:sidebar.spacer />

            {{-- ── Account footer ── --}}
            <div class="shrink-0 space-y-1 border-t border-zinc-100 pt-2 dark:border-zinc-800/80">
                <div class="mb-1 px-3 text-[10px] font-bold uppercase tracking-[0.14em] text-zinc-400 in-data-flux-sidebar-collapsed-desktop:hidden dark:text-zinc-600">
                    {{ __('Account') }}
                </div>

                <flux:sidebar.nav
                    class="px-1
                        [&_[data-flux-sidebar-item]]:my-0.5
                        [&_[data-flux-sidebar-item]]:h-9
                        [&_[data-flux-sidebar-item]]:rounded-xl
                        [&_[data-flux-sidebar-item]]:px-3
                        [&_[data-flux-sidebar-item]]:text-zinc-500
                        [&_[data-flux-sidebar-item]]:transition-colors
                        [&_[data-flux-sidebar-item]]:hover:bg-zinc-100
                        [&_[data-flux-sidebar-item]]:hover:text-zinc-800
                        dark:[&_[data-flux-sidebar-item]]:text-zinc-500
                        dark:[&_[data-flux-sidebar-item]]:hover:bg-white/[0.06]
                        dark:[&_[data-flux-sidebar-item]]:hover:text-zinc-200
                        [&_[data-flux-sidebar-item][data-current]]:bg-zinc-100
                        [&_[data-flux-sidebar-item][data-current]]:text-zinc-900
                        dark:[&_[data-flux-sidebar-item][data-current]]:bg-white/[0.08]
                        dark:[&_[data-flux-sidebar-item][data-current]]:text-zinc-100
                        [&_[data-content]]:text-[13px]
                        [&_[data-content]]:font-medium
                        [&_[data-flux-icon]]:size-4
                        data-flux-sidebar-collapsed-desktop:[&_[data-flux-sidebar-item]]:mx-auto
                        data-flux-sidebar-collapsed-desktop:[&_[data-flux-sidebar-item]]:w-9
                        data-flux-sidebar-collapsed-desktop:[&_[data-flux-sidebar-item]]:justify-center
                        data-flux-sidebar-collapsed-desktop:[&_[data-flux-sidebar-item]]:px-0"
                >
                    <flux:sidebar.item
                        icon="shield-check"
                        :href="route('settings.trust-profile')"
                        :current="request()->routeIs('settings.trust-profile')"
                        :tooltip="__('Trust profile')"
                        wire:navigate
                    >{{ __('Trust profile') }}</flux:sidebar.item>

                    <flux:sidebar.item
                        icon="cog-6-tooth"
                        :href="route('settings.profile')"
                        :current="request()->routeIs('settings.profile', 'settings.password', 'settings.security', 'settings.appearance')"
                        :tooltip="__('Settings')"
                        wire:navigate
                    >{{ __('Settings') }}</flux:sidebar.item>
                </flux:sidebar.nav>

                <div class="hidden items-center justify-between px-1 pt-1 lg:flex in-data-flux-sidebar-collapsed-desktop:justify-center">
                    <span class="text-[11px] text-zinc-400 in-data-flux-sidebar-collapsed-desktop:hidden dark:text-zinc-600">
                        {{ __('Collapse') }}
                    </span>
                    <flux:sidebar.collapse
                        class="in-data-flux-sidebar-collapsed-desktop:!relative in-data-flux-sidebar-collapsed-desktop:!opacity-100"
                        :tooltip="__('Collapse sidebar')"
                        tooltip-position="right"
                    />
                </div>
            </div>

        </flux:sidebar>

        {{ $slot }}

        @include('partials.idle-session')
        @stack('scripts')
        @fluxScripts
    </body>
</html>
