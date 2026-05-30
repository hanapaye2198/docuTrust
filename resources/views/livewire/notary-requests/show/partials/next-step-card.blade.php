@php
    $portalAction ??= null;
@endphp

@if ($portalAction)
    <div class="ui-panel border-sky-200/80 bg-sky-50/50 p-5 dark:border-sky-900/40 dark:bg-sky-950/20">
        <div class="text-xs font-semibold uppercase tracking-wider text-sky-700 dark:text-sky-300">{{ __('Your next step') }}</div>
        <p class="mt-2 text-sm font-semibold text-sky-950 dark:text-sky-100">{{ $portalAction['label'] }}</p>
        <p class="mt-1 text-xs leading-relaxed text-sky-800 dark:text-sky-200">{{ $portalAction['description'] }}</p>

        @if (($portalAction['type'] ?? '') === 'link' && ! empty($portalAction['href']))
            <flux:button
                class="mt-3"
                :variant="$portalAction['variant'] ?? 'primary'"
                size="sm"
                :href="$portalAction['href']"
                wire:navigate
            >
                {{ $portalAction['label'] }}
            </flux:button>
        @elseif (($portalAction['type'] ?? '') === 'tab' && ! empty($portalAction['tab']))
            <flux:button
                class="mt-3"
                :variant="$portalAction['variant'] ?? 'primary'"
                size="sm"
                type="button"
                wire:click="setActiveTab('{{ $portalAction['tab'] }}')"
            >
                {{ $portalAction['label'] }}
            </flux:button>
        @elseif (($portalAction['type'] ?? '') === 'wire')
            @php
                $wireAction = $portalAction['action'];
                if (! empty($portalAction['params'])) {
                    $wireAction .= '('.collect($portalAction['params'])->map(fn ($p) => is_numeric($p) ? $p : "'{$p}'")->implode(',').')';
                }
            @endphp
            <flux:button
                class="mt-3"
                :variant="$portalAction['variant'] ?? 'primary'"
                size="sm"
                type="button"
                wire:click="{{ $wireAction }}"
            >
                {{ $portalAction['label'] }}
            </flux:button>
        @endif
    </div>
@endif
