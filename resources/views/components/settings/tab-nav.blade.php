@props([
    'active' => 'profile',
])

@php
    $tabs = [
        'profile' => ['label' => __('Profile'), 'icon' => 'user-circle', 'danger' => false],
        'password' => ['label' => __('Password'), 'icon' => 'key', 'danger' => false],
        'security' => ['label' => __('Security'), 'icon' => 'shield-check', 'danger' => false],
        'appearance' => ['label' => __('Appearance'), 'icon' => 'paint-brush', 'danger' => false],
        'danger' => ['label' => __('Danger zone'), 'icon' => 'exclamation-triangle', 'danger' => true],
    ];

    $tabButtonClasses = function (string $key, bool $isDanger) use ($active): string {
        $isActive = $active === $key;

        if ($isDanger) {
            return \Illuminate\Support\Arr::toCssClasses([
                'group flex w-full items-center gap-2.5 text-left text-sm font-medium transition',
                'rounded-lg px-3 py-2.5 lg:rounded-r-xl lg:rounded-l-none lg:py-2',
                'border-s-2 border-transparent lg:ps-3.5',
                $isActive
                    ? 'border-rose-500 bg-rose-50 text-rose-800 dark:border-rose-400 dark:bg-rose-950/40 dark:text-rose-200'
                    : 'text-rose-700 hover:bg-rose-50/80 hover:text-rose-900 dark:text-rose-400 dark:hover:bg-rose-950/30 dark:hover:text-rose-200',
            ]);
        }

        return \Illuminate\Support\Arr::toCssClasses([
            'group flex w-full items-center gap-2.5 text-left text-sm font-medium transition',
            'rounded-lg px-3 py-2.5 lg:rounded-r-xl lg:rounded-l-none lg:py-2',
            'border-s-2 border-transparent lg:ps-3.5',
            $isActive
                ? 'border-teal-500 bg-teal-50/90 text-teal-900 dark:border-teal-400 dark:bg-teal-500/10 dark:text-teal-200'
                : 'text-zinc-600 hover:bg-zinc-100/90 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-white/[0.06] dark:hover:text-zinc-100',
        ]);
    };

    $mobileTabClasses = function (string $key, bool $isDanger) use ($active): string {
        $isActive = $active === $key;

        if ($isDanger) {
            return \Illuminate\Support\Arr::toCssClasses([
                'inline-flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-3 text-sm font-medium transition -mb-px',
                $isActive
                    ? 'border-rose-500 text-rose-700 dark:border-rose-400 dark:text-rose-300'
                    : 'border-transparent text-rose-600/80 hover:text-rose-700 dark:text-rose-400/80',
            ]);
        }

        return \Illuminate\Support\Arr::toCssClasses([
            'inline-flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-3 text-sm font-medium transition -mb-px',
            $isActive
                ? 'border-teal-500 text-teal-800 dark:border-teal-400 dark:text-teal-300'
                : 'border-transparent text-zinc-500 hover:border-zinc-300 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200',
        ]);
    };
@endphp

{{-- Mobile: underline tabs --}}
<nav
    class="mb-5 -mx-1 overflow-x-auto border-b border-zinc-200/90 px-1 dark:border-zinc-700/80 lg:hidden"
    aria-label="{{ __('Settings sections') }}"
>
    <div class="flex min-w-max gap-0.5" role="tablist">
        @foreach ($tabs as $key => $tab)
            <button
                type="button"
                role="tab"
                wire:click="$set('tab', '{{ $key }}')"
                aria-selected="{{ $active === $key ? 'true' : 'false' }}"
                class="{{ $mobileTabClasses($key, $tab['danger']) }}"
            >
                <flux:icon :name="$tab['icon']" variant="mini" class="size-4 shrink-0 opacity-80" />
                <span>{{ $tab['label'] }}</span>
            </button>
        @endforeach
    </div>
</nav>

{{-- Desktop: vertical section nav --}}
<nav
    {{ $attributes->class('hidden shrink-0 lg:block lg:w-56 xl:w-60') }}
    aria-label="{{ __('Settings sections') }}"
>
    <p class="mb-2 px-3 text-[10px] font-bold uppercase tracking-[0.14em] text-zinc-400 dark:text-zinc-600">
        {{ __('Sections') }}
    </p>
    <div class="space-y-0.5 rounded-xl border border-zinc-200/80 bg-zinc-50/50 p-1.5 dark:border-zinc-700/80 dark:bg-zinc-900/40" role="tablist">
        @foreach ($tabs as $key => $tab)
            <button
                type="button"
                role="tab"
                wire:click="$set('tab', '{{ $key }}')"
                aria-selected="{{ $active === $key ? 'true' : 'false' }}"
                class="{{ $tabButtonClasses($key, $tab['danger']) }}"
            >
                <flux:icon
                    :name="$tab['icon']"
                    variant="mini"
                    @class([
                        'size-4 shrink-0',
                        'text-teal-600 dark:text-teal-400' => $active === $key && ! $tab['danger'],
                        'text-rose-600 dark:text-rose-400' => $active === $key && $tab['danger'],
                        'text-zinc-400 group-hover:text-zinc-600 dark:text-zinc-500 dark:group-hover:text-zinc-300' => $active !== $key,
                    ])
                />
                <span class="min-w-0 truncate">{{ $tab['label'] }}</span>
            </button>
        @endforeach
    </div>
</nav>
