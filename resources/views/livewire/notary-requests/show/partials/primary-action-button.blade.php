@php
    $action = $action ?? null;
    $size = $size ?? 'base';
    $class = $class ?? 'w-full sm:w-auto';
@endphp

@if (is_array($action))
    @if ($action['type'] === 'link' && ! empty($action['href']))
        @php
            $shouldUseWireNavigate = ! str_contains((string) $action['href'], '#');
        @endphp

        @if ($shouldUseWireNavigate)
            <flux:button
                :variant="$action['variant'] ?? 'primary'"
                :size="$size"
                :href="$action['href']"
                :class="$class"
                wire:navigate
            >
                {{ $action['label'] }}
            </flux:button>
        @else
            <flux:button
                :variant="$action['variant'] ?? 'primary'"
                :size="$size"
                :href="$action['href']"
                :class="$class"
            >
                {{ $action['label'] }}
            </flux:button>
        @endif
    @elseif ($action['type'] === 'wire' && ! empty($action['action']))
        @php
            $wireAction = $action['action'];
            if (! empty($action['params'])) {
                $wireAction .= '('.collect($action['params'])->map(fn ($p) => is_numeric($p) ? $p : "'{$p}'")->implode(',').')';
            }
        @endphp
        @if (! empty($action['confirm']))
            <flux:button
                :variant="$action['variant'] ?? 'primary'"
                :size="$size"
                type="button"
                wire:click="{{ $wireAction }}"
                wire:confirm="{{ $action['confirm'] }}"
                :class="$class"
            >
                {{ $action['label'] }}
            </flux:button>
        @else
            <flux:button
                :variant="$action['variant'] ?? 'primary'"
                :size="$size"
                type="button"
                wire:click="{{ $wireAction }}"
                :class="$class"
            >
                {{ $action['label'] }}
            </flux:button>
        @endif
    @elseif (($action['type'] ?? '') === 'tab' && ! empty($action['tab']))
        <flux:button
            :variant="$action['variant'] ?? 'primary'"
            :size="$size"
            type="button"
            wire:click="setActiveTab('{{ $action['tab'] }}')"
            :class="$class"
        >
            {{ $action['label'] }}
        </flux:button>
    @endif
@endif
