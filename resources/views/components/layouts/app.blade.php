<x-layouts.app.sidebar>
    <flux:main class="app-workspace !flex !h-full !min-h-0 !flex-col !p-0">
        <x-layouts.app.top-bar :breadcrumbs="$layoutBreadcrumbs ?? []" />
        <div
            class="main-scroll-area min-h-0 flex-1 overflow-y-auto overscroll-y-contain pl-4 pr-6 pb-8 pt-4 sm:pl-6 sm:pr-8 lg:pl-6 lg:pr-10 in-data-flux-sidebar-collapsed-desktop:lg:px-4"
        >
            {{ $slot }}
        </div>
    </flux:main>
</x-layouts.app.sidebar>
